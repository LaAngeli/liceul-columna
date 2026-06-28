<?php

use App\Enums\CorigentaSeason;
use App\Enums\CorigentaSessionStatus;
use App\Enums\CorigentaSessionType;
use App\Enums\UserRole;
use App\Filament\Resources\CorigentaExams\Pages\ListCorigentaExams;
use App\Filament\Resources\CorigentaSessions\Pages\ListCorigentaSessions;
use App\Filament\Resources\ExamCommissions\Pages\ListExamCommissions;
use App\Models\AcademicYear;
use App\Models\CorigentaSession;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('canManageCorigenta: conducerea DA, profesorul NU', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $prof = User::factory()->create();
    $prof->assignRole(UserRole::Profesor->value);

    expect($director->canManageCorigenta())->toBeTrue()
        ->and($prof->canManageCorigenta())->toBeFalse();
});

it('conducerea poate deschide listele de corigență din panou (smoke)', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $this->actingAs($director);

    Livewire::test(ListCorigentaSessions::class)->assertOk();
    Livewire::test(ListExamCommissions::class)->assertOk();
    Livewire::test(ListCorigentaExams::class)->assertOk();
});

it('directorul aprobă o sesiune (draft → aprobată, cu ordin)', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $year = AcademicYear::factory()->create();
    $session = CorigentaSession::create([
        'academic_year_id' => $year->id,
        'season' => CorigentaSeason::Vara,
        'type' => CorigentaSessionType::Baza,
        'starts_on' => '2026-08-24',
        'ends_on' => '2026-08-28',
        'status' => CorigentaSessionStatus::Draft,
    ]);

    $this->actingAs($director);

    Livewire::test(ListCorigentaSessions::class)
        ->callTableAction('approve', $session, ['order_reference' => 'Ordin 42/2026'])
        ->assertHasNoTableActionErrors();

    $session->refresh();
    expect($session->status)->toBe(CorigentaSessionStatus::Approved)
        ->and($session->order_reference)->toBe('Ordin 42/2026');
});
