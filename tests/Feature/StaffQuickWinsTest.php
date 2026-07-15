<?php

use App\Enums\CorrectionStatus;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\DocumentRequests\Pages\ListDocumentRequests;
use App\Filament\Resources\GradeCorrections\Pages\ListGradeCorrections;
use App\Filament\Resources\Grades\Pages\ListGrades;
use App\Jobs\RecomputeTermAverage;
use App\Models\AcademicYear;
use App\Models\DocumentRequest;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function staffWithRole(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('aprobă în masă doar corecțiile în așteptare', function () {
    $director = staffWithRole(UserRole::Director);

    $pendingOne = GradeCorrection::factory()->create(['old_value' => 5, 'new_value' => 9]);
    $pendingTwo = GradeCorrection::factory()->create(['old_value' => 4, 'new_value' => 8]);
    $alreadyRejected = GradeCorrection::factory()->create(['status' => CorrectionStatus::Rejected]);

    $this->actingAs($director);

    Livewire::test(ListGradeCorrections::class)
        ->callTableBulkAction('approveSelected', [$pendingOne, $pendingTwo, $alreadyRejected], [
            'review_note' => 'verificat',
        ])
        ->assertHasNoTableBulkActionErrors();

    expect($pendingOne->fresh()->status)->toBe(CorrectionStatus::Approved)
        ->and($pendingTwo->fresh()->status)->toBe(CorrectionStatus::Approved)
        ->and((float) $pendingOne->grade->fresh()->value)->toBe(9.0)
        // Cele deja respinse rămân neatinse de acțiunea în masă.
        ->and($alreadyRejected->fresh()->status)->toBe(CorrectionStatus::Rejected);
});

it('bara de instrumente bulk a corecțiilor e ascunsă profesorului fără drept de aprobare', function () {
    $profesor = staffWithRole(UserRole::Profesor);
    // Profesorul are fișă → poate VEDEA pagina (cererile lui), dar acțiunea de aprobare în masă
    // rămâne ascunsă (nu are drept de aprobare). Fără fișă, catalogul academic nu-i e vizibil deloc.
    Teacher::factory()->create(['user_id' => $profesor->id]);

    $this->actingAs($profesor);

    Livewire::test(ListGradeCorrections::class)
        ->assertTableBulkActionHidden('approveSelected');
});

it('marchează procesate în masă cererile de documente în așteptare', function () {
    $admin = staffWithRole(UserRole::Admin);

    $first = DocumentRequest::factory()->create();
    $second = DocumentRequest::factory()->create();

    $this->actingAs($admin);

    Livewire::test(ListDocumentRequests::class)
        ->callTableBulkAction('processSelected', [$first, $second])
        ->assertHasNoTableBulkActionErrors();

    expect($first->fresh()->status)->toBe(RequestStatus::Approved)
        ->and($second->fresh()->status)->toBe(RequestStatus::Approved);
});

it('salvarea unei note pune pe coadă recalculul mediei (nu blochează request-ul)', function () {
    Queue::fake();

    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 7]);

    $grade = Grade::factory()->create([
        'student_id' => Student::factory()->create()->id,
        'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $class->id,
        'term_id' => Term::factory()->for($year)->create()->id,
        'value' => 8,
    ]);

    Queue::assertPushed(
        RecomputeTermAverage::class,
        fn (RecomputeTermAverage $job): bool => $job->studentId === (int) $grade->student_id
            && $job->subjectId === (int) $grade->subject_id
            && $job->termId === (int) $grade->term_id,
    );
});

it('catalogul de note se restrânge pe clasă (contextul navigatorului) și se caută după elev', function () {
    // Adaptat la navigatorul de catalog (309af01): tabelul se randează ÎN contextul unei clase
    // (care ține loc de filtrul de clasă); căutarea funcționează în interiorul contextului.
    $year = AcademicYear::factory()->create();
    $classA = SchoolClass::factory()->for($year)->create(['grade_level' => 7]);
    $classB = SchoolClass::factory()->for($year)->create(['grade_level' => 7]);
    $term = Term::factory()->for($year)->create();
    $subject = Subject::factory()->create();

    $studentA = Student::factory()->create(['last_name' => 'Aaaaa', 'first_name' => 'Ana']);
    $studentB = Student::factory()->create(['last_name' => 'Bbbbb', 'first_name' => 'Bogdan']);

    $gradeA = Grade::factory()->create([
        'student_id' => $studentA->id,
        'subject_id' => $subject->id,
        'school_class_id' => $classA->id,
        'term_id' => $term->id,
        'value' => 8,
    ]);
    $gradeB = Grade::factory()->create([
        'student_id' => $studentB->id,
        'subject_id' => $subject->id,
        'school_class_id' => $classA->id,
        'term_id' => $term->id,
        'value' => 7,
    ]);
    $gradeOtherClass = Grade::factory()->create([
        'student_id' => $studentB->id,
        'subject_id' => $subject->id,
        'school_class_id' => $classB->id,
        'term_id' => $term->id,
        'value' => 6,
    ]);

    $this->actingAs(staffWithRole(UserRole::Admin));

    // Contextul clasei restrânge tabelul (nota altei clase nu apare).
    Livewire::withQueryParams(['clasa' => (string) $classA->id])
        ->test(ListGrades::class)
        ->assertCanSeeTableRecords([$gradeA, $gradeB])
        ->assertCanNotSeeTableRecords([$gradeOtherClass]);

    // Căutarea după numele elevului, în interiorul contextului.
    Livewire::withQueryParams(['clasa' => (string) $classA->id])
        ->test(ListGrades::class)
        ->searchTable('Aaaaa')
        ->assertCanSeeTableRecords([$gradeA])
        ->assertCanNotSeeTableRecords([$gradeB]);
});
