<?php

/**
 * Calendar v3 — admitere: programarea vizitei (dată + oră) de către secretariat și proiecția ei
 * în calendarul INSTITUȚIONAL al staff-ului (fără PII de copil în titlu; familia nu o vede).
 */

use App\Actions\ProcessAdmissionRequest;
use App\Calendar\CalendarAccess;
use App\Calendar\CalendarItem;
use App\Calendar\CalendarScope;
use App\Calendar\Projectors\AdmissionVisitProjector;
use App\Enums\AdmissionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\AdmissionRequests\Pages\ViewAdmissionRequest;
use App\Models\AdmissionRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function admissionStaff(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole(UserRole::AdministratorOperational->value);

    return $user;
}

/** @return list<CalendarItem> */
function projectVisits(CalendarScope $scope): array
{
    return app(AdmissionVisitProjector::class)->project(
        $scope,
        Carbon::parse('2026-09-01'),
        Carbon::parse('2026-09-30'),
    );
}

it('programarea vizitei setează data și trece cererea nouă în „Contactat"', function () {
    $request = AdmissionRequest::factory()->visit()->create();

    app(ProcessAdmissionRequest::class)->scheduleVisit($request, admissionStaff(), Carbon::parse('2026-09-10 10:30'));

    $request->refresh();

    expect($request->status)->toBe(AdmissionStatus::Contactat)
        ->and($request->contacted_at)->not->toBeNull()
        ->and($request->scheduled_visit_at?->format('Y-m-d H:i'))->toBe('2026-09-10 10:30');
});

it('vizita programată apare în calendarul staff cu numele PĂRINTELUI, fără numele copilului', function () {
    $request = AdmissionRequest::factory()->visit()->contacted()->create([
        'parent_name' => 'Rusu Elena',
        'child_name' => 'Rusu Andrei',
        'scheduled_visit_at' => '2026-09-10 10:30',
    ]);

    $items = projectVisits(app(CalendarAccess::class)->staffScope(admissionStaff()));

    expect($items)->toHaveCount(1)
        ->and($items[0]->date)->toBe('2026-09-10')
        ->and($items[0]->startTime)->toBe('10:30')
        ->and($items[0]->title)->toContain('Rusu Elena')
        ->and($items[0]->title)->not->toContain('Andrei')
        ->and($items[0]->studentId)->toBeNull();

    // Re-programarea înlocuiește data (aceeași cerere, un singur eveniment).
    app(ProcessAdmissionRequest::class)->scheduleVisit($request, admissionStaff(), Carbon::parse('2026-09-12 09:00'));
    $items = projectVisits(app(CalendarAccess::class)->staffScope(admissionStaff()));

    expect($items)->toHaveCount(1)
        ->and($items[0]->date)->toBe('2026-09-12');
});

it('vizita cererii REFUZATE iese din calendar; familia nu vede vizitele deloc', function () {
    AdmissionRequest::factory()->visit()->rejected()->create([
        'scheduled_visit_at' => '2026-09-10 10:30',
    ]);

    expect(projectVisits(app(CalendarAccess::class)->staffScope(admissionStaff())))->toHaveCount(0);

    // Scope de FAMILIE (non-staff) — vizitele de admitere nu au ce căuta în cabinetul familiei.
    AdmissionRequest::factory()->visit()->contacted()->create([
        'scheduled_visit_at' => '2026-09-15 11:00',
    ]);
    $familyScope = new CalendarScope(User::factory()->create(), collect());

    expect(projectVisits($familyScope))->toHaveCount(0);
});

it('acțiunea „Programează vizita" din fișă e vizibilă doar pe cererile de tip vizită și salvează data', function () {
    actingAs(admissionStaff());

    $visit = AdmissionRequest::factory()->visit()->create();
    Livewire::test(ViewAdmissionRequest::class, ['record' => $visit->id])
        ->assertActionVisible('scheduleVisit')
        ->callAction('scheduleVisit', ['scheduled_visit_at' => '2026-09-10 10:30'])
        ->assertHasNoActionErrors();

    expect($visit->refresh()->scheduled_visit_at?->format('Y-m-d H:i'))->toBe('2026-09-10 10:30');

    // Cererea de ÎNMATRICULARE nu are vizită de programat.
    $enrollment = AdmissionRequest::factory()->create();
    Livewire::test(ViewAdmissionRequest::class, ['record' => $enrollment->id])
        ->assertActionHidden('scheduleVisit');
});
