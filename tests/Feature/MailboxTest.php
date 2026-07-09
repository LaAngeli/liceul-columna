<?php

use App\Actions\SendMessage;
use App\Actions\StoreMessageAttachments;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageState;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    // Randarea paginii /cabinet/mesaje include @vite; fără manifest ar arunca ViteException.
    $this->withoutVite();
});

/**
 * Un părinte (familie) + un profesor care predă copilului lui. Firul de mesaje al cabinetului e
 * mereu între familie și un cadru — de-asta „celălalt participant" e profesorul (staff).
 *
 * @return array{0: User, 1: User, 2: Student}
 */
function mailboxParties(): array
{
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    $teacherUser = User::factory()->create();
    $teacherUser->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $class->id,
    ]);

    return [$parent, $teacherUser, $student];
}

it('firul inițiat de mine apare în „Toate" (direcția sent) și NU în „Necitite"', function () {
    [$parent, $teacher, $student] = mailboxParties();
    app(SendMessage::class)->direct($parent, $teacher, 'Bună ziua.', 'Întrebare', $student);

    $this->actingAs($parent)->get(route('cabinet.messages', ['folder' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->has('threads', 1)->where('threads.0.direction', 'sent'));

    // Un fir pe care l-am inițiat (fără răspuns) nu are mesaje necitite pentru mine.
    $this->actingAs($parent)->get(route('cabinet.messages', ['folder' => 'unread']))
        ->assertInertia(fn (Assert $page) => $page->has('threads', 0));
});

it('firul primit apare în „Toate" (direcția received) și în „Necitite"', function () {
    [$parent, $teacher, $student] = mailboxParties();
    // Profesorul inițiază → pentru părinte firul e „primit" (received).
    app(SendMessage::class)->direct($teacher, $parent, 'O informare.', 'Anunț', $student);

    $this->actingAs($parent)->get(route('cabinet.messages', ['folder' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('threads', 1)
            ->where('threads.0.direction', 'received')
            ->where('counts.unread.total', 1));

    $this->actingAs($parent)->get(route('cabinet.messages', ['folder' => 'unread']))
        ->assertInertia(fn (Assert $page) => $page->has('threads', 1));
});

it('marcarea cu stea e per-utilizator și apare în folderul „Preferate"', function () {
    [$parent, $teacher, $student] = mailboxParties();
    $message = app(SendMessage::class)->direct($parent, $teacher, 'Text.', 'Subiect', $student);

    $this->actingAs($parent)->post(route('cabinet.messages.star', $message))->assertRedirect();

    // Starea e a PĂRINTELUI (nu a profesorului) și marchează firul preferat.
    expect(MessageState::query()->where('message_id', $message->id)->where('user_id', $parent->id)->whereNotNull('starred_at')->exists())->toBeTrue()
        ->and(MessageState::query()->where('message_id', $message->id)->where('user_id', $teacher->id)->exists())->toBeFalse();

    $this->actingAs($parent)->get(route('cabinet.messages', ['folder' => 'starred']))
        ->assertInertia(fn (Assert $page) => $page->has('threads', 1)->where('threads.0.starred', true));

    // A doua apăsare scoate din preferate.
    $this->actingAs($parent)->post(route('cabinet.messages.star', $message))->assertRedirect();
    expect(MessageState::query()->where('message_id', $message->id)->where('user_id', $parent->id)->whereNotNull('starred_at')->exists())->toBeFalse();
});

it('arhiva e per-utilizator: mutarea scoate firul din „Toate", îl pune în „Arhivă", fără să șteargă mesajul', function () {
    [$parent, $teacher, $student] = mailboxParties();
    $message = app(SendMessage::class)->direct($parent, $teacher, 'Text.', 'Subiect', $student);

    $this->actingAs($parent)->post(route('cabinet.messages.trash', $message))->assertRedirect();

    // Mesajul NU e șters global (profesorul îl vede în continuare în panou) — doar starea părintelui.
    expect(Message::query()->whereKey($message->id)->exists())->toBeTrue()
        ->and(MessageState::query()->where('message_id', $message->id)->where('user_id', $parent->id)->whereNotNull('trashed_at')->exists())->toBeTrue();

    $this->actingAs($parent)->get(route('cabinet.messages', ['folder' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->has('threads', 0));
    $this->actingAs($parent)->get(route('cabinet.messages', ['folder' => 'trash']))
        ->assertInertia(fn (Assert $page) => $page->has('threads', 1)->where('threads.0.trashed', true));
});

it('restaurarea readuce firul din arhivă înapoi în „Toate"', function () {
    [$parent, $teacher, $student] = mailboxParties();
    $message = app(SendMessage::class)->direct($parent, $teacher, 'Text.', 'Subiect', $student);

    $this->actingAs($parent)->post(route('cabinet.messages.trash', $message))->assertRedirect();
    $this->actingAs($parent)->post(route('cabinet.messages.restore', $message))->assertRedirect();

    $this->actingAs($parent)->get(route('cabinet.messages', ['folder' => 'trash']))
        ->assertInertia(fn (Assert $page) => $page->has('threads', 0));
    $this->actingAs($parent)->get(route('cabinet.messages', ['folder' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->has('threads', 1));
});

it('nu poți acționa asupra unui fir din care nu faci parte (403)', function () {
    [$parent] = mailboxParties();

    // Fir între alți doi utilizatori — părintele nu e participant.
    $outsiderThread = Message::factory()->create([
        'sender_user_id' => User::factory()->create()->id,
        'recipient_user_id' => User::factory()->create()->id,
    ]);

    $this->actingAs($parent)->post(route('cabinet.messages.star', $outsiderThread))->assertForbidden();
    $this->actingAs($parent)->post(route('cabinet.messages.trash', $outsiderThread))->assertForbidden();
});

it('acțiunea de stea/coș pe un RĂSPUNS se aplică întregului fir (rădăcina)', function () {
    [$parent, $teacher, $student] = mailboxParties();
    $root = app(SendMessage::class)->direct($parent, $teacher, 'Prima.', 'Subiect', $student);
    $reply = app(SendMessage::class)->reply($teacher, $root, 'Răspuns.');

    // Acționăm pe id-ul RĂSPUNSULUI → starea trebuie să cadă pe rădăcina firului.
    $this->actingAs($parent)->post(route('cabinet.messages.star', $reply))->assertRedirect();

    expect(MessageState::query()->where('message_id', $root->id)->where('user_id', $parent->id)->whereNotNull('starred_at')->exists())->toBeTrue();
});

// === Atașamente ===

it('atașează un fișier la un mesaj trimis și îl stochează pe discul privat', function () {
    Storage::fake('local');
    [$parent, $teacher, $student] = mailboxParties();

    $this->actingAs($parent)->post(route('cabinet.messages.send'), [
        'type' => 'direct',
        'student_id' => $student->id,
        'recipient_user_id' => $teacher->id,
        'subject' => 'Cu fișier',
        'body' => 'Vezi atașamentul.',
        'files' => [UploadedFile::fake()->create('document.pdf', 200, 'application/pdf')],
    ])->assertRedirect();

    $attachment = MessageAttachment::query()->firstOrFail();
    expect($attachment->original_name)->toBe('document.pdf')
        ->and($attachment->disk)->toBe('local')
        ->and(Storage::disk('local')->exists($attachment->path))->toBeTrue();
});

it('descărcarea e permisă participantului și interzisă altora (403)', function () {
    Storage::fake('local');
    [$parent, $teacher, $student] = mailboxParties();
    $message = app(SendMessage::class)->direct($parent, $teacher, 'Text.', 'Subj', $student);
    app(StoreMessageAttachments::class)->handle($message, [UploadedFile::fake()->create('f.pdf', 50, 'application/pdf')]);
    $att = MessageAttachment::query()->firstOrFail();

    $this->actingAs($parent)->get(route('cabinet.messages.attachment', $att))->assertOk();

    $outsider = User::factory()->create();
    $this->actingAs($outsider)->get(route('cabinet.messages.attachment', $att))->assertForbidden();
});

it('respinge un fișier peste limita de mărime (8 MB)', function () {
    Storage::fake('local');
    [$parent, $teacher, $student] = mailboxParties();

    $this->actingAs($parent)->post(route('cabinet.messages.send'), [
        'type' => 'direct',
        'student_id' => $student->id,
        'recipient_user_id' => $teacher->id,
        'subject' => 'Mare',
        'body' => 'x',
        'files' => [UploadedFile::fake()->create('big.pdf', 9000, 'application/pdf')], // 9000 KB > 8192
    ])->assertSessionHasErrors('files.0');

    expect(MessageAttachment::query()->count())->toBe(0);
});

it('respinge un tip de fișier nepermis (svg — risc XSS la servire inline)', function () {
    Storage::fake('local');
    [$parent, $teacher, $student] = mailboxParties();

    $this->actingAs($parent)->post(route('cabinet.messages.send'), [
        'type' => 'direct',
        'student_id' => $student->id,
        'recipient_user_id' => $teacher->id,
        'subject' => 'Svg',
        'body' => 'x',
        'files' => [UploadedFile::fake()->create('vector.svg', 10, 'image/svg+xml')],
    ])->assertSessionHasErrors('files.0');
});

it('atașează un fișier și la un răspuns în fir', function () {
    Storage::fake('local');
    [$parent, $teacher, $student] = mailboxParties();
    $root = app(SendMessage::class)->direct($parent, $teacher, 'Prima.', 'Subj', $student);

    $this->actingAs($parent)->post(route('cabinet.messages.reply', $root), [
        'body' => 'Răspuns cu poză.',
        'files' => [UploadedFile::fake()->image('poza.jpg')],
    ])->assertRedirect();

    expect(MessageAttachment::query()->where('mime', 'like', 'image/%')->exists())->toBeTrue();
});
