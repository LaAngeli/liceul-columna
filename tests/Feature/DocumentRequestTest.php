<?php

use App\Actions\GenerateRequestPdf;
use App\Enums\CorrectionStatus;
use App\Enums\DocumentRequestType;
use App\Enums\EvaluationType;
use App\Enums\GradingType;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\DocumentRequests\DocumentRequestResource;
use App\Filament\Resources\DocumentRequests\Pages\ListDocumentRequests;
use App\Models\AcademicYear;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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

it('eșecul generării PDF NU lasă o cerere PENDING orfană (tranzacție)', function () {
    Storage::fake('local');

    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    // Forțăm eșecul randării mpdf.
    $this->mock(GenerateRequestPdf::class, function ($mock): void {
        $mock->shouldReceive('generate')->andThrow(new RuntimeException('mpdf a eșuat'));
    });

    try {
        $this->actingAs($parent)->post("/cabinet/elev/{$student->id}/cereri", [
            'type' => DocumentRequestType::Adeverinta->value,
            'details' => 'Pentru dosar.',
        ]);
    } catch (Throwable) {
        // Excepția se propagă — ne interesează doar că rândul NU a rămas.
    }

    // Tranzacția s-a dat înapoi → nicio cerere orfană care ar bloca redepunerea.
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

// ─── Flux contestație→corecție (#36, decizia userului: „un flux real + notificare relevantă") ────

/**
 * Fixture comun: elev cu o notă activă (7 la o disciplină numerică), părinte-tutore și o
 * CONTESTAȚIE în așteptare depusă de familie.
 *
 * @return array{request: DocumentRequest, grade: Grade, parent: User, student: Student}
 */
function contestationFixture(): array
{
    $year = AcademicYear::factory()->create();
    $term = Term::factory()->for($year)->create([
        'number' => 2, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'is_current' => true,
    ]);
    $class = SchoolClass::factory()->for($year)->create();
    $subject = Subject::factory()->create(['grading_type' => GradingType::Numeric]);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    $grade = Grade::factory()->create([
        'student_id' => $student->id,
        'school_class_id' => $class->id,
        'subject_id' => $subject->id,
        'term_id' => $term->id,
        'teacher_id' => Teacher::factory()->create()->id,
        'evaluation_type' => EvaluationType::Curenta,
        'value' => 7,
        'calificativ' => null,
    ]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    $request = DocumentRequest::factory()->ofType(DocumentRequestType::Contestatie)->create([
        'student_id' => $student->id,
        'requested_by_user_id' => $parent->id,
        'payload' => ['details' => 'Lucrarea a fost punctată greșit la subiectul II.'],
    ]);

    return ['request' => $request, 'grade' => $grade, 'parent' => $parent, 'student' => $student];
}

it('administrația deschide o corecție din contestație: corecția e legată, cererea procesată cu notă, aprobatorii anunțați', function () {
    ['request' => $request, 'grade' => $grade] = contestationFixture();

    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    Notification::fake();

    Livewire::actingAs($ao)
        ->test(ListDocumentRequests::class)
        ->callTableAction('openCorrection', $request, [
            'grade_id' => $grade->id,
            'new_value' => 9,
            'reason' => 'Punctajul recalculat al lucrării dă nota 9.',
        ]);

    $correction = GradeCorrection::query()->where('document_request_id', $request->id)->first();

    expect($correction)->not->toBeNull()
        ->and($correction->grade_id)->toBe($grade->id)
        ->and($correction->requested_by_user_id)->toBe($ao->id)
        ->and((float) $correction->old_value)->toBe(7.0)
        ->and((float) $correction->new_value)->toBe(9.0)
        ->and($correction->status)->toBe(CorrectionStatus::Pending);

    // Contestația e închisă administrativ, cu trimitere la corecția deschisă (trasabilitate).
    $request->refresh();
    expect($request->status)->toBe(RequestStatus::Approved)
        ->and($request->review_note)->toContain('#'.$correction->id);

    // Aprobatorii corecțiilor află că au de judecat una nouă (observer-ul standard).
    Notification::assertSentTo(
        $director,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::GradeCorrectionRequest,
    );
});

it('respingerea corecției din contestație anunță FAMILIA cu rezultatul; corecția obișnuită (profesor) nu', function () {
    ['request' => $request, 'grade' => $grade, 'parent' => $parent] = contestationFixture();

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);

    $fromContestation = GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'requested_by_user_id' => $ao->id,
        'document_request_id' => $request->id,
        'old_value' => 7,
        'new_value' => 9,
        'status' => CorrectionStatus::Pending,
    ]);

    Notification::fake();
    $fromContestation->reject($director->id, 'Baremul a fost aplicat corect.');

    // Familia a inițiat reexaminarea → primește verdictul; solicitantul (AO) primește verdictul standard.
    Notification::assertSentTo(
        $parent,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::ContestationRejected,
    );
    Notification::assertSentTo(
        $ao,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::GradeCorrectionRejected,
    );

    // Contrast: corecția cerută de PROFESOR (fără contestație) rămâne teacher↔conducere — familia
    // nu e notificată la respingere (nota nu s-a schimbat, familia n-a fost implicată).
    $teacherCorrection = GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'requested_by_user_id' => User::factory()->create()->id,
        'old_value' => 7,
        'new_value' => 8,
        'status' => CorrectionStatus::Pending,
    ]);

    Notification::fake();
    $teacherCorrection->reject($director->id);

    Notification::assertNotSentTo(
        $parent,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::ContestationRejected,
    );
});

it('acțiunea „Deschide corecție" apare doar pe CONTESTAȚIILE în așteptare', function () {
    ['request' => $contestation, 'student' => $student, 'parent' => $parent] = contestationFixture();

    $adeverinta = DocumentRequest::factory()->create([
        'student_id' => $student->id,
        'requested_by_user_id' => $parent->id,
    ]);
    $processed = DocumentRequest::factory()->ofType(DocumentRequestType::Contestatie)->create([
        'student_id' => $student->id,
        'requested_by_user_id' => $parent->id,
        'status' => RequestStatus::Approved,
    ]);

    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);

    Livewire::actingAs($ao)
        ->test(ListDocumentRequests::class)
        ->assertActionVisible(TestAction::make('openCorrection')->table($contestation))
        ->assertActionHidden(TestAction::make('openCorrection')->table($adeverinta));

    // Cererea PROCESATĂ trăiește în vederea „Arhivă" a navigatorului — acolo acțiunea nu există.
    Livewire::actingAs($ao)
        ->withQueryParams(['arhiva' => '1'])
        ->test(ListDocumentRequests::class)
        ->assertActionHidden(TestAction::make('openCorrection')->table($processed));
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
