<?php

/**
 * Secțiunea „Cereri de înscriere" — restructurată (2026-07-17): navigator pe vederi
 * („De procesat" / „Arhivă") → carduri pe tip (vizite / înmatriculări) → tabel în context;
 * procesarea = acțiuni cu URMĂ (cine, când, cu ce notă), nu un select anonim de stare;
 * cererea înmatriculată întinde puntea spre onboarding-ul unificat al elevului.
 */

use App\Actions\ProcessAdmissionRequest;
use App\Enums\AdmissionRequestType;
use App\Enums\AdmissionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\AdmissionRequests\AdmissionRequestActions;
use App\Filament\Resources\AdmissionRequests\AdmissionRequestResource;
use App\Filament\Resources\AdmissionRequests\Pages\ListAdmissionRequests;
use App\Filament\Resources\AdmissionRequests\Pages\ViewAdmissionRequest;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\AdmissionRequest;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
    actingAs($this->director);
});

// ─── Navigatorul: vederi → carduri pe tip → context ─────────────────────────────────────

it('aterizarea arată cardurile ambelor tipuri, cu numărători și semnale', function () {
    AdmissionRequest::factory()->count(2)->create();                    // înmatriculări noi
    AdmissionRequest::factory()->visit()->contacted()->create();        // vizită contactată
    AdmissionRequest::factory()->enrolled()->create();                  // închisă — nu intră în coadă

    $page = Livewire::test(ListAdmissionRequests::class)->instance();
    $cards = collect($page->typeCards());

    expect($cards->pluck('id')->all())->toBe([
        AdmissionRequestType::Visit->value,
        AdmissionRequestType::Enrollment->value,
    ]);

    $enrollmentCard = $cards->firstWhere('id', AdmissionRequestType::Enrollment->value);
    expect($enrollmentCard['badge'])->toBe('2')
        ->and($enrollmentCard['stats'][0])->toContain('2');

    $visitCard = $cards->firstWhere('id', AdmissionRequestType::Visit->value);
    expect($visitCard['badge'])->toBeNull()
        ->and($visitCard['stats'][1])->toContain('1');

    // Pastilele vederilor numără coada (3 în lucru) și arhiva (1 închisă).
    $pills = collect($page->admissionViewPills());
    expect($pills->firstWhere('key', 'queue')['count'])->toBe(3)
        ->and($pills->firstWhere('key', 'archive')['count'])->toBe(1);
});

it('contextul unui tip arată doar cererile lui în lucru; un tip inventat nu deschide context', function () {
    $visit = AdmissionRequest::factory()->visit()->create();
    $enrollment = AdmissionRequest::factory()->create();
    $closedVisit = AdmissionRequest::factory()->visit()->enrolled()->create();

    Livewire::withQueryParams(['tip' => 'visit'])
        ->test(ListAdmissionRequests::class)
        ->assertCanSeeTableRecords([$visit])
        ->assertCanNotSeeTableRecords([$enrollment, $closedVisit]);

    expect(Livewire::withQueryParams(['tip' => 'tip-inventat'])->test(ListAdmissionRequests::class)->instance()->activeType())
        ->toBeNull();
});

it('arhiva arată doar cererile închise ale tipului, cu urma procesării', function () {
    $pending = AdmissionRequest::factory()->create();
    $enrolled = AdmissionRequest::factory()->enrolled()->create();
    $refused = AdmissionRequest::factory()->rejected()->create();

    Livewire::withQueryParams(['arhiva' => '1', 'tip' => 'enrollment'])
        ->test(ListAdmissionRequests::class)
        ->assertCanSeeTableRecords([$enrolled, $refused])
        ->assertCanNotSeeTableRecords([$pending]);
});

// ─── Procesarea cu urmă ──────────────────────────────────────────────────────────────────

it('marcarea contactării trece cererea „în lucru" și reține momentul + autorul', function () {
    $request = AdmissionRequest::factory()->create();

    Livewire::withQueryParams(['tip' => 'enrollment'])
        ->test(ListAdmissionRequests::class)
        ->callTableAction('markContacted', $request)
        ->assertHasNoTableActionErrors();

    $request->refresh();

    expect($request->status)->toBe(AdmissionStatus::Contactat)
        ->and($request->contacted_at)->not->toBeNull()
        ->and($request->processed_by_id)->toBe($this->director->id)
        ->and($request->processed_at)->toBeNull();
});

it('înmatricularea închide cererea cu notă opțională și urmă completă', function () {
    $request = AdmissionRequest::factory()->contacted()->create();

    Livewire::test(ViewAdmissionRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('enroll', data: ['staff_note' => 'Locul confirmat la clasa I.'])
        ->assertHasNoActionErrors();

    $request->refresh();

    expect($request->status)->toBe(AdmissionStatus::Inmatriculat)
        ->and($request->processed_at)->not->toBeNull()
        ->and($request->processed_by_id)->toBe($this->director->id)
        ->and($request->staff_note)->toBe('Locul confirmat la clasa I.');
});

it('refuzul cere motivul intern; fără el cererea rămâne neatinsă', function () {
    $request = AdmissionRequest::factory()->create();

    Livewire::test(ViewAdmissionRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('refuse', data: ['staff_note' => ''])
        ->assertHasActionErrors(['staff_note']);

    expect($request->fresh()->status)->toBe(AdmissionStatus::Nou);

    Livewire::test(ViewAdmissionRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('refuse', data: ['staff_note' => 'Locuri epuizate la clasa cerută.'])
        ->assertHasNoActionErrors();

    $request->refresh();

    expect($request->status)->toBe(AdmissionStatus::Refuzat)
        ->and($request->staff_note)->toBe('Locuri epuizate la clasa cerută.')
        // Închisă direct din „Nou" → contactul s-a întâmplat oricum, momentul se completează.
        ->and($request->contacted_at)->not->toBeNull();
});

it('redeschiderea readuce cererea în coadă: decizia dispare, istoricul contactării rămâne', function () {
    $request = AdmissionRequest::factory()->rejected()->create();
    $contactedAt = $request->contacted_at;

    Livewire::test(ViewAdmissionRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('reopen')
        ->assertHasNoActionErrors();

    $request->refresh();

    expect($request->status)->toBe(AdmissionStatus::Contactat)
        ->and($request->processed_at)->toBeNull()
        ->and($request->contacted_at?->toDateTimeString())->toBe($contactedAt?->toDateTimeString());
});

it('acțiunile respectă starea: „contactat" doar pe noi, „redeschide" doar pe închise', function () {
    $new = AdmissionRequest::factory()->create();
    $closed = AdmissionRequest::factory()->enrolled()->create();

    Livewire::withQueryParams(['tip' => 'enrollment'])
        ->test(ListAdmissionRequests::class)
        ->assertTableActionVisible('markContacted', $new)
        ->assertTableActionVisible('enroll', $new)
        ->assertTableActionHidden('reopen', $new);

    Livewire::withQueryParams(['arhiva' => '1', 'tip' => 'enrollment'])
        ->test(ListAdmissionRequests::class)
        ->assertTableActionVisible('reopen', $closed)
        ->assertTableActionHidden('markContacted', $closed)
        ->assertTableActionHidden('enroll', $closed);
});

// ─── Garduri și integrare ────────────────────────────────────────────────────────────────

it('ștergerea în masă (curățare PII) există doar în arhivă, nu pe coada de lucru', function () {
    AdmissionRequest::factory()->create();
    AdmissionRequest::factory()->enrolled()->create();

    Livewire::withQueryParams(['tip' => 'enrollment'])
        ->test(ListAdmissionRequests::class)
        ->assertTableBulkActionHidden('delete');

    Livewire::withQueryParams(['arhiva' => '1', 'tip' => 'enrollment'])
        ->test(ListAdmissionRequests::class)
        ->assertTableBulkActionVisible('delete');
});

it('badge-ul de navigație numără toată coada (noi + contactate), nu doar noile', function () {
    AdmissionRequest::factory()->count(2)->create();
    AdmissionRequest::factory()->contacted()->create();
    AdmissionRequest::factory()->enrolled()->create();

    expect(AdmissionRequestResource::getNavigationBadge())->toBe('3');
});

it('cererea înmatriculată întinde puntea spre onboarding: rol elev + numele pre-completat', function () {
    $request = AdmissionRequest::factory()->create(['child_name' => 'Popescu Ana Maria']);

    $url = AdmissionRequestActions::onboardingUrl($request);

    expect($url)->toContain('rol=elev')
        ->and($url)->toContain('nume=Popescu')
        ->and($url)->toContain('prenume=Ana');

    // Formularul de onboarding preia sugestia din query string (validată/igienizată).
    Livewire::withQueryParams(['rol' => UserRole::Elev->value, 'nume' => 'Popescu', 'prenume' => 'Ana Maria'])
        ->test(CreateUser::class)
        ->assertFormSet([
            'role' => UserRole::Elev->value,
            'last_name' => 'Popescu',
            'first_name' => 'Ana Maria',
        ]);
});

it('datele familiei nu se editează: pagina de editare nu mai există, fișa e read-only', function () {
    $request = AdmissionRequest::factory()->create();

    expect(AdmissionRequestResource::canEdit($request))->toBeFalse();

    // Fișa (View) se deschide și arată datele cererii.
    Livewire::test(ViewAdmissionRequest::class, ['record' => $request->getRouteKey()])
        ->assertOk();
});

it('procesarea rămâne în jurnalul de audit (modelul e Auditable)', function () {
    config(['audit.console' => true]);

    $request = AdmissionRequest::factory()->create();

    app(ProcessAdmissionRequest::class)->enroll($request, $this->director, 'ok');

    expect($request->audits()->where('event', 'updated')->exists())->toBeTrue();
});
