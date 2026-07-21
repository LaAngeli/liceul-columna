<?php

/**
 * Fluxul PROPUS de creare a sesiunii de corigență (standardizarea 2026-07-21): anul = cel curent
 * (sau contextul ?an=), doar ani DESCHIȘI; sezonul propus după momentul din an; perioada
 * PRE-COMPLETATĂ pe fereastra convenției și re-propusă la schimbarea contextului; sfârșitul nu
 * poate preceda începutul (golire la mutare); fereastra anului, dublura (an, sezon, tip) și
 * suprapunerea — respinse pe server ȘI pe model, sub orice cale de scriere.
 */

use App\Enums\CorigentaSeason;
use App\Enums\CorigentaSessionStatus;
use App\Enums\CorigentaSessionType;
use App\Enums\UserRole;
use App\Filament\Resources\CorigentaSessions\Pages\CreateCorigentaSession;
use App\Models\AcademicYear;
use App\Models\CorigentaSession;
use App\Models\User;
use App\Support\SchoolCalendar;
use Filament\Forms\Components\Select;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    // Moment fix (martie → sezonul propus = vară), pe fusul ȘCOLII — altfel testul își schimbă
    // comportamentul după luna în care rulează.
    Carbon::setTestNow(Carbon::parse('2026-03-10 10:00', SchoolCalendar::TIMEZONE));

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::AdministratorOperational->value);
    actingAs($this->admin);

    $this->year = AcademicYear::factory()->create([
        'name' => '2025–2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-06-30',
        'is_current' => true,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('propunerea vine COMPLETĂ: an curent, sezon după calendar, tip bază, perioada pe convenție — crearea merge fără nicio atingere', function () {
    Livewire::test(CreateCorigentaSession::class)
        ->call('create')
        ->assertHasNoFormErrors();

    $session = CorigentaSession::query()->firstOrFail();

    expect($session->academic_year_id)->toBe($this->year->id)
        // Martie → vară; tip bază → ultima săptămână din august a celui de-al doilea an.
        ->and($session->season)->toBe(CorigentaSeason::Vara)
        ->and($session->type)->toBe(CorigentaSessionType::Baza)
        ->and($session->starts_on->format('Y-m-d'))->toBe('2026-08-24')
        ->and($session->ends_on->format('Y-m-d'))->toBe('2026-08-31')
        // Starea inițială = propunere, cu autorul înregistrat.
        ->and($session->status)->toBe(CorigentaSessionStatus::Draft)
        ->and($session->proposed_by_user_id)->toBe($this->admin->id);
});

it('anii ÎNCHIȘI nu apar în opțiuni, iar contextul navigatorului (?an=) pre-completează anul', function () {
    $closed = AcademicYear::factory()->create(['name' => '2030–2031', 'closed_at' => now()]);
    $open = AcademicYear::factory()->create(['name' => '2026–2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30']);

    Livewire::test(CreateCorigentaSession::class)
        ->assertFormFieldExists('academic_year_id', function (Select $field) use ($closed, $open): bool {
            $ids = array_keys($field->getOptions());

            return in_array($this->year->id, $ids, true)
                && in_array($open->id, $ids, true)
                && ! in_array($closed->id, $ids, true);
        });

    $state = Livewire::withQueryParams(['an' => (string) $open->id])
        ->test(CreateCorigentaSession::class)
        ->instance()->form->getRawState();

    expect((int) $state['academic_year_id'])->toBe($open->id);
});

it('schimbarea sezonului sau a tipului RE-PROPUNE perioada pe fereastra convenției', function () {
    // Semestrul II definit → iarnă+repetată se propune chiar pe prima lui săptămână.
    $this->year->terms()->create([
        'number' => 2, 'name' => 'Semestrul II',
        'starts_on' => '2026-01-12', 'ends_on' => '2026-05-31',
    ]);

    $component = Livewire::test(CreateCorigentaSession::class);

    $state = $component->fillForm(['season' => CorigentaSeason::Iarna->value])->instance()->form->getRawState();
    expect($state['starts_on'])->toBe('2025-12-23')->and($state['ends_on'])->toBe('2025-12-30');

    $state = $component->fillForm(['type' => CorigentaSessionType::Repetata->value])->instance()->form->getRawState();
    expect($state['starts_on'])->toBe('2026-01-12')->and($state['ends_on'])->toBe('2026-01-18');
});

it('mutarea începutului DUPĂ sfârșit golește sfârșitul — intervalul răsturnat nu se poate nici selecta', function () {
    $state = Livewire::test(CreateCorigentaSession::class)
        ->fillForm(['starts_on' => '2026-08-24', 'ends_on' => '2026-08-28'])
        ->fillForm(['starts_on' => '2026-08-30'])
        ->instance()->form->getRawState();

    expect($state['ends_on'])->toBeNull();
});

it('POST forjat: interval răsturnat, dată în afara ferestrei anului, dublură și suprapunere — toate respinse', function () {
    // Răsturnat.
    Livewire::test(CreateCorigentaSession::class)
        ->fillForm(['starts_on' => '2026-08-28', 'ends_on' => '2026-08-24'])
        ->call('create')
        ->assertHasFormErrors(['ends_on']);

    // În afara ferestrei anului (2030 pe anul 2025–2026).
    Livewire::test(CreateCorigentaSession::class)
        ->fillForm(['starts_on' => '2030-06-10', 'ends_on' => '2030-06-20'])
        ->call('create')
        ->assertHasFormErrors(['starts_on']);

    CorigentaSession::create([
        'academic_year_id' => $this->year->id,
        'season' => CorigentaSeason::Vara,
        'type' => CorigentaSessionType::Baza,
        'starts_on' => '2026-08-24',
        'ends_on' => '2026-08-31',
        'status' => CorigentaSessionStatus::Draft,
    ]);

    // Dublura (an, sezon, tip).
    Livewire::test(CreateCorigentaSession::class)
        ->fillForm([
            'season' => CorigentaSeason::Vara->value,
            'type' => CorigentaSessionType::Baza->value,
            'starts_on' => '2026-06-10',
            'ends_on' => '2026-06-15',
        ])
        ->call('create')
        ->assertHasFormErrors(['ends_on']);

    // Suprapunere cu sesiunea existentă (combinație diferită, interval peste a ei).
    Livewire::test(CreateCorigentaSession::class)
        ->fillForm([
            'season' => CorigentaSeason::Vara->value,
            'type' => CorigentaSessionType::Repetata->value,
            'starts_on' => '2026-08-28',
            'ends_on' => '2026-09-02',
        ])
        ->call('create')
        ->assertHasFormErrors(['ends_on']);

    expect(CorigentaSession::query()->count())->toBe(1);
});

it('gărzile de MODEL prind orice cale: răsturnat, dublură, suprapunere, an închis', function () {
    $base = [
        'academic_year_id' => $this->year->id,
        'season' => CorigentaSeason::Vara,
        'type' => CorigentaSessionType::Baza,
        'status' => CorigentaSessionStatus::Draft,
    ];

    // Interval răsturnat, direct prin model.
    expect(fn () => CorigentaSession::create($base + ['starts_on' => '2026-08-28', 'ends_on' => '2026-08-20']))
        ->toThrow(ValidationException::class);

    CorigentaSession::create($base + ['starts_on' => '2026-08-24', 'ends_on' => '2026-08-31']);

    // Dublura aceleiași combinații.
    expect(fn () => CorigentaSession::create($base + ['starts_on' => '2026-06-01', 'ends_on' => '2026-06-05']))
        ->toThrow(ValidationException::class);

    // Suprapunere cu sesiunea existentă (combinație diferită).
    expect(fn () => CorigentaSession::create([
        'academic_year_id' => $this->year->id,
        'season' => CorigentaSeason::Vara,
        'type' => CorigentaSessionType::Repetata,
        'starts_on' => '2026-08-30',
        'ends_on' => '2026-09-03',
        'status' => CorigentaSessionStatus::Draft,
    ]))->toThrow(ValidationException::class);

    // An închis — nu mai primește sesiuni.
    $closed = AcademicYear::factory()->create(['name' => '2031–2032', 'closed_at' => now()]);

    expect(fn () => CorigentaSession::create([
        'academic_year_id' => $closed->id,
        'season' => CorigentaSeason::Iarna,
        'type' => CorigentaSessionType::Baza,
        'starts_on' => '2031-12-23',
        'ends_on' => '2031-12-30',
        'status' => CorigentaSessionStatus::Draft,
    ]))->toThrow(ValidationException::class);

    // Istoricul NEATINS rămâne editabil: schimbarea stării nu re-deșteaptă gărzile de combinație.
    $existing = CorigentaSession::query()->firstOrFail();
    $existing->update(['status' => CorigentaSessionStatus::Approved, 'order_reference' => 'Ordin 12/2026']);

    expect($existing->refresh()->status)->toBe(CorigentaSessionStatus::Approved);
});

it('mesajele și etichetele există în toate cele trei limbi', function () {
    foreach (['ro', 'ru', 'en'] as $locale) {
        app()->setLocale($locale);

        foreach ([
            'panel.forms.corigenta_session.section_context',
            'panel.forms.corigenta_session.section_period',
            'panel.forms.corigenta_session.flow_info',
            'panel.forms.corigenta_session.windows_info',
            'panel.forms.corigenta_session.exams_info',
            'panel.forms.corigenta_session.season_window_warning',
            'panel.validation.corigenta_session.dates_inverted',
            'panel.validation.corigenta_session.outside_year_window',
            'panel.validation.corigenta_session.duplicate',
            'panel.validation.corigenta_session.overlap',
            'panel.validation.corigenta_session.overlap_short',
            'panel.validation.corigenta_session.year_closed',
        ] as $key) {
            expect(__($key))->not->toBe($key, "Cheia {$key} lipsește pe {$locale}");
        }
    }

    app()->setLocale('ro');
});
