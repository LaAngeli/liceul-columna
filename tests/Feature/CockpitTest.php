<?php

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\AbsenceMotivation;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    // Randarea paginii /dashboard include directivele @vite; fără manifest (sau în timpul unui build)
    // ar arunca ViteException → 500. `withoutVite` o face deterministă, independent de starea build-ului.
    $this->withoutVite();
});

it('motivările în așteptare apar PER-COPIL pe card, nu agregat în banda de alerte', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);

    $childA = Student::factory()->create();
    $childB = Student::factory()->create();
    $parent->students()->attach([$childA->id, $childB->id]);

    // 1 motivare pending pentru fiecare copil (2 copii diferiți).
    AbsenceMotivation::factory()->create(['student_id' => $childA->id, 'status' => RequestStatus::Pending]);
    AbsenceMotivation::factory()->create(['student_id' => $childB->id, 'status' => RequestStatus::Pending]);
    // O motivare deja aprobată NU se numără.
    AbsenceMotivation::factory()->create(['student_id' => $childA->id, 'status' => RequestStatus::Approved]);

    $this->actingAs($parent)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            // Banda de alerte NU mai conține motivările (sunt status per-copil).
            ->missing('cabinet.alerts.pending_motivations')
            ->has('cabinet.alerts.unread_messages')
            ->has('cabinet.alerts.at_risk')
            // Fiecare copil are propriul contor (1 fiecare — numărul se potrivește cu profilul lui).
            ->has('cabinet.children', 2)
            ->where('cabinet.children.0.pendingMotivations', 1)
            ->where('cabinet.children.1.pendingMotivations', 1)
        );
});

it('un copil fără motivări pending arată 0 pe card', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $child = Student::factory()->create();
    $parent->students()->attach($child->id);

    $this->actingAs($parent)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('cabinet.children.0.pendingMotivations', 0));
});
