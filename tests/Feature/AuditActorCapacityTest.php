<?php

/**
 * JURNALUL CONSEMNEAZĂ CALITATEA, nu doar identitatea (spec §3.1, cerință 2026-07-27).
 *
 * Problema pe care o rezolvă: până acum jurnalul reținea doar `user_id`, iar fișa afișa rolul
 * CURENT al contului. Consecința — un profesor promovat director apărea retroactiv ca director în
 * toate intrările lui vechi, iar pentru dirigenție (reatribuită între ani) nu se mai putea
 * reconstitui de unde venise dreptul. Testele fixează instantaneul: rolul ȘI funcțiile care dau
 * drepturi peste el se scriu la momentul acțiunii și nu se recalculează niciodată.
 */

use App\Enums\AudienceDomain;
use App\Enums\UserRole;
use App\Filament\Resources\Audits\Pages\ViewAudit;
use App\Models\Audit;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    // Auditarea e OPRITĂ în consolă (config audit.console) — testele o pornesc explicit.
    config(['audit.console' => true]);
});

/** Cont de personal didactic cu fișă proprie, opțional diriginte al claselor date. */
function auditTeacher(string $role, SchoolClass ...$homeroom): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);

    foreach ($homeroom as $class) {
        $class->update(['homeroom_teacher_id' => $teacher->id]);
    }

    return $user->fresh();
}

/** O modificare oarecare pe un model auditabil → intrarea de jurnal produsă. */
function auditLastEntryAfterChange(): Audit
{
    $subject = Subject::factory()->create();
    $subject->update(['name' => $subject->name.' (modificat)']);

    return Audit::query()->where('event', 'updated')->latest('id')->firstOrFail();
}

it('consemnează ROLUL de la momentul acțiunii, nu pe cel de mai târziu', function () {
    $user = auditTeacher(UserRole::Profesor->value);
    actingAs($user);

    $entry = auditLastEntryAfterChange();

    expect($entry->actor_role)->toBe(UserRole::Profesor->value);

    // Promovarea de mai târziu NU rescrie istoria: intrarea rămâne pe rolul de atunci.
    $user->syncRoles([UserRole::Director->value]);

    expect($entry->fresh()->actor_role)->toBe(UserRole::Profesor->value)
        ->and($entry->fresh()->actorRoleLabel())->toBe(UserRole::Profesor->label());
});

it('consemnează DIRIGENȚIA ca sursă a dreptului, cu clasele de atunci', function () {
    $classA = SchoolClass::factory()->create(['name' => 'XI', 'section' => 'R', 'grade_level' => 11]);
    $classB = SchoolClass::factory()->create(['name' => 'X', 'section' => 'U', 'grade_level' => 10]);

    // Cazul din raportarea beneficiarului: ROL „profesor", dar DESEMNAT diriginte.
    $user = auditTeacher(UserRole::Profesor->value, $classA, $classB);
    actingAs($user);

    $entry = auditLastEntryAfterChange();

    expect($entry->actor_role)->toBe(UserRole::Profesor->value)
        ->and($entry->actor_capacity)->toContain('diriginte:');

    $capacities = $entry->actorCapacities();

    expect($capacities)->toHaveCount(1)
        ->and($capacities[0]['label'])->toBe(trans('panel.audit_view.capacity.diriginte'))
        // Ambele clase, în ordinea din etichetă (treaptă, apoi nume).
        ->and($capacities[0]['detail'])->toBe('X U, XI R');

    // Reatribuirea dirigenției nu atinge intrarea deja scrisă.
    $classA->update(['homeroom_teacher_id' => null]);
    $classB->update(['homeroom_teacher_id' => null]);

    expect($entry->fresh()->actorCapacities()[0]['detail'])->toBe('X U, XI R');
});

it('consemnează DOMENIUL de audiență al conducerii', function () {
    $user = User::factory()->create(['audience_domains' => [AudienceDomain::Educatie->value]]);
    $user->assignRole(UserRole::PrimVicedirector->value);
    actingAs($user->fresh());

    $entry = auditLastEntryAfterChange();

    expect($entry->actor_role)->toBe(UserRole::PrimVicedirector->value)
        ->and($entry->actorCapacities())->toHaveCount(1)
        ->and($entry->actorCapacities()[0]['label'])->toBe(trans('panel.audit_view.capacity.domeniu'))
        ->and($entry->actorCapacities()[0]['detail'])->toBe(AudienceDomain::Educatie->value);
});

it('nu inventează calitate acolo unde nu există', function () {
    $user = auditTeacher(UserRole::Profesor->value);
    actingAs($user);

    $entry = auditLastEntryAfterChange();

    expect($entry->actor_capacity)->toBeNull()
        ->and($entry->actorCapacities())->toBe([]);
});

it('acțiunile de sistem rămân fără actor și fără calitate', function () {
    // Fără actingAs: nimeni autentificat → jurnalul nu completează cu presupuneri.
    $entry = auditLastEntryAfterChange();

    expect($entry->user_id)->toBeNull()
        ->and($entry->actor_role)->toBeNull()
        ->and($entry->actor_capacity)->toBeNull();
});

it('cumulează dirigenția și domeniul când persoana le are pe amândouă', function () {
    $class = SchoolClass::factory()->create(['name' => 'IX', 'section' => 'A', 'grade_level' => 9]);

    $user = User::factory()->create(['audience_domains' => [AudienceDomain::Educatie->value]]);
    $user->assignRole(UserRole::PrimVicedirector->value);
    Teacher::factory()->create(['user_id' => $user->id]);
    $class->update(['homeroom_teacher_id' => $user->fresh()->teacher->id]);

    actingAs($user->fresh());

    $entry = auditLastEntryAfterChange();

    expect($entry->actorCapacities())->toHaveCount(2)
        ->and($entry->actorCapacities()[0]['detail'])->toBe('IX A')
        ->and($entry->actorCapacities()[1]['detail'])->toBe(AudienceDomain::Educatie->value);
});

it('fișa afișează calitatea consemnată, iar intrările vechi sunt marcate ca istorice', function () {
    $class = SchoolClass::factory()->create(['name' => 'XI', 'section' => 'R', 'grade_level' => 11]);
    $user = auditTeacher(UserRole::Profesor->value, $class);
    actingAs($user);

    $entry = auditLastEntryAfterChange();

    $page = new ViewAudit;
    $page->record = $entry;
    $actor = $page->actor();

    expect($actor['role'])->toBe(UserRole::Profesor->label())
        ->and($actor['historical'])->toBeFalse()
        ->and($actor['capacities'])->toHaveCount(1)
        ->and($actor['capacities'][0]['detail'])->toBe('XI R');

    // Intrare dinaintea consemnării (coloane null): fișa cade pe rolul CURENT și o spune.
    Audit::query()->whereKey($entry->getKey())->update(['actor_role' => null, 'actor_capacity' => null]);

    $page->record = $entry->fresh();
    $legacyActor = $page->actor();

    expect($legacyActor['historical'])->toBeTrue()
        ->and($legacyActor['role'])->toBe(UserRole::Profesor->label())
        ->and($legacyActor['capacities'])->toBe([]);
});

it('randează calitatea în fișa din panou', function () {
    $class = SchoolClass::factory()->create(['name' => 'XI', 'section' => 'R', 'grade_level' => 11]);
    $actor = auditTeacher(UserRole::Profesor->value, $class);
    actingAs($actor);

    $entry = auditLastEntryAfterChange();

    // Jurnalul se citește de administrație, nu de autorul acțiunii.
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);
    actingAs($admin);

    Livewire::test(ViewAudit::class, ['record' => $entry->getKey()])
        ->assertOk()
        ->assertSee(trans('panel.audit_view.capacity.diriginte'))
        ->assertSee('XI R')
        ->assertSee(trans('panel.audit_view.capacity_hint'));
});
