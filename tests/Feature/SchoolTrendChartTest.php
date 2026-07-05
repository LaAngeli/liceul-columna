<?php

use App\Enums\UserRole;
use App\Filament\Widgets\SchoolTrendChart;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Grade;
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

function trendChartUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/**
 * Profesor legat de o fișă, repartizat la clasele date (visibleSchoolClassIds = acele clase).
 */
function trendTeacherUser(SchoolClass ...$classes): User
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

it('e o secțiune standard: vizibil conducerii, super-admin ȘI profesorului cu fișă', function () {
    $this->actingAs(trendChartUser(UserRole::Director));
    expect(SchoolTrendChart::canView())->toBeTrue();

    $this->actingAs(trendChartUser(UserRole::AdministratorOperational));
    expect(SchoolTrendChart::canView())->toBeTrue();

    // Super-adminul (break-glass, omniscient) — altfel contul IT nu ar vedea deloc graficul.
    $this->actingAs(trendChartUser(UserRole::Admin));
    expect(SchoolTrendChart::canView())->toBeTrue();

    // Profesorul cu fișă îl vede (scopat pe activitatea lui) — parte din structura standard.
    $this->actingAs(trendTeacherUser());
    expect(SchoolTrendChart::canView())->toBeTrue();
});

it('NU e vizibil administratorului tehnic sau unui cont fără fișă/rol academic', function () {
    // Administratorul TEHNIC = fără date academice (nici agregate).
    $this->actingAs(trendChartUser(UserRole::AdministratorTehnic));
    expect(SchoolTrendChart::canView())->toBeFalse();

    // Rol de profesor dar FĂRĂ fișă Teacher → nicio amprentă de catalog.
    $this->actingAs(trendChartUser(UserRole::Profesor));
    expect(SchoolTrendChart::canView())->toBeFalse();
});

it('scopează datele profesorului pe notele lui + absențele claselor lui', function () {
    $year = AcademicYear::factory()->create();
    $classA = SchoolClass::factory()->for($year)->create();
    $classB = SchoolClass::factory()->for($year)->create();

    $teacherUser = trendTeacherUser($classA);
    $teacherId = $teacherUser->teacher->id;

    // Nota profesorului (contează) + nota altui profesor (NU contează).
    Grade::factory()->create(['teacher_id' => $teacherId]);
    Grade::factory()->create();

    // Absență în clasa lui (contează) + absență în altă clasă (NU contează).
    Absence::factory()->create(['school_class_id' => $classA->id]);
    Absence::factory()->create(['school_class_id' => $classB->id]);

    $this->actingAs($teacherUser);

    $data = invokeGetData(new SchoolTrendChart);
    expect(array_sum($data['datasets'][0]['data']))->toBe(1) // doar nota lui
        ->and(array_sum($data['datasets'][1]['data']))->toBe(1); // doar absența clasei lui
});

it('pentru conducere agregă întreaga școală (fără scopare)', function () {
    $year = AcademicYear::factory()->create();
    $classA = SchoolClass::factory()->for($year)->create();
    $classB = SchoolClass::factory()->for($year)->create();

    Grade::factory()->count(3)->create();
    Absence::factory()->create(['school_class_id' => $classA->id]);
    Absence::factory()->create(['school_class_id' => $classB->id]);

    $this->actingAs(trendChartUser(UserRole::Director));

    $data = invokeGetData(new SchoolTrendChart);
    expect(array_sum($data['datasets'][0]['data']))->toBe(3)
        ->and(array_sum($data['datasets'][1]['data']))->toBe(2);
});

it('afișează implicit perioada de 6 luni în titlu', function () {
    $this->actingAs(trendChartUser(UserRole::Director));

    Livewire::test(SchoolTrendChart::class)
        ->assertOk()
        ->assertSee('Activitate catalog')
        ->assertSee('6 luni');
});

it('selectorul schimbă perioada (1 / 3 luni) și titlul reflectă alegerea', function () {
    $this->actingAs(trendChartUser(UserRole::Director));

    Livewire::test(SchoolTrendChart::class)
        ->set('filter', '1')
        ->assertOk()
        ->assertSee('1 lună')
        ->set('filter', '3')
        ->assertOk()
        ->assertSee('3 luni');
});

it('ignoră o valoare arbitrară din filtru și revine la 6 luni (whitelist)', function () {
    $this->actingAs(trendChartUser(UserRole::Director));

    Livewire::test(SchoolTrendChart::class)
        ->set('filter', '999')
        ->assertOk()
        ->assertSee('6 luni');
});

/**
 * Invocă metoda protejată getData() pentru a inspecta datele scopate.
 *
 * @return array{datasets: array<int, array<string, mixed>>, labels: array<int, string>}
 */
function invokeGetData(SchoolTrendChart $widget): array
{
    $method = new ReflectionMethod(SchoolTrendChart::class, 'getData');
    $method->setAccessible(true);

    /** @var array{datasets: array<int, array<string, mixed>>, labels: array<int, string>} $data */
    $data = $method->invoke($widget);

    return $data;
}
