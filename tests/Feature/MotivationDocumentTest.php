<?php

use App\Enums\AudienceDomain;
use App\Enums\UserRole;
use App\Models\Absence;
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
    // Motivarea trebuie să vizeze o absență nemotivată reală (validare #37).
    Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-03-03', 'is_motivated' => false]);

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

    // Istoricul rămâne al familiei: după ARHIVAREA elevului (plecare), părintele descarcă în
    // continuare justificativul depus (decizie de produs 2026-07-18); străinul rămâne exclus.
    $student->delete();
    $this->actingAs($parent)->get(route('cabinet.motivation.document', $motivation))->assertOk();
    $this->actingAs($stranger)->get(route('cabinet.motivation.document', $motivation))->assertForbidden();
});

it('justificativul se servește inline pentru previzualizare; excepția se deschide validatorului ei', function () {
    Storage::fake('local');

    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);
    Absence::factory()->create(['student_id' => $student->id, 'occurred_on' => '2026-03-03', 'is_motivated' => false]);

    $this->actingAs($parent)->post("/cabinet/elev/{$student->id}/motivare", [
        'reason' => 'Consultație medicală',
        'period_start' => '2026-03-02',
        'period_end' => '2026-03-04',
        'document' => UploadedFile::fake()->create('adeverinta.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    $motivation = AbsenceMotivation::query()->firstOrFail();

    // `?inline=1` → disposition INLINE (previzualizarea de pe fișa cererii), nu attachment.
    $inline = $this->actingAs($parent)
        ->get(route('cabinet.motivation.document', ['absenceMotivation' => $motivation->id, 'inline' => 1]))
        ->assertOk();
    expect((string) $inline->headers->get('content-disposition'))->toStartWith('inline');

    $motivation->update(['is_exception' => true]);

    // Validatorul REAL al excepției — vicedirectorul pe educație (mereu un rol de administrație,
    // {@see UserRole::audienceDomainHolderValues}) — își vede dovada de pe fișă.
    $educatie = User::factory()->create(['audience_domains' => [AudienceDomain::Educatie->value]]);
    $educatie->assignRole(UserRole::PrimVicedirector->value);
    $this->actingAs($educatie)->get(route('cabinet.motivation.document', $motivation))->assertOk();

    // Reziduul de desemnare pe un rol fără drept (profesor retrogradat cu domeniul rămas în
    // coloană) NU deschide dosarul — desemnarea contează doar pe rolurile purtătoare.
    $residue = User::factory()->create(['audience_domains' => [AudienceDomain::Educatie->value]]);
    $residue->assignRole(UserRole::Profesor->value);
    $this->actingAs($residue)->get(route('cabinet.motivation.document', $motivation))->assertForbidden();
});
