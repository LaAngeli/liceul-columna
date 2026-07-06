<?php

use App\Enums\UserRole;
use App\Filament\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\Students\RelationManagers\GradesRelationManager;
use App\Models\Student;
use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

// ─── S-6: setările de notificare ignoră tipurile irelevante rolului ─────────────────────

it('setările de notificare păstrează doar tipurile RELEVANTE rolului (ignoră tipurile de staff)', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    actingAs($parent)->put(route('cabinet.notifications.settings.update'), [
        'preferences' => [
            'new_grade' => ['email'],                 // relevant familiei → păstrat
            'grade_correction_request' => ['email'],  // tip destinat STAFF-ului → ignorat pe server
        ],
    ])->assertRedirect();

    $parent->refresh();

    expect($parent->notification_preferences)->toHaveKey('new_grade')
        ->and($parent->notification_preferences)->not->toHaveKey('grade_correction_request');
});

// ─── S-9: titlurile RelationManager sunt traduse (nu hardcodate RO) ─────────────────────

it('titlurile RelationManager se traduc (RU/EN), nu rămân hardcodate în RO', function () {
    app()->setLocale('en');

    expect(GradesRelationManager::getTitle(new Student, ''))->toBe(__('panel.resources.grades.plural'))
        ->and(AuditsRelationManager::getTitle(new Student, ''))->toBe(__('panel.resources.audits.label'));

    app()->setLocale('ro');
});
