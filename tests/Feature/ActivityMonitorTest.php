<?php

use App\Enums\UserRole;
use App\Filament\Widgets\ActivityMonitor;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\Message;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function activityStaffUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/** Profesor legat de o fișă, repartizat la clasele date (visibleSchoolClassIds = acele clase). */
function activityTeacherUser(SchoolClass ...$classes): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $subject = Subject::factory()->create();

    foreach ($classes as $class) {
        TeachingAssignment::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
        ]);
    }

    return $user;
}

/**
 * Invocă getData() protejat cu un set de filtre dat, ca să inspectăm datasets/labels.
 *
 * @param  array<string, mixed>  $filters
 * @return array{datasets: array<int, array<string, mixed>>, labels: array<int, string>}
 */
function activityData(array $filters): array
{
    $widget = new ActivityMonitor;
    $widget->filters = $filters;

    $method = new ReflectionMethod(ActivityMonitor::class, 'getData');
    $method->setAccessible(true);

    /** @var array{datasets: array<int, array<string, mixed>>, labels: array<int, string>} $data */
    $data = $method->invoke($widget);

    return $data;
}

/**
 * Suma unei serii după etichetă (peste toate bucket-urile).
 *
 * @param  array{datasets: array<int, array<string, mixed>>, labels: array<int, string>}  $data
 */
function activitySeriesSum(array $data, string $label): int
{
    foreach ($data['datasets'] as $dataset) {
        if ($dataset['label'] === $label) {
            return (int) array_sum($dataset['data']);
        }
    }

    return -1; // seria lipsește
}

it('e o secțiune standard: vizibil oricărui staff logat, ascuns musafirului', function () {
    $this->actingAs(activityStaffUser(UserRole::Director));
    expect(ActivityMonitor::canView())->toBeTrue();

    $this->actingAs(activityStaffUser(UserRole::AdministratorTehnic));
    expect(ActivityMonitor::canView())->toBeTrue();

    $this->actingAs(activityTeacherUser());
    expect(ActivityMonitor::canView())->toBeTrue();

    auth('web')->logout();
    expect(ActivityMonitor::canView())->toBeFalse();
});

it('implicit desenează 3 serii: total + note + absențe, pe 6 luni', function () {
    $this->actingAs(activityTeacherUser());

    $data = activityData([]); // fără filtre → default period=6, series=[grades,absences]

    expect($data['datasets'])->toHaveCount(3)
        ->and($data['datasets'][0]['label'])->toBe('Activitate totală')
        ->and($data['datasets'][1]['label'])->toBe('Note')
        ->and($data['datasets'][2]['label'])->toBe('Absențe')
        ->and($data['labels'])->toHaveCount(6);
});

it('bifarea unei serii o adaugă (corecții) — 4 serii', function () {
    $this->actingAs(activityTeacherUser());

    $data = activityData(['series' => ['grades', 'absences', 'corrections']]);

    expect($data['datasets'])->toHaveCount(4)
        ->and(collect($data['datasets'])->pluck('label'))->toContain('Corecții note');
});

it('debifarea tuturor lasă doar linia Total (gol ≠ toate)', function () {
    $this->actingAs(activityTeacherUser());

    $data = activityData(['series' => []]);

    expect($data['datasets'])->toHaveCount(1)
        ->and($data['datasets'][0]['label'])->toBe('Activitate totală')
        ->and(array_sum($data['datasets'][0]['data']))->toBe(0);
});

it('scopează notele pe profesorul curent (exclude ale altuia + anulate)', function () {
    $year = AcademicYear::factory()->create();
    $classA = SchoolClass::factory()->for($year)->create();

    $teacherUser = activityTeacherUser($classA);
    $teacherId = $teacherUser->teacher->id;

    Grade::factory()->create(['teacher_id' => $teacherId]);                         // a lui — contează
    Grade::factory()->create(['teacher_id' => $teacherId, 'annulled_at' => now()]); // anulată — NU
    Grade::factory()->create();                                                     // a altui profesor — NU

    $this->actingAs($teacherUser);
    $data = activityData(['series' => ['grades']]);

    expect(activitySeriesSum($data, 'Note'))->toBe(1);
});

it('scopează absențele pe clasele profesorului', function () {
    $year = AcademicYear::factory()->create();
    $classA = SchoolClass::factory()->for($year)->create();
    $classB = SchoolClass::factory()->for($year)->create();

    $teacherUser = activityTeacherUser($classA);

    Absence::factory()->create(['school_class_id' => $classA->id]); // clasa lui — contează
    Absence::factory()->create(['school_class_id' => $classB->id]); // altă clasă — NU

    $this->actingAs($teacherUser);
    $data = activityData(['series' => ['absences']]);

    expect(activitySeriesSum($data, 'Absențe'))->toBe(1);
});

it('scopează corecțiile pe cererile + revizuirile userului', function () {
    $userA = activityStaffUser(UserRole::Director);
    $userB = activityStaffUser(UserRole::Profesor);

    GradeCorrection::factory()->create(['requested_by_user_id' => $userA->id]);
    GradeCorrection::factory()->create(['reviewed_by_user_id' => $userA->id, 'reviewed_at' => now()]);
    GradeCorrection::factory()->create(['requested_by_user_id' => $userB->id]); // al altuia — NU

    $this->actingAs($userA);
    $data = activityData(['series' => ['corrections']]);

    expect(activitySeriesSum($data, 'Corecții note'))->toBe(2);
});

it('scopează mesajele pe expeditor', function () {
    $userA = activityStaffUser(UserRole::Director);
    $userB = activityStaffUser(UserRole::Profesor);

    Message::factory()->create(['sender_user_id' => $userA->id]);
    Message::factory()->create(['sender_user_id' => $userB->id]); // al altuia — NU

    $this->actingAs($userA);
    $data = activityData(['series' => ['messages']]);

    expect(activitySeriesSum($data, 'Mesaje'))->toBe(1);
});

it('Total = suma seriilor AFIȘATE (ignoră categoriile ascunse)', function () {
    $year = AcademicYear::factory()->create();
    $classA = SchoolClass::factory()->for($year)->create();

    $teacherUser = activityTeacherUser($classA);
    $teacherId = $teacherUser->teacher->id;

    Grade::factory()->count(2)->create(['teacher_id' => $teacherId]);
    Absence::factory()->create(['school_class_id' => $classA->id]);

    $this->actingAs($teacherUser);

    // Ambele vizibile → Total = 2 note + 1 absență = 3.
    $both = activityData(['series' => ['grades', 'absences']]);
    expect(activitySeriesSum($both, 'Activitate totală'))->toBe(3);

    // Doar notele → Total = 2 (absența ascunsă NU intră).
    $onlyGrades = activityData(['series' => ['grades']]);
    expect(activitySeriesSum($onlyGrades, 'Activitate totală'))->toBe(2);
});

it('whitelist perioadă + bucketing adaptiv', function () {
    $this->actingAs(activityTeacherUser());

    // Valoare arbitrară → revine la 6 (6 bucket-uri lunare).
    expect(activityData(['period' => '999'])['labels'])->toHaveCount(6);

    // 1 lună → 4 bucket-uri săptămânale (linie citibilă).
    expect(activityData(['period' => '1'])['labels'])->toHaveCount(4);
});

it('titlul reflectă perioada, pluralizat (RO)', function () {
    $this->actingAs(activityStaffUser(UserRole::Director));

    Livewire::test(ActivityMonitor::class)
        ->assertOk()
        ->assertSee('Monitor activitate')
        ->assertSee('6 luni');
});
