<?php

/**
 * DREPTURILE DE APROBARE din secțiunea „Aprobări" — pinuite explicit (2026-07-27).
 *
 * Context: beneficiarul a raportat că un cont etichetat „Profesor" validează motivări de absențe
 * și a suspectat o breșă. Verificarea a arătat altceva: contul demo era legat de fișa unei
 * DIRIGINTE, iar dreptul venea din desemnarea de dirigenție — comportament corect (spec §2.1),
 * dar invizibil în interfață. Testele de aici fixează granițele, ca diferența dintre „profesor
 * simplu", „diriginte" și „conducere" să nu mai poată aluneca tăcut:
 *
 *   - profesorul SIMPLU nu judecă nimic, nicăieri;
 *   - dirigintele judecă DOAR motivările clasei lui, DOAR pe cele normale (nu excepțiile);
 *   - corecțiile de notă/temă nu se aprobă de profesor sau diriginte, indiferent de dirigenție.
 */

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\AbsenceMotivations\AbsenceMotivationResource;
use App\Filament\Resources\AbsenceMotivations\Pages\ViewAbsenceMotivation;
use App\Models\AbsenceMotivation;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->classA = SchoolClass::factory()->for($this->year)->create(['name' => 'XI', 'section' => 'R', 'grade_level' => 11]);
    $this->classB = SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'section' => '2', 'grade_level' => 7]);
});

/** Cont de personal didactic: rolul dat + fișă proprie, opțional diriginte al unei clase. */
function approvalTeacher(string $role, ?SchoolClass $homeroom = null): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);

    if ($homeroom !== null) {
        $homeroom->update(['homeroom_teacher_id' => $teacher->id]);
    }

    return $user->fresh();
}

/** Cerere de motivare pentru un elev înmatriculat în clasa dată. */
function approvalMotivation(mixed $ctx, SchoolClass $class, bool $exception = false): AbsenceMotivation
{
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($ctx->year)->create([
        'enrolled_on' => '2025-09-01',
        'left_on' => null,
    ]);

    return AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'status' => RequestStatus::Pending,
        'is_exception' => $exception,
    ]);
}

it('profesorul SIMPLU (fără dirigenție) nu vede și nu judecă motivări de absențe', function () {
    $profesor = approvalTeacher(UserRole::Profesor->value);
    actingAs($profesor);

    $cerere = approvalMotivation($this, $this->classA);

    expect($profesor->homeroomSchoolClassIds())->toBe([])
        // Secțiunea nu i se deschide deloc...
        ->and(AbsenceMotivationResource::canAccess())->toBeFalse()
        // ...iar dreptul pe cerere lipsește, indiferent de UI.
        ->and($cerere->canBeReviewedBy($profesor))->toBeFalse();
});

it('DIRIGINTELE judecă motivările clasei lui, dar nu pe ale altei clase', function () {
    $diriginte = approvalTeacher(UserRole::Diriginte->value, $this->classA);
    actingAs($diriginte);

    $aClasei = approvalMotivation($this, $this->classA);
    $altaClasa = approvalMotivation($this, $this->classB);

    expect(AbsenceMotivationResource::canAccess())->toBeTrue()
        ->and($aClasei->canBeReviewedBy($diriginte))->toBeTrue()
        ->and($altaClasa->canBeReviewedBy($diriginte))->toBeFalse();

    // Scoping-ul listei: cererea altei clase nici nu apare.
    $vizibile = AbsenceMotivationResource::getEloquentQuery()->pluck('id');

    expect($vizibile)->toContain($aClasei->id)
        ->and($vizibile)->not->toContain($altaClasa->id);
});

it('EXCEPȚIA (motivare tardivă) iese din mâna dirigintelui, chiar pentru clasa lui', function () {
    $diriginte = approvalTeacher(UserRole::Diriginte->value, $this->classA);
    actingAs($diriginte);

    $exceptie = approvalMotivation($this, $this->classA, exception: true);

    expect($exceptie->canBeReviewedBy($diriginte))->toBeFalse();

    // Pe fișă nu apare niciun buton de decizie.
    Livewire::test(ViewAbsenceMotivation::class, ['record' => $exceptie->getRouteKey()])
        ->assertActionHidden('approve')
        ->assertActionHidden('reject');
});

it('un cont cu ROLUL „profesor" care e desemnat diriginte judecă legitim — dreptul vine din dirigenție', function () {
    // Exact cazul semnalat de beneficiar: eticheta spune „Profesor", fișa spune „diriginte".
    $profesorDiriginte = approvalTeacher(UserRole::Profesor->value, $this->classA);
    actingAs($profesorDiriginte);

    $cerere = approvalMotivation($this, $this->classA);

    expect($cerere->canBeReviewedBy($profesorDiriginte))->toBeTrue()
        // ...iar interfața o SPUNE, ca să nu pară o breșă.
        ->and($profesorDiriginte->homeroomLabel())->toBe('XI R');

    $capacitate = Livewire::test(ViewAbsenceMotivation::class, ['record' => $cerere->getRouteKey()])
        ->instance()
        ->judgeCapacity();

    expect($capacitate)->toContain('XI R');
});

it('conducerea judecă în virtutea funcției — fără mențiunea de dirigenție', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    actingAs($director);

    $cerere = approvalMotivation($this, $this->classA);

    expect($cerere->canBeReviewedBy($director))->toBeTrue();

    $capacitate = Livewire::test(ViewAbsenceMotivation::class, ['record' => $cerere->getRouteKey()])
        ->instance()
        ->judgeCapacity();

    expect($capacitate)->toBeNull();
});

it('NIMENI din corpul didactic nu aprobă corecții de notă sau de temă — nici dirigintele', function () {
    foreach ([
        'profesor simplu' => approvalTeacher(UserRole::Profesor->value),
        'diriginte' => approvalTeacher(UserRole::Diriginte->value, $this->classB),
    ] as $eticheta => $user) {
        expect($user->canApproveGradeCorrections())->toBeFalse("($eticheta) nu poate aproba corecții de notă")
            ->and($user->canApproveHomeworkCorrections())->toBeFalse("($eticheta) nu poate aproba corecții de temă");
    }

    // Aprobarea rămâne la conducere (temele includ și administratorul operațional — decizie §3.3).
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $operational = User::factory()->create();
    $operational->assignRole(UserRole::AdministratorOperational->value);

    expect($director->canApproveGradeCorrections())->toBeTrue()
        ->and($director->canApproveHomeworkCorrections())->toBeTrue()
        ->and($operational->canApproveGradeCorrections())->toBeFalse()
        ->and($operational->canApproveHomeworkCorrections())->toBeTrue();
});

it('eticheta de dirigenție e goală pentru cine nu are clase în coordonare', function () {
    $profesor = approvalTeacher(UserRole::Profesor->value);
    $parinte = User::factory()->create();
    $parinte->assignRole(UserRole::Parinte->value);

    expect($profesor->homeroomLabel())->toBeNull()
        ->and($parinte->homeroomLabel())->toBeNull();
});
