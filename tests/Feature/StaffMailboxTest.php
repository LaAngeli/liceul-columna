<?php

use App\Actions\SendMessage;
use App\Enums\AudienceDomain;
use App\Enums\UserRole;
use App\Filament\Pages\Mailbox;
use App\Filament\Resources\Messages\ComposeSchema;
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

use function Pest\Laravel\actingAs;

/**
 * Poșta personalului (pagina Mailbox, tipar Gmail): foldere, fir, compunere, stare per-utilizator
 * + sincronizarea cu poșta cabinetului (aceleași rânduri `messages`, aceeași MessageMailbox).
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

// ─── Scoping: fiecare vede DOAR conversațiile la care participă ──────────────────────────

it('personalul vede doar conversațiile lui; ale altora nu apar în nicio cutie', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);
    app(SendMessage::class)->direct($parent, $teacher, 'Bună ziua.', 'Subiect-al-meu', $student);

    [$other, $otherClass] = sbxStudentInClass();
    app(SendMessage::class)->direct(sbxParentOf($other), sbxTeacherOf($otherClass), 'Salut.', 'Subiect-strain', $other);

    actingAs($teacher);

    Livewire::test(Mailbox::class)
        ->set('folder', 'inbox')
        ->assertSee('Subiect-al-meu')
        ->assertDontSee('Subiect-strain');
});

it('directorul NU vede firele altora — nu există „administrația vede tot"', function () {
    [$student, $class] = sbxStudentInClass();
    app(SendMessage::class)->direct(sbxParentOf($student), sbxTeacherOf($class), 'Confidențial.', 'Fir-privat', $student);

    $director = User::factory()->create(['email_verified_at' => now()]);
    $director->assignRole(UserRole::Director->value);

    actingAs($director);

    Livewire::test(Mailbox::class)->set('folder', 'inbox')->assertDontSee('Fir-privat');
});

// ─── Semantica folderelor (e-mail) ────────────────────────────────────────────────────────

it('firul inițiat de mine stă în Trimise și intră în Primite abia la răspuns', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    $root = app(SendMessage::class)->direct($teacher, $parent, 'Informare.', 'Fir-initiat', $student);

    actingAs($teacher);

    Livewire::test(Mailbox::class)->set('folder', 'sent')->assertSee('Fir-initiat');
    Livewire::test(Mailbox::class)->set('folder', 'inbox')->assertDontSee('Fir-initiat');

    app(SendMessage::class)->reply($parent, $root, 'Vă mulțumesc.');

    Livewire::test(Mailbox::class)->set('folder', 'inbox')->assertSee('Fir-initiat');
});

it('arhivarea scoate firul din Primite, îl ține în Arhivă și Trimise, fără să atingă cutia celuilalt', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    $root = app(SendMessage::class)->direct($parent, $teacher, 'Întrebare.', 'Fir-arhivabil', $student);
    app(SendMessage::class)->reply($teacher, $root, 'Răspund.');

    actingAs($teacher);

    Livewire::test(Mailbox::class)->call('toggleArchive', $root->id);

    Livewire::test(Mailbox::class)->set('folder', 'inbox')->assertDontSee('Fir-arhivabil');
    Livewire::test(Mailbox::class)->set('folder', 'archive')->assertSee('Fir-arhivabil');
    Livewire::test(Mailbox::class)->set('folder', 'sent')->assertSee('Fir-arhivabil');

    // Cutia părintelui nu e atinsă (a primit răspunsul → firul îi stă în Primite).
    expect(MessageMailbox::for($parent)->folder('inbox')->pluck('id')->all())->toContain($root->id);
});

it('coșul e per-utilizator, iar restaurarea readuce firul', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);
    $root = app(SendMessage::class)->direct($parent, $teacher, 'Text.', 'Fir-la-cos', $student);

    actingAs($teacher);

    Livewire::test(Mailbox::class)->call('moveToTrash', $root->id);
    Livewire::test(Mailbox::class)->set('folder', 'inbox')->assertDontSee('Fir-la-cos');
    Livewire::test(Mailbox::class)->set('folder', 'trash')->assertSee('Fir-la-cos');

    // Cutia părintelui rămâne neatinsă.
    expect(Message::query()->whereKey($root->id)->notTrashedBy($parent->id)->exists())->toBeTrue();

    Livewire::test(Mailbox::class)->call('restoreThread', $root->id);
    Livewire::test(Mailbox::class)->set('folder', 'inbox')->assertSee('Fir-la-cos');
});

it('steaua e per-utilizator și umple folderul Cu stea', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);
    $root = app(SendMessage::class)->direct($parent, $teacher, 'Text.', 'Fir-cu-stea', $student);

    actingAs($teacher);

    Livewire::test(Mailbox::class)->call('toggleStar', $root->id);
    Livewire::test(Mailbox::class)->set('folder', 'starred')->assertSee('Fir-cu-stea');

    // Steaua profesorului nu apare la părinte.
    expect(MessageState::query()->where('message_id', $root->id)->where('user_id', $parent->id)->exists())->toBeFalse();

    Livewire::test(Mailbox::class)->call('toggleStar', $root->id);
    Livewire::test(Mailbox::class)->set('folder', 'starred')->assertDontSee('Fir-cu-stea');
});

// ─── Conversația: acces + citire ─────────────────────────────────────────────────────────

it('deschiderea unui fir străin e interzisă (403), inclusiv prin deep-link', function () {
    [$student, $class] = sbxStudentInClass();
    $root = app(SendMessage::class)->direct(sbxParentOf($student), sbxTeacherOf($class), 'Confidențial.', 'Nu-al-tau', $student);

    [, $otherClass] = sbxStudentInClass();
    $stranger = sbxTeacherOf($otherClass);

    actingAs($stranger);

    // Abort-ul din acțiune trece prin kernel (nu se propagă ca excepție în harness) —
    // dovada blocării = EFECTELE: firul nu se deschide și nu e marcat citit.
    Livewire::test(Mailbox::class)
        ->call('openThread', $root->id)
        ->assertSet('thread', null);
    expect($root->refresh()->read_at)->toBeNull();

    // Deep-link (?fir=id) — mount() autorizează înainte de a randa orice.
    actingAs($stranger)
        ->get(Mailbox::getUrl(['fir' => $root->id]))
        ->assertForbidden();
});

it('deschiderea firului îl marchează citit doar pentru mine; „necitit" îl readuce necitit', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    $root = app(SendMessage::class)->direct($parent, $teacher, 'Întrebare.', 'Fir-citire', $student);
    $reply = app(SendMessage::class)->reply($teacher, $root, 'Răspuns.');

    actingAs($teacher);

    Livewire::test(Mailbox::class)->call('openThread', $root->id)->assertSee('Întrebare.');

    // Primit de profesor → citit; trimis DE el → neatins (îl citește părintele).
    expect($root->refresh()->read_at)->not->toBeNull()
        ->and($reply->refresh()->read_at)->toBeNull();

    Livewire::test(Mailbox::class)->call('markUnread', $root->id);
    expect($root->refresh()->read_at)->toBeNull();
});

it('un deep-link către un RĂSPUNS deschide conversația întreagă (rădăcina)', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    $root = app(SendMessage::class)->direct($parent, $teacher, 'Prima întrebare.', 'Fir-deep', $student);
    $reply = app(SendMessage::class)->reply($teacher, $root, 'Primul răspuns.');

    actingAs($teacher)
        ->get(Mailbox::getUrl(['fir' => $reply->id]))
        ->assertOk()
        ->assertSee('Prima întrebare.');
});

// ─── Răspuns inline + compunere ──────────────────────────────────────────────────────────

it('răspunsul inline pleacă spre celălalt participant, iar compozitorul se golește complet', function () {
    Storage::fake('local');
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);
    $root = app(SendMessage::class)->direct($parent, $teacher, 'Întrebare.', 'Fir-reply', $student);

    actingAs($teacher);

    $page = Livewire::test(Mailbox::class)
        ->call('openThread', $root->id)
        ->assertSet('replyKey', 1) // deschiderea resetează compozitorul
        ->set('reply.body', 'Vă răspund cu document.')
        ->set('reply.files', [UploadedFile::fake()->create('nota.pdf', 30, 'application/pdf')])
        ->call('sendReply');

    $reply = Message::query()->where('parent_id', $root->id)->firstOrFail();
    expect($reply->recipient_user_id)->toBe($parent->id)
        ->and($reply->attachments()->count())->toBe(1);

    $page->assertSet('reply.body', null)
        ->assertSet('reply.files', [])
        ->assertSet('replyKey', 2);
});

it('răspunsul cu corp gol e respins', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);
    $root = app(SendMessage::class)->direct($parent, $teacher, 'Întrebare.', 'Fir-gol', $student);

    actingAs($teacher);

    Livewire::test(Mailbox::class)
        ->call('openThread', $root->id)
        ->set('reply.body', '')
        ->call('sendReply')
        ->assertHasErrors(['reply.body']);

    expect(Message::query()->where('parent_id', $root->id)->exists())->toBeFalse();
});

it('compunerea către un părinte (elev predat) pleacă și apare în cutia lui de cabinet', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    actingAs($teacher);

    Livewire::test(Mailbox::class)
        ->call('openCompose')
        ->set('compose.kind', 'parent')
        ->set('compose.parent_target', $student->id.':'.$parent->id)
        ->set('compose.subject', 'Compus-din-posta')
        ->set('compose.body', 'Vă informez.')
        ->call('sendCompose')
        ->assertSet('composeOpen', false);

    $message = Message::query()->where('subject', 'Compus-din-posta')->firstOrFail();
    expect($message->recipient_user_id)->toBe($parent->id)
        ->and($message->student_id)->toBe($student->id);

    // Sincronizare: firul apare în Trimise la profesor și în Primite la părinte (cabinet).
    expect(MessageMailbox::for($teacher)->folder('sent')->pluck('id')->all())->toContain($message->id)
        ->and(MessageMailbox::for($parent)->folder('inbox')->pluck('id')->all())->toContain($message->id);
});

it('compunerea către familia unui elev NEPREDAT e respinsă cu 403 (poarta serverului)', function () {
    [$mine, $myClass] = sbxStudentInClass();
    [$other] = sbxStudentInClass();
    $teacher = sbxTeacherOf($myClass);
    $strangerParent = sbxParentOf($other);
    sbxParentOf($mine);

    actingAs($teacher);

    // Poarta canSendDirect() răspunde 403 prin kernel; dovada blocării = mesajul NU există
    // și compunerea a rămas deschisă (nu s-a „expediat").
    Livewire::test(Mailbox::class)
        ->call('openCompose')
        ->set('compose.kind', 'parent')
        ->set('compose.parent_target', $other->id.':'.$strangerParent->id)
        ->set('compose.subject', 'Nepermis')
        ->set('compose.body', 'Text.')
        ->call('sendCompose')
        ->assertSet('composeOpen', true);

    expect(Message::query()->where('subject', 'Nepermis')->exists())->toBeFalse();
});

it('un atașament neterminat de încărcat blochează expedierea (nu dispare tăcut)', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);
    $root = app(SendMessage::class)->direct($parent, $teacher, 'Întrebare.', 'Fir-upload', $student);

    actingAs($teacher);

    Livewire::test(Mailbox::class)
        ->call('openThread', $root->id)
        ->set('reply.body', 'Cu fișier în curs.')
        ->set('reply.files', ['livewire-file:tmp-neterminat.pdf'])
        ->call('sendReply')
        ->assertHasErrors(['reply.files']);

    expect(Message::query()->where('parent_id', $root->id)->exists())->toBeFalse();
});

it('tipurile interzise (svg) sunt respinse din compunere — aceeași listă albă ca la cabinet', function () {
    Storage::fake('local');
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    expect(fn () => ComposeSchema::send($teacher, [
        'kind' => 'parent',
        'parent_target' => $student->id.':'.$parent->id,
        'subject' => 'XSS',
        'body' => 'Text.',
        'files' => [UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')],
    ]))->toThrow(ValidationException::class);

    expect(Message::query()->where('subject', 'XSS')->exists())->toBeFalse();
});

// ─── Sincronizarea celor două cutii ──────────────────────────────────────────────────────

it('mesajul trimis de părinte din cabinet apare în Primite la profesor, cu aceiași doi participanți pe fir', function () {
    [$student, $class] = sbxStudentInClass();
    $teacher = sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    actingAs($parent)->post(route('cabinet.messages.send'), [
        'type' => 'direct',
        'student_id' => $student->id,
        'recipient_user_id' => $teacher->id,
        'subject' => 'Din-cabinet',
        'body' => 'Bună ziua, o întrebare.',
    ])->assertRedirect();

    $root = Message::query()->where('subject', 'Din-cabinet')->firstOrFail();

    actingAs($teacher);
    Livewire::test(Mailbox::class)->set('folder', 'inbox')->assertSee('Din-cabinet');

    // Răspunsul profesorului din poștă ajunge înapoi la părinte, pe ACELAȘI fir.
    Livewire::test(Mailbox::class)
        ->call('openThread', $root->id)
        ->set('reply.body', 'Răspunsul meu.')
        ->call('sendReply');

    $reply = Message::query()->where('parent_id', $root->id)->firstOrFail();
    expect($reply->recipient_user_id)->toBe($parent->id)
        ->and($reply->student_id)->toBe($root->student_id);

    $participants = Message::query()
        ->where(fn ($q) => $q->whereKey($root->id)->orWhere('parent_id', $root->id))
        ->get()
        ->flatMap(fn (Message $m) => [$m->sender_user_id, $m->recipient_user_id])
        ->unique()->sort()->values()->all();

    expect($participants)->toBe(collect([$parent->id, $teacher->id])->sort()->values()->all());
});

it('folderul Audiențe apare doar când există audiențe și le listează', function () {
    [$student, $class] = sbxStudentInClass();
    sbxTeacherOf($class);
    $parent = sbxParentOf($student);

    $vice = User::factory()->create(['email_verified_at' => now()]);
    $vice->assignRole(UserRole::PrimVicedirector->value);

    actingAs($vice);
    Livewire::test(Mailbox::class)->assertDontSee(__('panel.mailbox.folders.audience'));

    app(SendMessage::class)->audience($parent, $student, 'Solicitare-audienta', 'Aș dori o discuție.', AudienceDomain::Educatie);

    Livewire::test(Mailbox::class)
        ->set('folder', 'audience')
        ->assertSee(__('panel.mailbox.folders.audience'))
        ->assertSee('Solicitare-audienta');
});
