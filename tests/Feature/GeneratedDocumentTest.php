<?php

use App\Enums\DocumentAccessLevel;
use App\Enums\GeneratedDocumentType;
use App\Enums\UserRole;
use App\Http\Controllers\CabinetController;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\DocumentVersion;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
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

// ─── Conținutul situației școlare e TERM-SCOPED (audit Documente, #35) ───────────────────

it('situația școlară conține DOAR semestrul curent — notele/absențele altor semestre nu intră', function () {
    $year = AcademicYear::factory()->create();
    $oldTerm = Term::factory()->for($year)->create(['number' => 1, 'is_current' => false]);
    $currentTerm = Term::factory()->for($year)->create(['number' => 2, 'is_current' => true]);
    $class = SchoolClass::factory()->for($year)->create();

    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    $oldSubject = Subject::factory()->create(['name' => 'Disciplina Veche']);
    $currentSubject = Subject::factory()->create(['name' => 'Disciplina Curentă']);

    // Notă + absență în semestrul VECHI; notă + absență în semestrul CURENT.
    Grade::factory()->create([
        'student_id' => $student->id, 'subject_id' => $oldSubject->id,
        'school_class_id' => $class->id, 'term_id' => $oldTerm->id, 'value' => 9,
    ]);
    Grade::factory()->create([
        'student_id' => $student->id, 'subject_id' => $currentSubject->id,
        'school_class_id' => $class->id, 'term_id' => $currentTerm->id, 'value' => 7,
    ]);
    Absence::factory()->create([
        'student_id' => $student->id, 'subject_id' => $oldSubject->id,
        'school_class_id' => $class->id, 'term_id' => $oldTerm->id, 'occurred_on' => '2026-03-09',
    ]);
    Absence::factory()->create([
        'student_id' => $student->id, 'subject_id' => $currentSubject->id,
        'school_class_id' => $class->id, 'term_id' => $currentTerm->id, 'occurred_on' => '2026-03-10',
    ]);

    // Metoda privată de date — direct pe constatarea confirmată: titlul promite semestrul
    // curent, deci conținutul trebuie să fie SCOPAT pe el (înainte agrega tot istoricul).
    $method = new ReflectionMethod(CabinetController::class, 'generatedDocumentData');
    $data = $method->invoke(
        app(CabinetController::class),
        GeneratedDocumentType::TermSituation,
        $student,
    );

    $subjectNames = collect($data['subjects'])->pluck('subject');
    $absenceNames = collect($data['absences'])->pluck('subject');

    expect($data['termLabel'])->toBe('Semestrul II')
        ->and($subjectNames)->toContain('Disciplina Curentă')
        ->and($subjectNames)->not->toContain('Disciplina Veche')
        ->and($absenceNames)->toContain('Disciplina Curentă')
        ->and($absenceNames)->not->toContain('Disciplina Veche')
        ->and($data['absencesTotal'])->toBe(1);
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

it('badge-ul „Cereri" arată totalul REAL, nu lista plafonată la 15 (#36)', function () {
    $student = Student::factory()->create();
    $parent = familyParent($student);

    DocumentRequest::factory()->count(17)->create([
        'student_id' => $student->id,
        'requested_by_user_id' => $parent->id,
    ]);

    actingAs($parent)
        ->get(route('cabinet.documents'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/documents')
            ->has('children.0.requests', 15)              // lista rămâne plafonată (cele mai recente)
            ->where('children.0.requestsTotal', 17)       // badge-ul numără tot
        );
});

it('cererile din pagina Documente poartă răspunsul secretariatului (coerent cu profilul)', function () {
    $student = Student::factory()->create();
    $parent = familyParent($student);

    $request = DocumentRequest::factory()->create([
        'student_id' => $student->id,
        'requested_by_user_id' => $parent->id,
    ]);
    $request->markProcessed(User::factory()->create()->id, 'Adeverința e gata la secretariat.');

    actingAs($parent)
        ->get(route('cabinet.documents'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/documents')
            ->where('children.0.requests.0.note', 'Adeverința e gata la secretariat.')
        );
});

// ─── Igiena fișierelor bibliotecii (audit Documente #35, versionare Faza 4) ───────────────

it('înlocuirea fișierului ARHIVEAZĂ versiunea veche; forceDelete curăță tot; soft delete nu', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');

    $disk->put('documents/v1.pdf', 'v1');
    $document = Document::factory()->create(['file_path' => 'documents/v1.pdf']);

    // Înlocuire (Faza 4): fișierul vechi RĂMÂNE pe disk, arhivat ca versiune — nu se mai pierde.
    $disk->put('documents/v2.pdf', 'v2');
    $document->update(['file_path' => 'documents/v2.pdf']);
    $disk->assertExists('documents/v1.pdf');
    $disk->assertExists('documents/v2.pdf');
    expect($document->versions()->count())->toBe(1)
        ->and($document->versions()->first()->file_path)->toBe('documents/v1.pdf');

    // Soft delete: restaurabil → fișierele și istoricul rămân.
    $document->delete();
    $disk->assertExists('documents/v2.pdf');
    expect($document->versions()->count())->toBe(1);

    // Force delete: rând dispărut definitiv → dispar fișierul curent, fișierele versiunilor
    // și rândurile de istoric (un fișier fără rând-mamă n-ar mai putea fi găsit la ștergere L133).
    $document->forceDelete();
    $disk->assertMissing('documents/v2.pdf');
    $disk->assertMissing('documents/v1.pdf');
    expect(DocumentVersion::query()->count())->toBe(0);
});

it('personalul e redirecționat de la pagina Documente a cabinetului (doar familie)', function () {
    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole(UserRole::AdministratorTehnic->value);

    actingAs($staff)->get(route('cabinet.documents'))->assertRedirect();
});
