<?php

use App\Enums\UserRole;
use App\Filament\Widgets\ActivityMonitor;
use App\Filament\Widgets\AudiencesPendingAssignment;
use App\Models\Message;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function userWithRoleE(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

// ─── S-2: widget „audiențe fără responsabil" doar cui poate atribui ─────────────────────

it('widgetul „audiențe fără responsabil" e ascuns prim-vicedirectorului, vizibil directorului', function () {
    // Audiență necitită pe un domeniu neatribuit → pendingCount > 0.
    Message::factory()->audience()->create(['read_at' => null]);

    AudiencesPendingAssignment::flushCache();
    actingAs(userWithRoleE(UserRole::PrimVicedirector->value));
    expect(AudiencesPendingAssignment::canView())->toBeFalse(); // vede semnalul dar nu poate atribui

    AudiencesPendingAssignment::flushCache();
    actingAs(userWithRoleE(UserRole::Director->value));
    expect(AudiencesPendingAssignment::canView())->toBeTrue();
});

// ─── S-3: Monitor activitate — serii implicite relevante rolului ─────────────────────────

it('Monitor activitate: seriile implicite urmează rolul (profesor vs non-didactic)', function () {
    $method = new ReflectionMethod(ActivityMonitor::class, 'defaultSeries');
    $method->setAccessible(true);

    $prof = userWithRoleE(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $prof->id]);
    actingAs($prof);
    expect($method->invoke(new ActivityMonitor))->toBe(['grades', 'absences']);

    actingAs(userWithRoleE(UserRole::Director->value));
    expect($method->invoke(new ActivityMonitor))->toBe(['corrections', 'motivations', 'messages']);
});
