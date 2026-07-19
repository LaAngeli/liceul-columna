<?php

/**
 * Mesajul comportamental FILTRAT (spec §4.2): profesorul/dirigintele elevului semnalează
 * comportamentul → mesajul merge la PRIM-VICEDIRECTOR (moderare), nu direct la familie;
 * conducerea decide ce transmite părinților (prin compose-ul obișnuit).
 */

use App\Actions\SendMessage;
use App\Enums\MessageType;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Message;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    $this->class = SchoolClass::factory()->for($this->year)->create();
    $this->student = Student::factory()->create();
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create();
});

function behaviorTeacher(SchoolClass $class): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => $class->id,
        'subject_id' => Subject::factory()->create()->id,
    ]);

    return $user;
}

function primVicedirector(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole(UserRole::PrimVicedirector->value);

    return $user;
}

it('semnalarea merge la PRIM-VICEDIRECTOR, nu la familie, și îl notifică', function () {
    $deputy = primVicedirector();
    $teacher = behaviorTeacher($this->class);

    // Familia există — ca să dovedim că mesajul NU ajunge la ea.
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($this->student->id);

    Notification::fake();

    $message = app(SendMessage::class)->behavioralReport($teacher, $this->student, 'A întrerupt repetat ora și a refuzat sarcinile.');

    expect($message->type)->toBe(MessageType::Behavioral)
        ->and($message->recipient_user_id)->toBe($deputy->id)
        ->and($message->student_id)->toBe($this->student->id)
        ->and($message->subject)->toContain($this->student->full_name);

    // Destinatarul e conducerea — NU părintele (filtrarea e chiar sensul fluxului).
    Notification::assertSentTo($deputy, CatalogNotification::class, fn (CatalogNotification $n): bool => $n->type === NotificationType::NewMessage);
    Notification::assertNotSentTo($parent, CatalogNotification::class);
});

it('responsabilul domeniului EDUCAȚIE primează; fără conducere semnalarea e respinsă cu 422', function () {
    $deputy = primVicedirector();

    // Responsabilul de domeniu trebuie să aibă și ROLUL care-l poate exercita: desemnarea singură,
    // rămasă într-o coloană, nu face pe nimeni destinatar legitim al unei semnalări despre un MINOR
    // (reziduul de privilegiu reparat în LOT 2 — vezi RoleResidualPrivilegeTest).
    $handler = User::factory()->create(['audience_domains' => ['educatie']]);
    $handler->assignRole(UserRole::Director->value);

    $message = app(SendMessage::class)->behavioralReport(behaviorTeacher($this->class), $this->student, 'Situație raportabilă.');

    expect($message->recipient_user_id)->toBe($handler->id)
        ->and($message->recipient_user_id)->not->toBe($deputy->id);

    // Fără NICIO țintă de rutare → 422 (nu un mesaj rătăcit).
    $handler->delete();
    $deputy->delete();

    expect(fn () => app(SendMessage::class)->behavioralReport(behaviorTeacher($this->class), $this->student, 'X'))
        ->toThrow(HttpException::class);
});

it('profesorul STRĂIN de elev nu poate semnala (403); familia nici atât', function () {
    primVicedirector();

    $strangerTeacher = behaviorTeacher(SchoolClass::factory()->for($this->year)->create());

    expect(fn () => app(SendMessage::class)->behavioralReport($strangerTeacher, $this->student, 'X'))
        ->toThrow(HttpException::class);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($this->student->id);

    expect(fn () => app(SendMessage::class)->behavioralReport($parent, $this->student, 'X'))
        ->toThrow(HttpException::class);
});

it('acțiunea de pe fișa elevului: vizibilă profesorului lui, ascunsă administrației fără fișă didactică; trimite mesajul', function () {
    $deputy = primVicedirector();
    $teacher = behaviorTeacher($this->class);

    actingAs($teacher);
    Livewire::test(ViewStudent::class, ['record' => $this->student->id])
        ->assertActionVisible('reportBehavior')
        ->callAction('reportBehavior', ['body' => 'A absentat nemotivat și a perturbat ora de curs.'])
        ->assertHasNoActionErrors();

    $message = Message::query()->where('type', MessageType::Behavioral)->firstOrFail();
    expect($message->recipient_user_id)->toBe($deputy->id)
        ->and($message->sender_user_id)->toBe($teacher->id);

    // Directorul (fără fișă de cadru didactic) nu are butonul — el PRIMEȘTE semnalările.
    $director = User::factory()->create(['email_verified_at' => now()]);
    $director->assignRole(UserRole::Director->value);
    actingAs($director);
    Livewire::test(ViewStudent::class, ['record' => $this->student->id])
        ->assertActionHidden('reportBehavior');
});
