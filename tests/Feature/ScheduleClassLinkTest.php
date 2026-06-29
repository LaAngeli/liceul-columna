<?php

use App\Enums\ScheduleType;
use App\Models\Schedule;
use App\Models\SchoolClass;

/**
 * Canonizare orar (O1): orarul „lecții" se leagă de clasa reală după etichetă, nedistructiv.
 * „Clasa {nume} {secțiune}" → clasa cu (name, section). Globalele (sunete/examene) nu se ating.
 */
it('leagă orarul „lecții" de clasa reală după etichetă', function () {
    $class = SchoolClass::factory()->create(['name' => 'IX', 'section' => '2', 'grade_level' => 9]);
    $schedule = Schedule::factory()->create([
        'type' => ScheduleType::Lessons,
        'label' => 'Clasa IX 2',
        'school_class_id' => null,
    ]);

    $this->artisan('app:link-schedule-classes')->assertSuccessful();

    expect($schedule->fresh()->school_class_id)->toBe($class->id);
});

it('lasă nelegată o etichetă fără clasă corespunzătoare', function () {
    Schedule::factory()->create([
        'type' => ScheduleType::Lessons,
        'label' => 'Clasa ZZ 9',
        'school_class_id' => null,
    ]);

    $this->artisan('app:link-schedule-classes')->assertSuccessful();

    expect(Schedule::where('label', 'Clasa ZZ 9')->first()?->school_class_id)->toBeNull();
});

it('nu atinge orarele globale (sunete/examene)', function () {
    $bell = Schedule::factory()->create([
        'type' => ScheduleType::Bells,
        'label' => 'Sunete',
        'school_class_id' => null,
    ]);

    $this->artisan('app:link-schedule-classes')->assertSuccessful();

    expect($bell->fresh()->school_class_id)->toBeNull();
});

it('relația inversă lessonsSchedule întoarce orarul clasei', function () {
    $class = SchoolClass::factory()->create(['name' => 'X', 'section' => 'R']);
    $schedule = Schedule::factory()->create([
        'type' => ScheduleType::Lessons,
        'label' => 'Clasa X R',
        'school_class_id' => $class->id,
    ]);

    expect($class->lessonsSchedule?->id)->toBe($schedule->id);
});
