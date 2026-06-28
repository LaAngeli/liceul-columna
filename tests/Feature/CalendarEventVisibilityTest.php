<?php

use App\Enums\UserRole;
use App\Models\CalendarEvent;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Modul Calendar V2a: vizibilitatea evenimentelor manuale (global / treaptă / clasă) + traduceri cu
 * fallback RO + gating-ul „cine poate gestiona evenimente".
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('evenimentul global e vizibil oricărei clase și fără clasă', function () {
    $event = CalendarEvent::factory()->create();
    $class = SchoolClass::factory()->create(['grade_level' => 9]);

    expect($event->isVisibleToClass($class))->toBeTrue()
        ->and($event->isVisibleToClass(null))->toBeTrue();
});

it('evenimentul de treaptă e vizibil doar claselor acelei trepte', function () {
    $event = CalendarEvent::factory()->forGrade(9)->create();
    $grade9 = SchoolClass::factory()->create(['grade_level' => 9]);
    $grade10 = SchoolClass::factory()->create(['grade_level' => 10]);

    expect($event->isVisibleToClass($grade9))->toBeTrue()
        ->and($event->isVisibleToClass($grade10))->toBeFalse()
        ->and($event->isVisibleToClass(null))->toBeFalse();
});

it('evenimentul de clasă e vizibil doar clasei sale', function () {
    $class = SchoolClass::factory()->create(['grade_level' => 11]);
    $other = SchoolClass::factory()->create(['grade_level' => 11]);
    $event = CalendarEvent::factory()->forClass($class->id)->create();

    expect($event->isVisibleToClass($class))->toBeTrue()
        ->and($event->isVisibleToClass($other))->toBeFalse();
});

it('scopeVisibleToClass returnează exact evenimentele vizibile clasei', function () {
    $class = SchoolClass::factory()->create(['grade_level' => 9]);

    $global = CalendarEvent::factory()->create();
    $sameGrade = CalendarEvent::factory()->forGrade(9)->create();
    CalendarEvent::factory()->forGrade(10)->create();
    $sameClass = CalendarEvent::factory()->forClass($class->id)->create();
    CalendarEvent::factory()->forClass(SchoolClass::factory()->create()->id)->create();

    $visible = CalendarEvent::query()->visibleToClass($class)->pluck('id');

    expect($visible)->toHaveCount(3)
        ->and($visible)->toContain($global->id, $sameGrade->id, $sameClass->id);
});

it('titlul cade pe RO când nu există traducere și folosește RU când există', function () {
    $event = CalendarEvent::factory()->create(['title' => 'Ședință RO']);
    $event->translations()->create(['locale' => 'ru', 'title' => 'Собрание']);

    expect($event->localizedTitle('ro'))->toBe('Ședință RO')
        ->and($event->localizedTitle('ru'))->toBe('Собрание')
        ->and($event->localizedTitle('en'))->toBe('Ședință RO');
});

it('gating: conducerea și dirigintele pot gestiona evenimente, profesorul simplu și părintele nu', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $diriginte = User::factory()->create();
    $diriginte->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $diriginte->id]);
    SchoolClass::factory()->create(['homeroom_teacher_id' => $teacher->id]);

    $plainTeacher = User::factory()->create();
    $plainTeacher->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $plainTeacher->id]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    expect($director->canManageCalendarEvents())->toBeTrue()
        ->and($diriginte->fresh()->canManageCalendarEvents())->toBeTrue()
        ->and($plainTeacher->fresh()->canManageCalendarEvents())->toBeFalse()
        ->and($parent->canManageCalendarEvents())->toBeFalse();
});
