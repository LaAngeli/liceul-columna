<?php

use App\Actions\SendMessage;
use App\Enums\AudienceDomain;
use App\Enums\MessageType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Message;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function studentInClass(): array
{
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    return [$student, $class];
}

function parentOf(Student $student): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Parinte->value);
    $user->students()->attach($student->id);

    return $user;
}

function teacherTeaching(SchoolClass $class, UserRole $role = UserRole::Profesor): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);

    if ($role === UserRole::Diriginte) {
        $class->update(['homeroom_teacher_id' => $teacher->id]);
    } else {
        TeachingAssignment::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => Subject::factory()->create()->id,
            'school_class_id' => $class->id,
        ]);
    }

    return $user;
}

it('familia poate scrie profesorului și dirigintelui copilului (direct)', function () {
    [$student, $class] = studentInClass();
    $parent = parentOf($student);
    $prof = teacherTeaching($class);
    $diriginte = teacherTeaching($class, UserRole::Diriginte);

    $send = app(SendMessage::class);

    expect($send->canSendDirect($parent, $prof, $student))->toBeTrue()
        ->and($send->canSendDirect($parent, $diriginte, $student))->toBeTrue();

    $message = $send->direct($parent, $prof, 'Bună ziua, o întrebare despre temă.', 'Temă', $student);

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->type)->toBe(MessageType::Direct)
        ->and($message->recipient_user_id)->toBe($prof->id);
});

it('familia NU poate scrie unui profesor care nu predă copilului', function () {
    [$student, $class] = studentInClass();
    [, $otherClass] = studentInClass();
    $parent = parentOf($student);
    $stranger = teacherTeaching($otherClass);

    expect(app(SendMessage::class)->canSendDirect($parent, $stranger, $student))->toBeFalse();
});

it('familia NU poate scrie direct conducerii (canal nepermis → 403)', function () {
    [$student, $class] = studentInClass();
    teacherTeaching($class);
    $parent = parentOf($student);
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    expect(app(SendMessage::class)->canSendDirect($parent, $director, $student))->toBeFalse();

    app(SendMessage::class)->direct($parent, $director, 'Vreau să discut.', null, $student);
})->throws(HttpException::class);

it('solicitarea de audiență e rutată către responsabilul de DOMENIU (atribut, nu rol)', function () {
    [$student] = studentInClass();
    $parent = parentOf($student);

    // Cont de conducere desemnat pe domeniul „instruire" prin atribut (fără rol nou).
    $vice = User::factory()->create(['audience_domains' => [AudienceDomain::Instruire->value]]);
    $vice->assignRole(UserRole::PrimVicedirector->value);

    $message = app(SendMessage::class)
        ->audience($parent, $student, 'Solicitare audiență', 'Aș dori o întâlnire.', AudienceDomain::Instruire);

    expect($message->type)->toBe(MessageType::Audience)
        ->and($message->audience_domain)->toBe(AudienceDomain::Instruire)
        ->and($message->recipient_user_id)->toBe($vice->id)
        ->and($message->student_id)->toBe($student->id);
});

it('audiența cade pe director dacă domeniul nu are responsabil atribuit', function () {
    [$student] = studentInClass();
    $parent = parentOf($student);
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $message = app(SendMessage::class)
        ->audience($parent, $student, 'Solicitare', 'Text.', AudienceDomain::Educatie);

    expect($message->recipient_user_id)->toBe($director->id)
        ->and($message->audience_domain)->toBe(AudienceDomain::Educatie);
});

it('profesorul poate scrie familiei unui elev pe care îl predă, dar nu altei familii', function () {
    [$student, $class] = studentInClass();
    [$otherStudent] = studentInClass();
    $parent = parentOf($student);
    $otherParent = parentOf($otherStudent);
    $prof = teacherTeaching($class);

    $send = app(SendMessage::class);

    expect($send->canSendDirect($prof, $parent, $student))->toBeTrue()
        ->and($send->canSendDirect($prof, $otherParent, $otherStudent))->toBeFalse();
});

it('familia nu poate scrie altei familii', function () {
    [$student] = studentInClass();
    [$other] = studentInClass();
    $parent = parentOf($student);
    $otherParent = parentOf($other);

    expect(app(SendMessage::class)->canSendDirect($parent, $otherParent, $student))->toBeFalse();
});

it('personalul poate comunica intern (profesor → prim-vicedirector)', function () {
    [, $class] = studentInClass();
    $prof = teacherTeaching($class);
    $primVice = User::factory()->create();
    $primVice->assignRole(UserRole::PrimVicedirector->value);

    expect(app(SendMessage::class)->canSendDirect($prof, $primVice, null))->toBeTrue();
});

it('lista destinatarilor permiși conține profesorii și dirigintele copilului', function () {
    [$student, $class] = studentInClass();
    $prof = teacherTeaching($class);
    $diriginte = teacherTeaching($class, UserRole::Diriginte);

    $recipients = app(SendMessage::class)->allowedRecipientsForStudent($student);
    $ids = array_column($recipients, 'id');
    $roles = collect($recipients)->keyBy('id');

    expect($ids)->toContain($prof->id, $diriginte->id)
        ->and($roles[$diriginte->id]['role'])->toBe('diriginte')
        ->and($roles[$prof->id]['role'])->toBe('profesor');
});

it('ruta de trimitere din cabinet respectă ierarhia (HTTP)', function () {
    [$student, $class] = studentInClass();
    $parent = parentOf($student);
    $prof = teacherTeaching($class);

    $this->actingAs($parent)->post(route('cabinet.messages.send'), [
        'type' => 'direct',
        'student_id' => $student->id,
        'recipient_user_id' => $prof->id,
        'body' => 'Bună ziua.',
    ])->assertRedirect();

    expect(Message::query()->where('sender_user_id', $parent->id)->where('recipient_user_id', $prof->id)->exists())
        ->toBeTrue();
});

it('ruta de trimitere blochează canalele nepermise (HTTP 403)', function () {
    [$student, $class] = studentInClass();
    teacherTeaching($class);
    $parent = parentOf($student);
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $this->actingAs($parent)->post(route('cabinet.messages.send'), [
        'type' => 'direct',
        'student_id' => $student->id,
        'recipient_user_id' => $director->id,
        'body' => 'Salut.',
    ])->assertForbidden();
});

it('inboxul cabinetului se randează cu firele și contextul de compunere', function () {
    [$student, $class] = studentInClass();
    $parent = parentOf($student);
    $prof = teacherTeaching($class);
    app(SendMessage::class)->direct($parent, $prof, 'test', 'Subiect', $student);

    $this->actingAs($parent)->get(route('cabinet.messages'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/messages')
            ->has('threads', 1)
            ->has('compose.students'));
});

it('ruta de cabinet trimite o audiență pe domeniu (HTTP)', function () {
    [$student] = studentInClass();
    $parent = parentOf($student);
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $this->actingAs($parent)->post(route('cabinet.messages.send'), [
        'type' => 'audience',
        'student_id' => $student->id,
        'domain' => AudienceDomain::Educatie->value,
        'subject' => 'Audiență',
        'body' => 'Aș dori o discuție.',
    ])->assertRedirect();

    expect(Message::query()
        ->where('type', MessageType::Audience)
        ->where('audience_domain', AudienceDomain::Educatie->value)
        ->exists())->toBeTrue();
});

it('audiența din cabinet cere un domeniu (eroare de validare fără el)', function () {
    [$student] = studentInClass();
    $parent = parentOf($student);

    $this->actingAs($parent)->post(route('cabinet.messages.send'), [
        'type' => 'audience',
        'student_id' => $student->id,
        'body' => 'Fără domeniu.',
    ])->assertSessionHasErrors('domain');
});
