<?php

use App\Enums\UserRole;
use App\Models\AbsenceMotivation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('justificativul atașat se stochează privat și e descărcabil de familie, nu de un străin', function () {
    Storage::fake('local');

    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    $this->actingAs($parent)->post("/cabinet/elev/{$student->id}/motivare", [
        'reason' => 'Consultație medicală',
        'period_start' => '2026-03-02',
        'period_end' => '2026-03-04',
        'document' => UploadedFile::fake()->create('adeverinta.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    $motivation = AbsenceMotivation::query()->firstOrFail();
    $path = $motivation->document_path;

    expect($path)->not->toBeNull();
    Storage::disk('local')->assertExists((string) $path);

    // Familia descarcă justificativul propriu.
    $this->actingAs($parent)->get(route('cabinet.motivation.document', $motivation))->assertOk();

    // Un profesor străin (nici diriginte, nici administrație) NU are acces (PII de minor).
    $stranger = User::factory()->create();
    $stranger->assignRole(UserRole::Profesor->value);
    $this->actingAs($stranger)->get(route('cabinet.motivation.document', $motivation))->assertForbidden();
});
