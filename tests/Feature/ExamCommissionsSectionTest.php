<?php

/**
 * Comisiile de examen ca navigator de ACOPERIRE: barometrul (discipline cu examene vs. acoperite),
 * coada „de acoperit" cu creare pre-completată, componența nominală cu stările care cer atenție
 * (fără președinte / sub 3 persoane), garda „președintele nu e și membru" și curățarea [DEMO].
 */

use App\Enums\CorigentaSeason;
use App\Enums\CorigentaSessionStatus;
use App\Enums\CorigentaSessionType;
use App\Enums\UserRole;
use App\Filament\Resources\ExamCommissions\Pages\CreateExamCommission;
use App\Filament\Resources\ExamCommissions\Pages\ListExamCommissions;
use App\Models\AcademicYear;
use App\Models\CorigentaExam;
use App\Models\CorigentaSession;
use App\Models\ExamCommission;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    $this->term = Term::factory()->for($this->year)->create(['is_current' => true]);

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    actingAs($director);
});

function commissionTeacher(string $last, string $first): Teacher
{
    return Teacher::factory()->create(['last_name' => $last, 'first_name' => $first]);
}

it('barometrul și coada „de acoperit": disciplina cu examene fără comisie iese la suprafață', function () {
    $covered = Subject::factory()->create(['name' => 'Matematică']);
    $uncovered = Subject::factory()->create(['name' => 'Fizică']);

    $president = commissionTeacher('Popescu', 'Ana');
    $memberOne = commissionTeacher('Ionescu', 'Dan');
    $memberTwo = commissionTeacher('Rusu', 'Elena');

    $commission = ExamCommission::query()->create([
        'academic_year_id' => $this->year->id,
        'subject_id' => $covered->id,
        'name' => 'Comisia de matematică',
        'president_teacher_id' => $president->id,
    ]);
    $commission->members()->sync([$memberOne->id, $memberTwo->id]);

    // Un examen ALOCAT comisiei + unul pe disciplina neacoperită (fără comisie).
    CorigentaExam::query()->create([
        'student_id' => Student::factory()->create()->id,
        'subject_id' => $covered->id,
        'term_id' => $this->term->id,
        'season' => CorigentaSeason::cases()[0]->value,
        'exam_commission_id' => $commission->id,
    ]);
    CorigentaExam::query()->create([
        'student_id' => Student::factory()->create()->id,
        'subject_id' => $uncovered->id,
        'term_id' => $this->term->id,
        'season' => CorigentaSeason::cases()[0]->value,
    ]);

    Livewire::test(ListExamCommissions::class)
        // Coada „de acoperit" poartă disciplina lipsă + îndemnul de creare.
        ->assertSee(__('panel.exam_commissions.to_cover'))
        ->assertSee('Fizică')
        ->assertSee(__('panel.exam_commissions.create_commission'))
        // Componența nominală: președintele cu NUMELE COMPLET, membrii la fel.
        ->assertSee('Popescu Ana')
        ->assertSee('Ionescu Dan')
        // Examenele alocate comisiei se numără pe card.
        ->assertSee(trans_choice('panel.exam_commissions.exams_assigned', 1, ['count' => 1]))
        // Barometrul semnalează și examenul rămas fără comisie atribuită.
        ->assertSee(trans_choice('panel.exam_commissions.unassigned_exams', 1, ['count' => 1]));
});

it('comisia incompletă e marcată: fără președinte și sub 3 persoane', function () {
    $subject = Subject::factory()->create(['name' => 'Chimie']);

    $commission = ExamCommission::query()->create([
        'academic_year_id' => $this->year->id,
        'subject_id' => $subject->id,
        'name' => 'Comisia de chimie',
        'president_teacher_id' => null,
    ]);
    $commission->members()->sync([commissionTeacher('Munteanu', 'Ion')->id]);

    Livewire::test(ListExamCommissions::class)
        ->assertSee(__('panel.exam_commissions.no_president'))
        ->assertSee(__('panel.exam_commissions.thin', ['count' => 1]));
});

it('crearea din coada „de acoperit" vine pre-completată cu anul și disciplina', function () {
    $subject = Subject::factory()->create(['name' => 'Biologie']);

    Livewire::withQueryParams(['an' => $this->year->id, 'disciplina' => $subject->id])
        ->test(CreateExamCommission::class)
        ->assertSchemaStateSet([
            'academic_year_id' => $this->year->id,
            'subject_id' => $subject->id,
        ]);
});

it('președintele ales și ca membru e scos dintre membri la salvare (gardă de server)', function () {
    $subject = Subject::factory()->create(['name' => 'Istorie']);
    $president = commissionTeacher('Toma', 'Vasile');
    $member = commissionTeacher('Lungu', 'Maria');

    Livewire::test(CreateExamCommission::class)
        ->fillForm([
            'academic_year_id' => $this->year->id,
            'subject_id' => $subject->id,
            'name' => 'Comisia de istorie',
            'president_teacher_id' => $president->id,
            'members' => [$president->id, $member->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $commission = ExamCommission::query()->where('name', 'Comisia de istorie')->sole();

    expect($commission->president_teacher_id)->toBe($president->id)
        ->and($commission->members()->pluck('teachers.id')->all())->toBe([$member->id]);
});

it('app:purge-demo-data curăță comisiile, sesiunile și examenele [DEMO], păstrând realele', function () {
    $subject = Subject::factory()->create(['name' => 'Geografie']);
    $student = Student::factory()->create();
    $realStudent = Student::factory()->create();

    $demoCommission = ExamCommission::query()->create([
        'academic_year_id' => $this->year->id,
        'subject_id' => $subject->id,
        'name' => '[DEMO] Comisia de geografie',
    ]);
    $demoSession = CorigentaSession::query()->create([
        'academic_year_id' => $this->year->id,
        'season' => CorigentaSeason::cases()[0]->value,
        'type' => CorigentaSessionType::cases()[0]->value,
        'starts_on' => now()->addDays(5)->toDateString(),
        'ends_on' => now()->addDays(9)->toDateString(),
        'status' => CorigentaSessionStatus::Published->value,
        'order_reference' => '[DEMO] Sesiune comisii',
    ]);
    CorigentaExam::query()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'term_id' => $this->term->id,
        'season' => CorigentaSeason::cases()[0]->value,
        'corigenta_session_id' => $demoSession->id,
        'exam_commission_id' => $demoCommission->id,
    ]);

    $realCommission = ExamCommission::query()->create([
        'academic_year_id' => $this->year->id,
        'subject_id' => $subject->id,
        'name' => 'Comisia reală de geografie',
    ]);
    $realExam = CorigentaExam::query()->create([
        'student_id' => $realStudent->id,
        'subject_id' => $subject->id,
        'term_id' => $this->term->id,
        'season' => CorigentaSeason::cases()[0]->value,
        'exam_commission_id' => $realCommission->id,
    ]);

    artisan('app:purge-demo-data')->assertSuccessful();

    expect(ExamCommission::query()->pluck('id')->all())->toBe([$realCommission->id])
        ->and(CorigentaSession::query()->whereKey($demoSession->id)->exists())->toBeFalse()
        ->and(CorigentaExam::query()->pluck('id')->all())->toBe([$realExam->id]);
});
