<?php

use App\Enums\ScheduleType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Orarul demo trebuie să parcurgă LANȚUL REAL (cerința beneficiarului): administratorul
 * operațional creează și publică → importatorul real derivă orarul structurat → cabinetul îl
 * livrează familiei. Testul pinuiește exact ce nu se vede din interfață: cine are voie să scrie,
 * că lecțiile sunt DERIVATE (nu injectate) și că popularea de test nu atinge clasele reale.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

/**
 * O zonă demo minimă: un an, o clasă „[DEMO]" de treapta I, disciplinele ei și contul
 * administratorului operațional (autorul de drept al orarului).
 *
 * @return array{0: SchoolClass, 1: User}
 */
function demoScheduleZone(): array
{
    $year = AcademicYear::factory()->create();

    $class = SchoolClass::factory()->for($year)->create([
        'name' => '[DEMO] 1A',
        'section' => 'A',
        'grade_level' => 1,
    ]);

    // Nomenclator pe treapta I + o disciplină de gimnaziu, care NU are ce căuta în orarul clasei I.
    Subject::factory()->create(['name' => 'Matematică', 'min_grade' => 1, 'max_grade' => 4]);
    Subject::factory()->create(['name' => 'Limba și literatura română', 'min_grade' => 1, 'max_grade' => 4]);
    Subject::factory()->create(['name' => 'Educație plastică', 'min_grade' => 1, 'max_grade' => 4]);
    Subject::factory()->create(['name' => 'Fizică', 'min_grade' => 6, 'max_grade' => 12]);

    $author = User::factory()->create(['email' => 'operational@columna.test']);
    $author->assignRole(UserRole::AdministratorOperational->value);

    return [$class, $author];
}

it('administratorul operațional publică orarul, iar lecțiile se DERIVĂ din el', function () {
    [$class] = demoScheduleZone();

    $this->artisan('app:seed-demo-schedule')->assertSuccessful();

    $schedule = Schedule::query()
        ->where('type', ScheduleType::Lessons)
        ->where('school_class_id', $class->id)
        ->first();

    expect($schedule)->not->toBeNull()
        ->and($schedule->label)->toStartWith('[DEMO]')   // marcaj pentru curățare
        ->and($schedule->is_public)->toBeTrue();          // publicarea = pasul care îl face vizibil

    // Lecțiile structurate NU sunt scrise de seeder: vin din parserul real, peste tabelul publicat.
    expect(Lesson::query()->where('school_class_id', $class->id)->count())->toBeGreaterThan(0);
});

it('orarul clasei I conține DOAR discipline de treapta ei — niciodată fizică', function () {
    [$class] = demoScheduleZone();

    $this->artisan('app:seed-demo-schedule')->assertSuccessful();

    $names = Lesson::query()
        ->where('school_class_id', $class->id)
        ->with('subject')
        ->get()
        ->map(fn (Lesson $lesson): string => (string) $lesson->subject?->name)
        ->unique();

    expect($names)->not->toContain('Fizică')
        ->and($names->every(fn (string $name): bool => in_array($name, ['Matematică', 'Limba și literatura română', 'Educație plastică'], true)))->toBeTrue();
});

it('refuză să scrie dacă autorul NU are dreptul de a publica orare (§3.2)', function () {
    [$class, $author] = demoScheduleZone();

    // Îi luăm capabilitatea: demonstrația nu trebuie să ocolească tăcut regula pe care o ilustrează.
    $author->syncRoles([UserRole::Profesor->value]);

    $this->artisan('app:seed-demo-schedule')->assertFailed();

    expect(Schedule::query()->where('school_class_id', $class->id)->exists())->toBeFalse();
});

it('NU atinge orarul claselor reale — popularea de test rămâne în zona demo', function () {
    [, $author] = demoScheduleZone();
    $realClass = SchoolClass::factory()->create(['name' => 'V', 'section' => '1', 'grade_level' => 5]);

    // Clasa reală are orar publicat + o lecție introdusă MANUAL în panou.
    Schedule::create([
        'type' => ScheduleType::Lessons,
        'label' => 'Clasa V 1',
        'school_class_id' => $realClass->id,
        'headers' => ['', 'Luni'],
        'rows' => [['Lecția 1 08.00 – 08.45', 'Matematică']],
        'is_public' => true,
    ]);
    $manual = Lesson::create([
        'academic_year_id' => $realClass->academic_year_id,
        'school_class_id' => $realClass->id,
        'subject_id' => Subject::query()->where('name', 'Matematică')->value('id'),
        'day_of_week' => 3,
        'lesson_number' => 7,
        'room' => 'MANUAL',
    ]);

    $this->artisan('app:seed-demo-schedule')->assertSuccessful();

    // Lecția manuală supraviețuiește: fără filtrul de perimetru, `--force` ar fi rescris-o.
    expect(Lesson::query()->whereKey($manual->id)->value('room'))->toBe('MANUAL');

    unset($author);
});

it('--remove șterge orarele demo ȘI lecțiile derivate din ele', function () {
    [$class] = demoScheduleZone();

    $this->artisan('app:seed-demo-schedule')->assertSuccessful();
    $this->artisan('app:seed-demo-schedule', ['--remove' => true])->assertSuccessful();

    expect(Schedule::query()->where('school_class_id', $class->id)->exists())->toBeFalse()
        ->and(Lesson::query()->where('school_class_id', $class->id)->exists())->toBeFalse();
});
