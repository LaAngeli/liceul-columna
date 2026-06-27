<?php

use App\Enums\UserRole;
use App\Filament\Resources\Audits\AuditResource;
use App\Models\Audit;
use App\Models\Student;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('jurnalul de audit din panou e accesibil conducerii, dar nu profesorului', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);

    $this->actingAs($director)->get('/admin/audits')->assertOk();
    $this->actingAs($profesor)->get('/admin/audits')->assertForbidden();
});

it('vizualizarea unui dosar de elev de către personal e jurnalizată (L133 §7)', function () {
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Profesor->value);
    $student = Student::factory()->create();

    // Auditarea e oprită în consolă implicit (config audit.console=false); o pornim doar pentru
    // acțiunea testată, DUPĂ ce fixtura e creată (ca să nu auditeze și `created`-ul elevului).
    config(['audit.console' => true]);

    $this->actingAs($staff)->get("/cabinet/elev/{$student->id}")->assertOk();

    $audit = Audit::query()
        ->where('auditable_type', Student::class)
        ->where('auditable_id', $student->id)
        ->where('event', 'viewed')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((int) $audit->user_id)->toBe($staff->id);
});

it('familia care-și vede propriul copil NU intră în jurnalul de acces', function () {
    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    config(['audit.console' => true]);

    $this->actingAs($parent)->get("/cabinet/elev/{$student->id}")->assertOk();

    expect(Audit::query()->where('event', 'viewed')->count())->toBe(0);
});

it('administratorul tehnic NU vede auditul datelor academice (scoping ◐); directorul vede tot', function () {
    config(['audit.console' => true]);

    // Auditul creării elevului (date academice/PII).
    $student = Student::factory()->create();

    $at = User::factory()->create();
    $at->assignRole(UserRole::AdministratorTehnic->value);
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $this->actingAs($director);
    expect(AuditResource::getEloquentQuery()->where('auditable_type', Student::class)->exists())->toBeTrue();

    $this->actingAs($at);
    expect(AuditResource::getEloquentQuery()->where('auditable_type', Student::class)->exists())->toBeFalse();
});
