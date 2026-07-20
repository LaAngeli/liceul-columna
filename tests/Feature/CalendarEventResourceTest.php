<?php

use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Enums\UserRole;
use App\Filament\Resources\CalendarEvents\CalendarEventResource;
use App\Filament\Resources\CalendarEvents\Pages\CreateCalendarEvent;
use App\Models\CalendarEvent;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Modul Calendar V2c: resursa Filament de evenimente. Creare gated pe rol (conducere vs diriginte),
 * scoping pe server pentru diriginte, coerența scope↔FK.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function makeDiriginte(): array
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $class = SchoolClass::factory()->create(['homeroom_teacher_id' => $teacher->id, 'grade_level' => 9]);

    return [$user->fresh(), $class];
}

it('gating: conducerea și dirigintele au acces, profesorul simplu și părintele nu', function () {
    [$diriginte] = makeDiriginte();

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $profesor->id]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $this->actingAs($director);
    expect(CalendarEventResource::canViewAny())->toBeTrue();

    $this->actingAs($diriginte);
    expect(CalendarEventResource::canViewAny())->toBeTrue();

    $this->actingAs($profesor->fresh());
    expect(CalendarEventResource::canViewAny())->toBeFalse();

    $this->actingAs($parent);
    expect(CalendarEventResource::canViewAny())->toBeFalse();
});

it('conducerea creează un eveniment global, cu autor și fără FK reziduale', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $this->actingAs($director);

    Livewire::test(CreateCalendarEvent::class)
        ->fillForm([
            'type' => CalendarEventType::SchoolEvent->value,
            'visibility_scope' => CalendarEventScope::Global->value,
            'title' => 'Festivalul de toamnă',
            // Dată VIITOARE: de la gardarea calendarului (2026-07-20), formularul refuză trecutul
            // pentru evenimente noi — fixtura veche folosea o dată deja consumată.
            'starts_on' => now()->addMonth()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('calendar_events', [
        'title' => 'Festivalul de toamnă',
        'created_by' => $director->id,
        'grade_level' => null,
        'school_class_id' => null,
    ]);
});

it('dirigintele creează un eveniment pentru clasa lui', function () {
    [$diriginte, $class] = makeDiriginte();

    $this->actingAs($diriginte);

    Livewire::test(CreateCalendarEvent::class)
        ->fillForm([
            'type' => CalendarEventType::Meeting->value,
            'visibility_scope' => CalendarEventScope::SchoolClass->value,
            'school_class_id' => $class->id,
            'title' => 'Ședință cu părinții',
            'starts_on' => now()->addWeek()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('calendar_events', [
        'title' => 'Ședință cu părinții',
        'school_class_id' => $class->id,
        'created_by' => $diriginte->id,
    ]);
});

it('dirigintele vede în listă doar evenimentele claselor lui', function () {
    [$diriginte, $class] = makeDiriginte();

    $mine = CalendarEvent::factory()->forClass($class->id)->create();
    $global = CalendarEvent::factory()->create();
    $otherClass = CalendarEvent::factory()->forClass(SchoolClass::factory()->create()->id)->create();

    $this->actingAs($diriginte);

    $ids = CalendarEventResource::getEloquentQuery()->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($global->id)
        ->and($ids)->not->toContain($otherClass->id);
});

it('dirigintele poate edita doar evenimentele de clasă ale claselor lui', function () {
    [$diriginte, $class] = makeDiriginte();

    $mine = CalendarEvent::factory()->forClass($class->id)->create();
    $global = CalendarEvent::factory()->create();
    $otherClass = CalendarEvent::factory()->forClass(SchoolClass::factory()->create()->id)->create();

    $this->actingAs($diriginte);

    expect(CalendarEventResource::canEdit($mine))->toBeTrue()
        ->and(CalendarEventResource::canEdit($global))->toBeFalse()
        ->and(CalendarEventResource::canEdit($otherClass))->toBeFalse();
});
