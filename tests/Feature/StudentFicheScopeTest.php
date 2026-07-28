<?php

use App\Enums\UserRole;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\AbsencesRelationManager;
use App\Filament\Resources\Students\RelationManagers\AcademicRecordsRelationManager;
use App\Filament\Resources\Students\RelationManagers\GradesRelationManager;
use App\Models\Absence;
use App\Models\AcademicRecord;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\TermAverage;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * FIȘA ELEVULUI respectă calitatea privitorului.
 *
 * De ce un test separat de TeacherScopingTest: acela verifică `GradeResource::getEloquentQuery()`
 * — care era corect — și tocmai de aceea n-a prins scurgerea. Fișa elevului nu trece prin resursă:
 * relation manager-ul interoghează relația `$student->grades`, care restrânge la ELEV, nu la
 * privitor. Testele de aici lovesc ECRANUL, nu interogarea resursei; altfel regresia se poate
 * întoarce fără ca suita să clipească.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $year = AcademicYear::factory()->create();
    $this->term = Term::factory()->for($year)->create(['is_current' => true]);
    $this->class = SchoolClass::factory()->for($year)->create();

    $this->romana = Subject::factory()->create(['name' => 'Limba și literatura română']);
    $this->fizica = Subject::factory()->create(['name' => 'Fizică']);

    $this->student = Student::factory()->create();
    Enrollment::factory()->create([
        'student_id' => $this->student->id,
        'school_class_id' => $this->class->id,
        'academic_year_id' => $year->id,
    ]);

    // Profesorul de română: predă DOAR româna, la această clasă. Nu e diriginte nicăieri.
    $this->profesor = User::factory()->create();
    $this->profesor->assignRole(UserRole::Profesor->value);
    $teacherRomana = Teacher::factory()->create(['user_id' => $this->profesor->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacherRomana->id,
        'subject_id' => $this->romana->id,
        'school_class_id' => $this->class->id,
    ]);

    // Dirigintele clasei: NU predă nimic acolo — dreptul lui vine din desemnare.
    $this->diriginte = User::factory()->create();
    $this->diriginte->assignRole(UserRole::Diriginte->value);
    $teacherDiriginte = Teacher::factory()->create(['user_id' => $this->diriginte->id]);
    $this->class->update(['homeroom_teacher_id' => $teacherDiriginte->id]);

    // Profesorul de fizică — autorul datelor pe care româna NU are voie să le vadă.
    $teacherFizica = Teacher::factory()->create();
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacherFizica->id,
        'subject_id' => $this->fizica->id,
        'school_class_id' => $this->class->id,
    ]);

    $common = [
        'student_id' => $this->student->id,
        'school_class_id' => $this->class->id,
        'term_id' => $this->term->id,
    ];

    $this->notaRomana = Grade::factory()->create([...$common, 'subject_id' => $this->romana->id, 'teacher_id' => $teacherRomana->id]);
    $this->notaFizica = Grade::factory()->create([...$common, 'subject_id' => $this->fizica->id, 'teacher_id' => $teacherFizica->id]);

    $this->absentaRomana = Absence::factory()->create([...$common, 'subject_id' => $this->romana->id, 'teacher_id' => $teacherRomana->id]);
    $this->absentaFizica = Absence::factory()->create([...$common, 'subject_id' => $this->fizica->id, 'teacher_id' => $teacherFizica->id]);

    $this->mediaRomana = TermAverage::factory()->create([...$common, 'subject_id' => $this->romana->id]);
    $this->mediaFizica = TermAverage::factory()->create([...$common, 'subject_id' => $this->fizica->id]);

    $this->matricolaRomana = AcademicRecord::factory()->create(['student_id' => $this->student->id, 'subject_id' => $this->romana->id]);
    $this->matricolaFizica = AcademicRecord::factory()->create(['student_id' => $this->student->id, 'subject_id' => $this->fizica->id]);
});

/** Deschide un relation manager de pe fișa elevului, ca utilizatorul dat. */
function ficheTable(User $as, string $relationManager): Testable
{
    test()->actingAs($as);

    return Livewire::test($relationManager, [
        'ownerRecord' => test()->student,
        'pageClass' => ViewStudent::class,
    ]);
}

it('profesorul NU vede pe fișă notele de la disciplinele altora', function () {
    ficheTable($this->profesor, GradesRelationManager::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->notaRomana])
        ->assertCanNotSeeTableRecords([$this->notaFizica]);
});

it('dirigintele vede pe fișă notele de la TOATE disciplinele clasei lui', function () {
    ficheTable($this->diriginte, GradesRelationManager::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->notaRomana, $this->notaFizica]);
});

it('profesorul NU vede pe fișă absențele de la disciplinele altora', function () {
    ficheTable($this->profesor, AbsencesRelationManager::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->absentaRomana])
        ->assertCanNotSeeTableRecords([$this->absentaFizica]);
});

it('dirigintele vede pe fișă toate absențele clasei lui', function () {
    ficheTable($this->diriginte, AbsencesRelationManager::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->absentaRomana, $this->absentaFizica]);
});

it('profesorul NU vede din foaia matricolă disciplinele pe care nu le predă', function () {
    ficheTable($this->profesor, AcademicRecordsRelationManager::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->matricolaRomana])
        ->assertCanNotSeeTableRecords([$this->matricolaFizica]);
});

it('dirigintele vede foaia matricolă întreagă a elevului său', function () {
    ficheTable($this->diriginte, AcademicRecordsRelationManager::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->matricolaRomana, $this->matricolaFizica]);
});

it('cardul de medii arată doar disciplinele profesorului, dar toate dirigintelui', function () {
    $this->actingAs($this->profesor);
    $vizibile = TermAverage::query()->visibleToStaff($this->profesor)
        ->where('student_id', $this->student->id)->pluck('subject_id');
    expect($vizibile)->toContain($this->romana->id)->not->toContain($this->fizica->id);

    $this->actingAs($this->diriginte);
    $vizibile = TermAverage::query()->visibleToStaff($this->diriginte)
        ->where('student_id', $this->student->id)->pluck('subject_id');
    expect($vizibile)->toContain($this->romana->id)->toContain($this->fizica->id);
});

it('administrația vede tot, iar un cont fără fișă de profesor nu vede nimic', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    expect(Grade::query()->visibleToStaff($director)->count())->toBe(2);

    // Administratorul TEHNIC nu e „administrator" de catalog și n-are fișă → zero acces la note.
    $tehnic = User::factory()->create();
    $tehnic->assignRole(UserRole::AdministratorTehnic->value);
    expect(Grade::query()->visibleToStaff($tehnic)->count())->toBe(0);
});
