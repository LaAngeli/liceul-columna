<?php

use App\Enums\DocumentAccessLevel;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function familyParent(Student $student): User
{
    $parent = User::factory()->create(['email_verified_at' => now()]);
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    return $parent;
}

// ─── Generare gardată pe server (§1) ────────────────────────────────────────────────────

it('familia poate genera foaia matricolă a copilului (PDF), un străin primește 403', function () {
    $student = Student::factory()->create();
    $parent = familyParent($student);

    actingAs($parent)
        ->get(route('cabinet.document.generate', ['student' => $student->id, 'type' => 'transcript']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    // Alt părinte (fără legătură cu elevul) → interzis.
    $stranger = User::factory()->create(['email_verified_at' => now()]);
    $stranger->assignRole(UserRole::Parinte->value);

    actingAs($stranger)
        ->get(route('cabinet.document.generate', ['student' => $student->id, 'type' => 'transcript']))
        ->assertForbidden();
});

it('administrația poate genera situația școlară a oricărui elev', function () {
    $student = Student::factory()->create();
    $director = User::factory()->create(['email_verified_at' => now()]);
    $director->assignRole(UserRole::Director->value);

    actingAs($director)
        ->get(route('cabinet.document.generate', ['student' => $student->id, 'type' => 'term_situation']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('un tip de document necunoscut returnează 404', function () {
    $student = Student::factory()->create();

    actingAs(familyParent($student))
        ->get(route('cabinet.document.generate', ['student' => $student->id, 'type' => 'inexistent']))
        ->assertNotFound();
});

// ─── Pagina „Documente" din cabinet ─────────────────────────────────────────────────────

it('pagina Documente randează pentru familie: documentele copilului + cele ale școlii', function () {
    $student = Student::factory()->create();
    $parent = familyParent($student);

    // Un document public (vizibil familiei) + unul de staff (ascuns familiei).
    Document::factory()->create(['is_published' => true, 'access_level' => DocumentAccessLevel::Public, 'title' => 'ROI']);
    Document::factory()->forRoles(UserRole::Director->value)->create(['is_published' => true, 'title' => 'Doar director']);

    actingAs($parent)
        ->get(route('cabinet.documents'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/documents')
            ->has('categories', 5)                     // toate cele 5 subcategorii = taburi mereu prezente
            ->has('children', 1)
            ->has('children.0.generated', 2)          // foaie matricolă + situația școlară
            ->has('schoolDocuments')
        );
});

it('personalul e redirecționat de la pagina Documente a cabinetului (doar familie)', function () {
    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole(UserRole::AdministratorTehnic->value);

    actingAs($staff)->get(route('cabinet.documents'))->assertRedirect();
});
