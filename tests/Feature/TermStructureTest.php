<?php

/**
 * Restructurarea secțiunii „Semestre" (2026-07-21): gărzile de integritate ale structurii anului
 * (unicitate număr/an, an închis = înghețat, ștergerea semestrului curent/cu istoric refuzată),
 * sincronizarea semestrului curent din pagină și axa anului cu semnalele ei.
 */

use App\Actions\SyncCurrentTermFlag;
use App\Enums\UserRole;
use App\Filament\Resources\Terms\Pages\CreateTerm;
use App\Filament\Resources\Terms\Pages\ListTerms;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function tsConfigurator(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::AdministratorOperational->value);

    return $user;
}

/** @return array{0: AcademicYear, 1: Term, 2: Term} */
function tsYearWithTerms(): array
{
    $year = AcademicYear::factory()->create([
        'name' => '2025–2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31',
    ]);
    $semI = Term::factory()->for($year)->create([
        'number' => 1, 'name' => 'Semestrul I',
        'starts_on' => '2025-09-01', 'ends_on' => '2025-12-31', 'is_current' => false,
    ]);
    $semII = Term::factory()->for($year)->create([
        'number' => 2, 'name' => 'Semestrul II',
        'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'is_current' => true,
    ]);

    return [$year, $semI, $semII];
}

// ─── Unicitatea numărului pe an ─────────────────────────────────────────────────────────

it('refuză al doilea semestru cu același număr în același an', function () {
    [$year] = tsYearWithTerms();

    actingAs(tsConfigurator());

    Livewire::test(CreateTerm::class)
        ->fillForm([
            'academic_year_id' => $year->id,
            'number' => 1,
            'name' => 'Semestrul I bis',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-08-20',
        ])
        ->call('create')
        ->assertHasFormErrors(['number']);

    expect(Term::query()->where('academic_year_id', $year->id)->count())->toBe(2);
});

it('permite același număr de semestru în ALT an', function () {
    tsYearWithTerms();
    $other = AcademicYear::factory()->create([
        'name' => '2026–2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31',
    ]);

    actingAs(tsConfigurator());

    Livewire::test(CreateTerm::class)
        ->fillForm([
            'academic_year_id' => $other->id,
            'number' => 1,
            'name' => 'Semestrul I',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-12-31',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Term::query()->where('academic_year_id', $other->id)->count())->toBe(1);
});

// ─── Anul închis îngheață structura ─────────────────────────────────────────────────────

it('anul ÎNCHIS nu primește semestre noi, iar cele existente nu se mai editează/șterg', function () {
    [$year, $semI] = tsYearWithTerms();
    $year->update(['closed_at' => now()]);

    $configurator = tsConfigurator();
    actingAs($configurator);

    // Crearea: regula de server respinge anul închis (selectul nici nu-l mai oferă).
    Livewire::test(CreateTerm::class)
        ->fillForm([
            'academic_year_id' => $year->id,
            'number' => 1,
            'name' => 'Semestrul I',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['academic_year_id']);

    // Politica: fără editare/ștergere pe structura înghețată (butoanele Filament dispar).
    expect($configurator->can('update', $semI))->toBeFalse()
        ->and($configurator->can('delete', $semI))->toBeFalse();

    // Garda de MODEL prinde orice cale de ștergere ocolită de UI.
    expect(fn () => $semI->delete())->toThrow(ValidationException::class);
});

// ─── Gărzile de ștergere ────────────────────────────────────────────────────────────────

it('semestrul CURENT nu poate fi șters pe nicio cale', function () {
    [, , $semII] = tsYearWithTerms();
    $configurator = tsConfigurator();

    expect($configurator->can('delete', $semII))->toBeFalse()
        ->and(fn () => $semII->delete())->toThrow(ValidationException::class)
        ->and(Term::query()->whereKey($semII->id)->exists())->toBeTrue();
});

it('semestrul cu ISTORIC academic nu poate fi șters; unul gol, ne-curent, da', function () {
    [$year, $semI] = tsYearWithTerms();
    $configurator = tsConfigurator();

    $class = SchoolClass::factory()->for($year)->create();
    Grade::factory()->create([
        'student_id' => Student::factory()->create()->id,
        'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $class->id,
        'term_id' => $semI->id,
    ]);

    expect($semI->hasAcademicHistory())->toBeTrue()
        ->and($configurator->can('delete', $semI))->toBeFalse()
        ->and(fn () => $semI->delete())->toThrow(ValidationException::class);

    // Un semestru gol și ne-curent rămâne curățabil (rând creat din greșeală) — într-un an nou
    // (anul are exact DOUĂ semestre; un al treilea nu mai poate exista).
    $emptyYear = AcademicYear::factory()->create(['starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
    $empty = Term::factory()->for($emptyYear)->create([
        'number' => 1, 'name' => 'Semestrul I',
        'starts_on' => '2026-09-01', 'ends_on' => '2026-12-20', 'is_current' => false,
    ]);

    expect($configurator->can('delete', $empty))->toBeTrue();
    $empty->delete();
    expect($empty->fresh()->trashed())->toBeTrue();
});

// ─── Sincronizarea semestrului curent ───────────────────────────────────────────────────

it('acțiunea de sincronizare din pagină corectează flag-ul stale și oglindește anul', function () {
    [$year, $semI, $semII] = tsYearWithTerms();
    // Flag-ul a rămas pe Sem I deși calendarul e în Sem II (scheduler oprit).
    $semII->update(['is_current' => false]);
    $semI->update(['is_current' => true]);
    $year->update(['is_current' => false]);

    $this->travelTo(Carbon::parse('2026-03-10'));

    actingAs(tsConfigurator());

    $page = Livewire::test(ListTerms::class);
    expect($page->instance()->isCurrentStale())->toBeTrue();

    $page->callAction('syncCurrentTerm')->assertNotified();

    expect($semII->refresh()->is_current)->toBeTrue()
        ->and($semI->refresh()->is_current)->toBeFalse()
        ->and($year->refresh()->is_current)->toBeTrue();
});

it('acțiunea de sincronizare nu apare când flag-ul e deja corect sau utilizatorul nu configurează', function () {
    tsYearWithTerms();
    $this->travelTo(Carbon::parse('2026-03-10'));

    actingAs(tsConfigurator());
    Livewire::test(ListTerms::class)->assertActionHidden('syncCurrentTerm');

    // Directorul-adjunct vede pagina (administrator), dar nu configurează → fără buton, chiar stale.
    Term::query()->where('is_current', true)->update(['is_current' => false]);
    $viewer = User::factory()->create();
    $viewer->assignRole(UserRole::PrimVicedirector->value);
    actingAs($viewer);

    Livewire::test(ListTerms::class)->assertActionHidden('syncCurrentTerm');
});

it('SyncCurrentTermFlag e idempotentă și lasă exact un semestru curent', function () {
    tsYearWithTerms();
    $this->travelTo(Carbon::parse('2025-10-15'));

    $sync = app(SyncCurrentTermFlag::class);
    $sync->run();
    $sync->run();

    expect(Term::query()->where('is_current', true)->count())->toBe(1)
        ->and(Term::query()->where('is_current', true)->value('number'))->toBe(1);
});

// ─── Axa anului + semnalele ─────────────────────────────────────────────────────────────

it('pagina arată axa anului, cardurile semestrelor și semnalul pentru semestrul fără interval', function () {
    [$year] = tsYearWithTerms();
    // Stare MOȘTENITĂ (un „al treilea semestru" fără interval nu mai poate fi produs de model —
    // anul are exact două semestre): construită prin query builder, ca datele legacy.
    DB::table('terms')->insert([
        'academic_year_id' => $year->id, 'number' => 3, 'name' => 'Semestru rătăcit',
        'starts_on' => null, 'ends_on' => null, 'is_current' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->travelTo(Carbon::parse('2026-03-10'));

    actingAs(tsConfigurator());

    Livewire::test(ListTerms::class)
        ->assertSee(__('panel.terms_axis.axis_label'))
        ->assertSee('Semestrul I')
        ->assertSee('Semestrul II')
        ->assertSee(__('panel.terms_axis.status.current'))
        // Semnalul pentru semestrul fără interval, cu numele lui.
        ->assertSee('Semestru rătăcit');
});

it('semnalează evaluările datate în afara semestrului lor (drift)', function () {
    [$year, $semI] = tsYearWithTerms();
    $class = SchoolClass::factory()->for($year)->create();

    // Notă legată de Sem I dar datată în Sem II — exact ce lasă în urmă o mutare de granițe.
    Grade::factory()->create([
        'student_id' => Student::factory()->create()->id,
        'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $class->id,
        'term_id' => $semI->id,
        'graded_on' => '2026-02-10',
    ]);

    $this->travelTo(Carbon::parse('2026-03-10'));

    actingAs(tsConfigurator());

    $page = Livewire::test(ListTerms::class);

    $drift = collect($page->instance()->integrity())
        ->first(fn (array $signal): bool => str_contains($signal['text'], 'realiniaz'));

    expect($drift)->not->toBeNull();
});
