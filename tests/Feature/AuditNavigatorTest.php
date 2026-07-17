<?php

/**
 * Jurnalul de audit SECȚIONAT pe categorii (2026-07-17): carduri pe categoria de date (cu
 * numărători + pulsul azi/7 zile) → tabel în contextul categoriei; bucket-ul „Altele" prinde
 * tipurile nemapate; minimizarea administratorului tehnic se aplică ȘI cardurilor; fiecare
 * model auditat are etichetă tradusă și categorie (disciplina de mapare e păzită de test).
 */

use App\Enums\UserRole;
use App\Filament\Resources\Audits\Pages\ListAudits;
use App\Models\AbsenceMotivation;
use App\Models\Audit;
use App\Models\Grade;
use App\Models\Post;
use App\Models\Student;
use App\Models\User;
use App\Support\AuditCategories;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use OwenIt\Auditing\Contracts\Auditable;
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
function insertAudit(string $auditableType, array $overrides = []): int
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

it('aterizarea arată categoriile cu numărători; „Altele" doar când există tipuri nemapate', function () {
    insertAudit(Grade::class);
    insertAudit(Grade::class, ['created_at' => now()->subDays(10)]);
    insertAudit(User::class);
    insertAudit(Post::class);

    $cards = collect(Livewire::test(ListAudits::class)->instance()->categoryCards());

    // Cele 5 categorii fixe, fără „Altele" (nimic nemapat încă).
    expect($cards->pluck('id')->all())->toBe(AuditCategories::keys());

    $catalog = $cards->firstWhere('id', 'catalog');
    expect($catalog['stats'][0])->toContain('2')
        // Pulsul: una din intrările catalogului e veche de 10 zile → în „7 zile" intră doar una.
        ->and($catalog['stats'][1])->toContain('1')
        ->and($catalog['badge'])->not->toBeNull();

    // Singularul RO nu conține cifra („o intrare") → comparăm cu traducerea însăși.
    $one = (string) trans_choice('panel.audit_nav.entries_count', 1, ['count' => 1]);

    expect($cards->firstWhere('id', 'conturi')['stats'][0])->toBe($one)
        ->and($cards->firstWhere('id', 'continut')['stats'][0])->toBe($one);

    // Un tip NEMAPAT (model viitor) aterizează vizibil în „Altele", nu dispare din jurnal.
    insertAudit('App\\Models\\ModelViitor');

    $cards = collect(Livewire::test(ListAudits::class)->instance()->categoryCards());
    expect($cards->pluck('id')->all())->toBe([...AuditCategories::keys(), AuditCategories::OTHER]);
});

it('contextul unei categorii filtrează tabelul la tipurile ei; o categorie inventată nu deschide context', function () {
    $gradeAudit = Audit::query()->find(insertAudit(Grade::class));
    $userAudit = Audit::query()->find(insertAudit(User::class));
    $strayAudit = Audit::query()->find(insertAudit('App\\Models\\ModelViitor'));

    Livewire::withQueryParams(['categorie' => 'catalog'])
        ->test(ListAudits::class)
        ->assertCanSeeTableRecords([$gradeAudit])
        ->assertCanNotSeeTableRecords([$userAudit, $strayAudit]);

    // Bucket-ul „Altele" = complementul tipurilor mapate.
    Livewire::withQueryParams(['categorie' => AuditCategories::OTHER])
        ->test(ListAudits::class)
        ->assertCanSeeTableRecords([$strayAudit])
        ->assertCanNotSeeTableRecords([$gradeAudit, $userAudit]);

    expect(Livewire::withQueryParams(['categorie' => 'categorie-inventata'])->test(ListAudits::class)->instance()->activeCategory())
        ->toBeNull();
});

it('minimizarea administratorului tehnic se aplică și cardurilor, nu doar tabelului', function () {
    insertAudit(Grade::class);
    insertAudit(AbsenceMotivation::class);
    insertAudit(Student::class);

    $one = (string) trans_choice('panel.audit_nav.entries_count', 1, ['count' => 1]);
    $zero = (string) trans_choice('panel.audit_nav.entries_count', 0, ['count' => 0]);

    // Directorul vede tot catalogul (2) + dosarele elevilor (1).
    $directorCards = collect(Livewire::test(ListAudits::class)->instance()->categoryCards());
    expect($directorCards->firstWhere('id', 'catalog')['stats'][0])->toContain('2')
        ->and($directorCards->firstWhere('id', 'elevi')['stats'][0])->toBe($one);

    // AT: datele academice (Grade, Student) dispar și din numărători — rămâne doar motivarea.
    $at = User::factory()->create();
    $at->assignRole(UserRole::AdministratorTehnic->value);
    actingAs($at);

    $atCards = collect(Livewire::test(ListAudits::class)->instance()->categoryCards());
    expect($atCards->firstWhere('id', 'catalog')['stats'][0])->toBe($one)
        ->and($atCards->firstWhere('id', 'elevi')['stats'][0])->toBe($zero);
});

it('fiecare model auditat e mapat într-o categorie și are etichetă tradusă (disciplina de mapare)', function () {
    $mapped = AuditCategories::allMapped();

    // Toate modelele care implementează Auditable sunt încadrate + etichetate (un model NOU
    // auditabil pică acest test până primește categorie și etichetă — nu ajunge tăcut în „Altele").
    foreach (glob(app_path('Models/*.php')) as $file) {
        $class = 'App\\Models\\'.basename($file, '.php');

        if (! class_exists($class) || ! is_subclass_of($class, Auditable::class)) {
            continue;
        }

        expect($mapped)->toContain($class);

        $key = 'panel.audit_types.'.class_basename($class);
        expect(Lang::has($key))->toBeTrue("Lipsește eticheta {$key}");
    }

    // Etichetele filtrului folosesc aceeași sursă.
    expect(Audit::labelForType(Grade::class))->toBe(__('panel.audit_types.Grade'));
});
