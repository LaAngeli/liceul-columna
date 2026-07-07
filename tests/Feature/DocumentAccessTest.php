<?php

use App\Enums\DocumentAccessLevel;
use App\Enums\UserRole;
use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function docUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

// ─── Vizibilitate la nivel de rând (isVisibleTo) ────────────────────────────────────────

it('documentul public e vizibil oricărui rol', function () {
    $doc = Document::factory()->create(['is_published' => true, 'access_level' => DocumentAccessLevel::Public]);

    expect($doc->isVisibleTo(docUser(UserRole::Profesor->value)))->toBeTrue()
        ->and($doc->isVisibleTo(docUser(UserRole::Elev->value)))->toBeTrue();
});

it('documentul rol-specific e vizibil DOAR rolurilor țintă', function () {
    $doc = Document::factory()->forRoles(UserRole::Director->value)->create(['is_published' => true]);

    expect($doc->isVisibleTo(docUser(UserRole::Director->value)))->toBeTrue()
        ->and($doc->isVisibleTo(docUser(UserRole::Profesor->value)))->toBeFalse();
});

it('documentul nepublicat e ascuns nemanagerilor, vizibil administrației care gestionează', function () {
    $doc = Document::factory()->draft()->create(['access_level' => DocumentAccessLevel::Public]);

    expect($doc->isVisibleTo(docUser(UserRole::Profesor->value)))->toBeFalse()
        ->and($doc->isVisibleTo(docUser(UserRole::AdministratorOperational->value)))->toBeTrue();
});

// ─── Vizibilitate la nivel de query (scopul oglindește isVisibleTo) ─────────────────────

it('scopul de vizibilitate: profesorul nu vede documentele de director; managerul le vede pe toate', function () {
    Document::factory()->create(['is_published' => true, 'access_level' => DocumentAccessLevel::Public]);
    Document::factory()->forRoles(UserRole::Director->value)->create(['is_published' => true]);
    Document::factory()->draft()->create(['access_level' => DocumentAccessLevel::Public]);

    expect(Document::query()->visibleTo(docUser(UserRole::Profesor->value))->count())->toBe(1)   // doar publicul publicat
        ->and(Document::query()->visibleTo(docUser(UserRole::AdministratorOperational->value))->count())->toBe(3); // managerul: tot, incl. draftul
});

// ─── Descărcare gardată pe server (§1) ──────────────────────────────────────────────────

it('descărcarea returnează 403 pentru un rol neautorizat și 200 pentru cel autorizat', function () {
    Storage::fake('local');
    Storage::disk('local')->put('documents/demo.pdf', '%PDF-1.4 conținut demo');

    $doc = Document::factory()->forRoles(UserRole::Director->value)->create([
        'is_published' => true,
        'file_path' => 'documents/demo.pdf',
        'file_name' => 'demo.pdf',
    ]);

    actingAs(docUser(UserRole::Profesor->value))
        ->get(route('documents.download', $doc))
        ->assertForbidden();

    actingAs(docUser(UserRole::Director->value))
        ->get(route('documents.download', $doc))
        ->assertOk();
});

// ─── Gestiune gardată (doar administrația operațională) ─────────────────────────────────

it('gestiunea bibliotecii: profesorul NU poate crea documente, administratorul operațional da', function () {
    actingAs(docUser(UserRole::Profesor->value));
    expect(DocumentResource::canCreate())->toBeFalse();

    actingAs(docUser(UserRole::AdministratorOperational->value));
    expect(DocumentResource::canCreate())->toBeTrue();
});
