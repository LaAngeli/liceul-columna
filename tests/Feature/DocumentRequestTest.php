<?php

use App\Enums\DocumentRequestType;
use App\Enums\UserRole;
use App\Filament\Resources\DocumentRequests\DocumentRequestResource;
use App\Models\DocumentRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('familia depune o cerere tipică și se generează un PDF privat', function () {
    Storage::fake('local');

    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    $this->actingAs($parent)->post("/cabinet/elev/{$student->id}/cereri", [
        'type' => DocumentRequestType::Adeverinta->value,
        'details' => 'Necesară pentru dosarul de bursă.',
    ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success');

    $request = DocumentRequest::query()->where('student_id', $student->id)->first();

    expect($request)->not->toBeNull()
        ->and($request->type)->toBe(DocumentRequestType::Adeverinta)
        ->and($request->pdf_path)->not->toBeNull();

    Storage::disk('local')->assertExists($request->pdf_path);
});

it('personalul NU poate depune cereri pentru un elev (403)', function () {
    $student = Student::factory()->create();
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Profesor->value);

    $this->actingAs($staff)->post("/cabinet/elev/{$student->id}/cereri", [
        'type' => DocumentRequestType::Adeverinta->value,
    ])->assertForbidden();
});

it('PDF-ul cererii e privat: familia și administrația îl descarcă, un străin nu (403)', function () {
    Storage::fake('local');

    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    $request = DocumentRequest::factory()->create([
        'student_id' => $student->id,
        'requested_by_user_id' => $parent->id,
    ]);
    Storage::disk('local')->put('cereri/test.pdf', '%PDF-1.4 demo');
    $request->update(['pdf_path' => 'cereri/test.pdf']);

    $this->actingAs($parent)->get("/cabinet/cereri/{$request->id}/pdf")->assertOk();

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $this->actingAs($director)->get("/cabinet/cereri/{$request->id}/pdf")->assertOk();

    $stranger = User::factory()->create();
    $stranger->assignRole(UserRole::Parinte->value);
    $this->actingAs($stranger)->get("/cabinet/cereri/{$request->id}/pdf")->assertForbidden();
});

it('resursa „Cereri" (secretariat) e vizibilă administrației, nu profesorului/familiei', function (UserRole $role, bool $access) {
    $user = User::factory()->create();
    $user->assignRole($role->value);
    $this->actingAs($user);

    expect(DocumentRequestResource::canAccess())->toBe($access);
})->with([
    'super-admin' => [UserRole::Admin, true],
    'director' => [UserRole::Director, true],
    'administrator operațional' => [UserRole::AdministratorOperational, true],
    'administrator tehnic' => [UserRole::AdministratorTehnic, false],
    'profesor' => [UserRole::Profesor, false],
    'părinte' => [UserRole::Parinte, false],
]);
