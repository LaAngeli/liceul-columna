<?php

use App\Actions\SendMessage;
use App\Enums\UserRole;
use App\Filament\Resources\Messages\ComposeSchema;
use App\Filament\Resources\Messages\MessageResource;
use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Messages\Pages\ViewThread;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Message;
use App\Models\MessageState;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\MessageMailbox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

/**
 * Poșta personalului (Filament): fire, foldere, stare per-utilizator + sincronizarea cu poșta
 * cabinetului elev/părinte (aceleași rânduri `messages`).
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

/** @return array{0: Student, 1: SchoolClass} */
function sbxStudentInClass(): array
{
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    return [$student, $class];
}

function sbxParentOf(Student $student): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole(UserRole::Parinte->value);
    $user->students()->attach($student->id);

    return $user;
}

function sbxTeacherOf(SchoolClass $class): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);

    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $class->id,
    ]);

    return $user;
}

// ─── Scoping: fiecare vede DOAR firele la care participă ─────────────────────────────────

it('personalul vede doar conversațiile la care participă', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    $mine = app(SendMessage::class)->direct($parent, $teacher, 'Bună ziua.', 'Despre teme', $student);

    // Fir între ALȚI doi, la care profesorul nu participă.
    [$other, $otherClass] = sbxStudentInClass();
    $otherTeacher = sbxTeacherOf($otherClass);
    $otherParent = sbxParentOf($other);
    $notMine = app(SendMessage::class)->direct($otherParent, $otherTeacher, 'Salut.', 'Altceva', $other);

    actingAs($teacher);

    Livewire::test(ListMessages::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$notMine]);
});

it('directorul NU vede firele altora — nu există „administrația vede tot"', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);
    $thread = app(SendMessage::class)->direct($parent, $teacher, 'Confidențial.', 'Subiect', $student);

    $director = User::factory()->create(['email_verified_at' => now()]);
    $director->assignRole(UserRole::Director->value);

    actingAs($director);

    Livewire::test(ListMessages::class)->assertCanNotSeeTableRecords([$thread]);
});

// ─── Foldere ────────────────────────────────────────────────────────────────────────────

it('Primite conține firele primite, Trimise pe cele inițiate de mine', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    $received = app(SendMessage::class)->direct($parent, $teacher, 'Întrebare.', 'De la părinte', $student);
    $sent = app(SendMessage::class)->direct($teacher, $parent, 'Informare.', 'De la profesor', $student);

    actingAs($teacher);

    Livewire::test(ListMessages::class)
        ->set('activeTab', 'inbox')
        ->assertCanSeeTableRecords([$received])
        ->assertCanNotSeeTableRecords([$sent]);

    Livewire::test(ListMessages::class)
        ->set('activeTab', 'sent')
        ->assertCanSeeTableRecords([$sent])
        ->assertCanNotSeeTableRecords([$received]);

    Livewire::test(ListMessages::class)
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$received, $sent]);
});

it('coșul e per-utilizator: firul dispare din cutia mea, rămâne în a celuilalt', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);
    $thread = app(SendMessage::class)->direct($parent, $teacher, 'Text.', 'Subiect', $student);

    MessageState::create(['message_id' => $thread->id, 'user_id' => $teacher->id, 'trashed_at' => now()]);

    actingAs($teacher);
    Livewire::test(ListMessages::class)
        ->set('activeTab', 'all')
        ->assertCanNotSeeTableRecords([$thread]);

    Livewire::test(ListMessages::class)
        ->set('activeTab', 'trash')
        ->assertCanSeeTableRecords([$thread]);

    // Cutia părintelui (cabinet) nu e afectată — firul e tot acolo.
    expect(Message::query()->whereKey($thread->id)->notTrashedBy($parent->id)->exists())->toBeTrue();
});

it('preferatele sunt un overlay per-utilizator', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);
    $thread = app(SendMessage::class)->direct($parent, $teacher, 'Text.', 'Subiect', $student);

    MessageState::create(['message_id' => $thread->id, 'user_id' => $teacher->id, 'starred_at' => now()]);

    actingAs($teacher);
    Livewire::test(ListMessages::class)
        ->set('activeTab', 'starred')
        ->assertCanSeeTableRecords([$thread]);

    // Steaua profesorului nu apare la părinte.
    expect(MessageState::query()->where('message_id', $thread->id)->where('user_id', $parent->id)->exists())->toBeFalse();
});

// ─── Pagina de conversație: IDOR + citire ───────────────────────────────────────────────

it('pagina conversației e interzisă (403) unui neparticipant', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);
    $thread = app(SendMessage::class)->direct($parent, $teacher, 'Confidențial.', 'Subiect', $student);

    [, $otherClass] = sbxStudentInClass();
    $stranger = sbxTeacherOf($otherClass);

    actingAs($stranger)
        ->get(MessageResource::getUrl('thread', ['record' => $thread]))
        ->assertForbidden();
});

it('deschiderea conversației marchează citite doar mesajele PRIMITE de mine', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    $fromParent = app(SendMessage::class)->direct($parent, $teacher, 'Întrebare.', 'Subiect', $student);
    $fromTeacher = app(SendMessage::class)->reply($teacher, $fromParent, 'Răspuns.');

    actingAs($teacher)
        ->get(MessageResource::getUrl('thread', ['record' => $fromParent]))
        ->assertOk();

    // Mesajul primit de profesor e citit; cel trimis DE el rămâne necitit (îl citește părintele).
    expect($fromParent->refresh()->read_at)->not->toBeNull()
        ->and($fromTeacher->refresh()->read_at)->toBeNull();
});

it('un deep-link către un RĂSPUNS deschide tot conversația (rădăcina)', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    $root = app(SendMessage::class)->direct($parent, $teacher, 'Întrebare.', 'Subiect', $student);
    $reply = app(SendMessage::class)->reply($teacher, $root, 'Răspuns.');

    actingAs($teacher)
        ->get(MessageResource::getUrl('thread', ['record' => $reply]))
        ->assertOk()
        ->assertSee('Întrebare.');
});

// ─── Sincronizarea celor două poște ─────────────────────────────────────────────────────

it('sincronizare: mesajul trimis de părinte din cabinet apare în poșta staff a profesorului', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    // Părintele scrie prin ruta cabinetului (fluxul real, nu direct din Action).
    actingAs($parent)->post(route('cabinet.messages.send'), [
        'type' => 'direct',
        'student_id' => $student->id,
        'recipient_user_id' => $teacher->id,
        'subject' => 'Din cabinet',
        'body' => 'Bună ziua, am o întrebare.',
    ])->assertRedirect();

    $thread = Message::query()->where('subject', 'Din cabinet')->firstOrFail();

    actingAs($teacher);
    Livewire::test(ListMessages::class)
        ->set('activeTab', 'inbox')
        ->assertCanSeeTableRecords([$thread]);
});

it('sincronizare: răspunsul profesorului din panou ajunge în firul părintelui, cu aceiași doi participanți', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    $root = app(SendMessage::class)->direct($parent, $teacher, 'Întrebare.', 'Subiect', $student);
    $reply = app(SendMessage::class)->reply($teacher, $root, 'Vă răspund.');

    expect($reply->parent_id)->toBe($root->id)
        ->and($reply->sender_user_id)->toBe($teacher->id)
        ->and($reply->recipient_user_id)->toBe($parent->id)
        ->and($reply->student_id)->toBe($root->student_id);

    // Invariant: firul are exact doi participanți, aceiași ca rădăcina.
    $participants = Message::query()
        ->where(fn ($q) => $q->whereKey($root->id)->orWhere('parent_id', $root->id))
        ->get()
        ->flatMap(fn (Message $m) => [$m->sender_user_id, $m->recipient_user_id])
        ->unique()
        ->sort()
        ->values();

    expect($participants->all())->toBe(collect([$parent->id, $teacher->id])->sort()->values()->all());
});

// ─── Compunere din panou (Mesaj nou) ────────────────────────────────────────────────────

it('personalul compune către familia unui elev pe care îl PREDĂ, iar mesajul ajunge la părinte', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    $message = ComposeSchema::send($teacher, [
        'kind' => 'family',
        'student_id' => $student->id,
        'family_user_id' => $parent->id,
        'subject' => 'Situația la clasă',
        'body' => 'Vă informez despre progres.',
    ]);

    expect($message->sender_user_id)->toBe($teacher->id)
        ->and($message->recipient_user_id)->toBe($parent->id)
        ->and($message->student_id)->toBe($student->id);

    // Firul apare în poșta părintelui (cabinet) — aceleași rânduri, fără sincronizare separată.
    expect(MessageMailbox::for($parent)->folder('all')->pluck('id')->all())->toContain($message->id);
});

it('personalul NU poate compune către familia unui elev pe care nu îl predă (403)', function () {
    [$mine, $myClass] = sbxStudentInClass();
    [$other] = sbxStudentInClass();
    $teacher = sbxTeacherOf($myClass);
    $otherParent = sbxParentOf($other);
    sbxParentOf($mine);

    expect(fn () => ComposeSchema::send($teacher, [
        'kind' => 'family',
        'student_id' => $other->id,
        'family_user_id' => $otherParent->id,
        'subject' => 'Nepermis',
        'body' => 'Text.',
    ]))->toThrow(HttpException::class);
});

it('personalul compune către un coleg, fără ancoră pe elev', function () {
    [, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $colleague = sbxTeacherOf($class);

    $message = ComposeSchema::send($teacher, [
        'kind' => 'colleague',
        'recipient_user_id' => $colleague->id,
        'subject' => 'Organizatoric',
        'body' => 'Ședință la 15:00.',
    ]);

    expect($message->recipient_user_id)->toBe($colleague->id)
        ->and($message->student_id)->toBeNull();
});

it('atașamentul din panou ajunge pe discul privat', function () {
    Storage::fake('local');
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    $message = ComposeSchema::send($teacher, [
        'kind' => 'family',
        'student_id' => $student->id,
        'family_user_id' => $parent->id,
        'subject' => 'Cu atașament',
        'body' => 'Vedeți fișierul.',
        'files' => [UploadedFile::fake()->create('nota.pdf', 40, 'application/pdf')],
    ]);

    expect($message->attachments()->count())->toBe(1);
    Storage::disk('local')->assertExists($message->attachments()->first()->path);
});

it('panoul respinge tipurile interzise (svg) — aceeași listă albă ca la cabinet', function () {
    Storage::fake('local');
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    expect(fn () => ComposeSchema::send($teacher, [
        'kind' => 'family',
        'student_id' => $student->id,
        'family_user_id' => $parent->id,
        'subject' => 'XSS',
        'body' => 'Text.',
        'files' => [UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')],
    ]))->toThrow(ValidationException::class);
});

// ─── Răspuns INLINE, pe aceeași pagină (nu într-o modală) ───────────────────────────────

it('răspunsul se compune inline pe pagina firului și ajunge la celălalt participant', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    $root = app(SendMessage::class)->direct($parent, $teacher, 'Întrebare.', 'Subiect', $student);

    actingAs($teacher);

    Livewire::test(ViewThread::class, ['record' => $root->id])
        ->assertOk()
        // Formularul de răspuns e pe pagină, nu ascuns într-o acțiune modală.
        ->assertFormExists()
        ->fillForm(['body' => 'Vă răspund imediat.'])
        ->call('sendReply')
        ->assertHasNoFormErrors();

    $reply = Message::query()->where('parent_id', $root->id)->firstOrFail();
    expect($reply->body)->toBe('Vă răspund imediat.')
        ->and($reply->sender_user_id)->toBe($teacher->id)
        ->and($reply->recipient_user_id)->toBe($parent->id);
});

it('răspunsul inline validează corpul gol', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);
    $root = app(SendMessage::class)->direct($parent, $teacher, 'Întrebare.', 'Subiect', $student);

    actingAs($teacher);

    Livewire::test(ViewThread::class, ['record' => $root->id])
        ->fillForm(['body' => ''])
        ->call('sendReply')
        ->assertHasFormErrors(['body' => 'required']);

    expect(Message::query()->where('parent_id', $root->id)->exists())->toBeFalse();
});
