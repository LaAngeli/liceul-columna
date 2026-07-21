<?php

/**
 * Jurnalul de audit ca INSTRUMENT DE INVESTIGARE (restructurarea 2026-07-21): fișa intrării
 * arată totul pe loc (actor + rol, moment, diff vechi→nou, context tehnic, n/a la lipsuri);
 * imuabilitate la nivel de model + politică (nimeni nu editează/șterge prin aplicație, nici
 * super-adminul) cu bulk-ul de consolă păstrat deliberat (retenție/purge); severitate derivată
 * filtrabilă; acoperire unificată (structura școlii scrie și ea în jurnal); scoping-ul AT
 * se aplică și fișei (404, nu confirmă existența).
 */

use App\Enums\UserRole;
use App\Filament\Resources\Audits\Pages\ListAudits;
use App\Filament\Resources\Audits\Pages\ViewAudit;
use App\Models\Audit;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
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

/** Inserează o intrare de jurnal direct (scrierea reală e a pachetului owen-it). */
function ajInsertAudit(string $auditableType, array $overrides = []): int
{
    return (int) DB::table('audits')->insertGetId([
        'user_type' => null,
        'user_id' => null,
        'event' => 'updated',
        'auditable_type' => $auditableType,
        'auditable_id' => 1,
        'old_values' => '[]',
        'new_values' => '[]',
        'url' => null,
        'ip_address' => null,
        'user_agent' => null,
        'tags' => null,
        'created_at' => now(),
        'updated_at' => now(),
        ...$overrides,
    ]);
}

it('jurnalul e imuabil prin instanțe de model: update și delete aruncă excepție', function () {
    $audit = Audit::query()->findOrFail(ajInsertAudit(Grade::class));

    expect(fn () => $audit->update(['event' => 'created']))
        ->toThrow(LogicException::class);

    expect(fn () => $audit->delete())
        ->toThrow(LogicException::class);

    // Intrarea a rămas neatinsă.
    expect(Audit::query()->whereKey($audit->getKey())->value('event'))->toBe('updated');
});

it('curățarea prin query builder rămâne posibilă (calea deliberată a retenției/purge-ului din consolă)', function () {
    $id = ajInsertAudit(Grade::class);

    // Bulk delete pe builder NU trece prin evenimentele de model — exact bypass-ul documentat
    // pe care se sprijină PurgeDemoData și retenția legală. Testul îl ține sub contract.
    Audit::query()->whereKey($id)->delete();

    expect(Audit::query()->whereKey($id)->exists())->toBeFalse();
});

it('politica refuză orice scriere pe jurnal, inclusiv super-adminului; vizualizarea urmează capabilitatea', function () {
    $audit = Audit::query()->findOrFail(ajInsertAudit(Grade::class));

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::Admin->value);

    expect(Gate::forUser($superAdmin)->allows('update', $audit))->toBeFalse()
        ->and(Gate::forUser($superAdmin)->allows('delete', $audit))->toBeFalse()
        ->and(Gate::forUser($superAdmin)->allows('forceDelete', $audit))->toBeFalse()
        ->and(Gate::forUser($this->director)->allows('view', $audit))->toBeTrue();

    // Profesorul nu are capabilitatea de jurnal (matricea §3.3).
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);

    expect(Gate::forUser($profesor)->allows('viewAny', Audit::class))->toBeFalse();
});

it('fișa intrării arată diff-ul vechi→nou, actorul cu rolul lui și n/a la valorile lipsă', function () {
    $profesor = User::factory()->create(['name' => 'Ion Investigatu']);
    $profesor->assignRole(UserRole::Profesor->value);

    $id = ajInsertAudit(Grade::class, [
        'user_type' => User::class,
        'user_id' => $profesor->id,
        'old_values' => json_encode(['value' => 7]),
        'new_values' => json_encode(['value' => 9]),
        'ip_address' => null,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/126.0 Safari/537.36',
    ]);

    Livewire::test(ViewAudit::class, ['record' => $id])
        ->assertSee('Ion Investigatu')
        ->assertSee(UserRole::Profesor->label())
        ->assertSee('7')
        ->assertSee('9')
        // IP lipsă = n/a (nu eroare, nu câmp ascuns).
        ->assertSee('n/a')
        // Dispozitivul derivat din user agent.
        ->assertSee('Chrome · Windows')
        ->assertSee(__('panel.audit_view.immutable_note'));
});

it('intrarea de ACCES (viewed) își arată contextul consultării în fișă', function () {
    $id = ajInsertAudit(Student::class, [
        'event' => 'viewed',
        'new_values' => json_encode(['detaliu' => 'Consultare fișă elev din panou']),
    ]);

    Livewire::test(ViewAudit::class, ['record' => $id])
        ->assertSee(__('panel.audit_view.access_context'))
        ->assertSee('Consultare fișă elev din panou');
});

it('actorul de sistem și contul șters se disting onest în fișă', function () {
    // user_id NULL = acțiune de sistem (consolă/scheduler).
    $systemEntry = ajInsertAudit(Grade::class);

    Livewire::test(ViewAudit::class, ['record' => $systemEntry])
        ->assertSee(__('panel.common.system'));

    // user_id orfan (cont șters între timp) — intrarea rămâne, autorul e numit onest.
    $orphanEntry = ajInsertAudit(Grade::class, [
        'user_type' => User::class,
        'user_id' => 999999,
    ]);

    Livewire::test(ViewAudit::class, ['record' => $orphanEntry])
        ->assertSee(__('panel.audit_view.deleted_user'))
        ->assertSee(__('panel.audit_view.deleted_user_hint'));
});

it('fișa unei intrări academice nu există pentru administratorul tehnic (404), dar se deschide pentru director', function () {
    $academic = ajInsertAudit(Grade::class);
    $neacademic = ajInsertAudit(User::class);

    $at = User::factory()->create();
    $at->assignRole(UserRole::AdministratorTehnic->value);

    // Minimizarea AT (§3.3 ◐) se aplică și fișei — 404, nu confirmă nici existența intrării.
    $this->actingAs($at)
        ->get("/admin/audits/{$academic}")
        ->assertNotFound();

    $this->actingAs($at)
        ->get("/admin/audits/{$neacademic}")
        ->assertOk();

    $this->actingAs($this->director)
        ->get("/admin/audits/{$academic}")
        ->assertOk();
});

it('filtrul de severitate grupează evenimentele pe trepte derivate', function () {
    $deleted = Audit::query()->findOrFail(ajInsertAudit(Grade::class, ['event' => 'deleted']));
    $updated = Audit::query()->findOrFail(ajInsertAudit(Grade::class, ['event' => 'updated']));
    $created = Audit::query()->findOrFail(ajInsertAudit(Grade::class, ['event' => 'created']));

    Livewire::withQueryParams(['categorie' => 'catalog'])
        ->test(ListAudits::class)
        ->filterTable('severity', 'danger')
        ->assertCanSeeTableRecords([$deleted])
        ->assertCanNotSeeTableRecords([$updated, $created]);

    // Harta severității acoperă exact evenimentele cunoscute, în oglindă cu severityForEvent.
    foreach (Audit::severityMap() as $severity => $events) {
        foreach ($events as $event) {
            expect(Audit::severityForEvent($event))->toBe($severity);
        }
    }
});

it('rândul tabelului deschide FIȘA intrării, nu alte module (recordUrl)', function () {
    $id = ajInsertAudit(Grade::class);

    Livewire::withQueryParams(['categorie' => 'catalog'])
        ->test(ListAudits::class)
        ->assertSee("/admin/audits/{$id}");
});

it('structura școlii scrie în jurnal (acoperire unificată): modificarea unui semestru lasă urmă', function () {
    // Auditarea din teste/consolă e oprită implicit (audit.console=false).
    config(['audit.console' => true]);

    $term = Term::factory()->create();
    $term->update(['name' => 'Semestrul I (redenumit)']);

    $entry = Audit::query()
        ->where('auditable_type', Term::class)
        ->where('auditable_id', $term->id)
        ->where('event', 'updated')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->new_values)->toHaveKey('name');
});

it('etichetele fișei și tipurile nou-auditate există în toate cele trei limbi', function () {
    $newTypes = [
        'HomeworkAssignment', 'AcademicYear', 'Term', 'SchoolClass',
        'Subject', 'TeachingAssignment', 'Schedule', 'Teacher',
    ];

    foreach (['ro', 'ru', 'en'] as $locale) {
        expect(Lang::hasForLocale('panel.audit_view.title', $locale))->toBeTrue("Lipsește panel.audit_view.title [{$locale}]")
            ->and(Lang::hasForLocale('panel.audit_nav.categories.scoala', $locale))->toBeTrue("Lipsește categoria scoala [{$locale}]")
            ->and(Lang::hasForLocale('panel.audit_nav.descriptions.scoala', $locale))->toBeTrue("Lipsește descrierea scoala [{$locale}]");

        foreach ($newTypes as $type) {
            expect(Lang::hasForLocale('panel.audit_types.'.$type, $locale))
                ->toBeTrue("Lipsește panel.audit_types.{$type} [{$locale}]");
        }
    }
});
