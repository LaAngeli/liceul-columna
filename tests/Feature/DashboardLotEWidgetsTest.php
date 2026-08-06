<?php

use App\Enums\UserRole;
use App\Filament\Widgets\ActivityMonitor;
use App\Filament\Widgets\AudiencesPendingAssignment;
use App\Models\Grade;
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

// ─── S-3: Pulsul activității — relevanța pe rol vine din chips-urile cu activitate ────────
// (Redesenat 07.08.2026: vechea logică „serii implicite pe rol" exista fiindcă filtrele stăteau
// ascunse după pâlnie. Acum categoriile fără activitate nu primesc chip deloc, deci un director
// nu vede „Note 0" ca zgomot, iar un profesor își vede exact ce a lucrat.)

it('Pulsul activității: doar categoriile cu activitate primesc chip — relevanța pe rol, fără configurare', function () {
    $prof = userWithRoleE(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $prof->id]);
    Grade::factory()->create(['teacher_id' => $teacher->id]);

    actingAs($prof);
    expect(array_column((new ActivityMonitor)->pulse()['cats'], 'key'))->toBe(['grades']);

    $director = userWithRoleE(UserRole::Director->value);
    Message::factory()->create(['sender_user_id' => $director->id]);

    actingAs($director);
    expect(array_column((new ActivityMonitor)->pulse()['cats'], 'key'))->toBe(['messages']);
});
