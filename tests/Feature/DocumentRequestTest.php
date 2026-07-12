<?php

use App\Enums\DocumentRequestType;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Filament\Resources\DocumentRequests\DocumentRequestResource;
use App\Models\AcademicYear;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\Notification;
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

// ─── Cluster Documente/Rapoarte (mapare + verificare adversarială, 2026-07-12) ────────────

it('cererea elevului ARHIVAT rămâne descărcabilă de administrație (nu mai crapă cu 500)', function () {
    Storage::fake('local');

    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    $request = DocumentRequest::factory()->create([
        'student_id' => $student->id,
        'requested_by_user_id' => $parent->id,
    ]);
    Storage::disk('local')->put('cereri/arhivat.pdf', '%PDF-1.4 demo');
    $request->update(['pdf_path' => 'cereri/arhivat.pdf']);

    // Elevul pleacă din școală → fișa e arhivată. Administrația trebuie să poată închide cererea.
    $student->delete();

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $this->actingAs($director)->get("/cabinet/cereri/{$request->id}/pdf")->assertOk();

    // Familia: accesul la cabinet se pierde odată cu arhivarea copilului (isFamilyOf pe relația
    // activă — coerent cu restul cabinetului). Important: 403 curat, NU 500 ca înainte.
    $this->actingAs($parent)->get("/cabinet/cereri/{$request->id}/pdf")->assertForbidden();
});

it('învoirea CERE perioada (fără ea, secretariatul nu știe CÂND) — adeverința nu', function () {
    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    // Învoire fără perioadă → eroare de validare pe period_start.
    $this->actingAs($parent)->post("/cabinet/elev/{$student->id}/cereri", [
        'type' => DocumentRequestType::Invoire->value,
        'details' => 'Plecare în familie.',
    ])->assertSessionHasErrors(['period_start']);

    expect(DocumentRequest::query()->count())->toBe(0);
});

it('o cerere PENDING de același tip nu se poate redepune (anti-duplicat)', function () {
    Storage::fake('local');

    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    $submit = fn () => $this->actingAs($parent)->post("/cabinet/elev/{$student->id}/cereri", [
        'type' => DocumentRequestType::Adeverinta->value,
        'details' => 'Pentru dosar.',
    ]);

    $submit()->assertRedirect();
    // Al doilea submit de același tip, cât prima e în așteptare → respins pe câmpul type.
    $submit()->assertSessionHasErrors(['type']);

    expect(DocumentRequest::query()->count())->toBe(1);
});

it('profesorul clasei NU primește lista cererilor familiei în profilul elevului (L133 — minim necesar)', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();
    DocumentRequest::factory()->create(['student_id' => $student->id]);

    $profUser = User::factory()->create();
    $profUser->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $profUser->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $class->id,
        'subject_id' => Subject::factory()->create()->id,
    ]);

    // Prop-ul e defer → cerem explicit partial reload-ul lui (așa îl încarcă și frontend-ul).
    $this->actingAs($profUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get("/cabinet/elev/{$student->id}", inertiaPartialHeaders('cabinet/student-profile', 'documentRequests'))
        ->assertOk()
        ->assertJsonPath('props.documentRequests', []);

    // Administrația le vede în continuare (poate procesa).
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $this->actingAs($director)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get("/cabinet/elev/{$student->id}", inertiaPartialHeaders('cabinet/student-profile', 'documentRequests'))
        ->assertOk()
        ->assertJsonCount(1, 'props.documentRequests');
});

it('depunerea anunță secretariatul (AO), nu directorul (matricea lui nu are tipul)', function () {
    Notification::fake();

    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    DocumentRequest::factory()->create(['student_id' => $student->id]);

    Notification::assertSentTo(
        $ao,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::DocumentRequestSubmitted,
    );
    Notification::assertNothingSentTo($director);
});

it('ștergerea PERMANENTĂ a cererii șterge și PDF-ul; soft delete-ul îl păstrează (restaurabil)', function () {
    Storage::fake('local');

    $request = DocumentRequest::factory()->create(['student_id' => Student::factory()->create()->id]);
    Storage::disk('local')->put('cereri/igiena.pdf', '%PDF-1.4 demo');
    $request->update(['pdf_path' => 'cereri/igiena.pdf']);

    // Soft delete: rândul e restaurabil → fișierul RĂMÂNE.
    $request->delete();
    Storage::disk('local')->assertExists('cereri/igiena.pdf');

    // Force delete: rând dispărut definitiv → fișierul cu PII nu rămâne orfan (L133).
    $request->forceDelete();
    Storage::disk('local')->assertMissing('cereri/igiena.pdf');
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
