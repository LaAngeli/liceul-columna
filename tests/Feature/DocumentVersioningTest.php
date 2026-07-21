<?php

/**
 * Documente — Faza 4: versionarea reală a documentelor statice (istoricul înlocuirilor de fișier,
 * vizibil doar administratorilor bibliotecii) + foaia matricolă BILINGVĂ RO/EN (document de
 * circulație internațională).
 */

use App\Enums\AcademicRecordPeriod;
use App\Enums\GeneratedDocumentType;
use App\Enums\UserRole;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\RelationManagers\VersionsRelationManager;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Http\Controllers\CabinetController;
use App\Models\Absence;
use App\Models\AcademicRecord;
use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

function versioningUser(string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

// ─── Arhivarea la înlocuire: snapshot-ul poartă metadatele VECHI ─────────────────────────

it('versiunea arhivată păstrează metadatele de dinaintea înlocuirii (etichetă + uploader)', function () {
    Storage::fake('local');
    Storage::disk('local')->put('documents/v1.pdf', 'v1');

    $firstUploader = versioningUser(UserRole::AdministratorOperational->value);
    $document = Document::factory()->create([
        'file_path' => 'documents/v1.pdf',
        'file_name' => 'regulament-2025.pdf',
        'version' => 'ed. 2025',
        'uploaded_by_user_id' => $firstUploader->id,
    ]);

    Storage::disk('local')->put('documents/v2.pdf', 'v2');
    $document->update([
        'file_path' => 'documents/v2.pdf',
        'file_name' => 'regulament-2026.pdf',
        'version' => 'ed. 2026',
        'uploaded_by_user_id' => versioningUser(UserRole::Director->value)->id,
    ]);

    $version = $document->versions()->sole();

    expect($version->file_path)->toBe('documents/v1.pdf')
        ->and($version->file_name)->toBe('regulament-2025.pdf')
        ->and($version->version_label)->toBe('ed. 2025')
        ->and($version->uploaded_by_user_id)->toBe($firstUploader->id);
});

it('scoaterea fișierului (fără înlocuitor) arhivează la fel versiunea veche', function () {
    Storage::fake('local');
    Storage::disk('local')->put('documents/v1.pdf', 'v1');

    $document = Document::factory()->create(['file_path' => 'documents/v1.pdf']);
    $document->update(['file_path' => null, 'file_name' => null]);

    Storage::disk('local')->assertExists('documents/v1.pdf');
    expect($document->versions()->count())->toBe(1);
});

// ─── Istoricul în panou: doar administratorii bibliotecii ────────────────────────────────

it('istoricul versiunilor e vizibil administratorului operațional, nu profesorului', function () {
    $document = Document::factory()->create();

    actingAs(versioningUser(UserRole::AdministratorOperational->value));
    expect(VersionsRelationManager::canViewForRecord($document, EditDocument::class))->toBeTrue();

    actingAs(versioningUser(UserRole::Profesor->value));
    expect(VersionsRelationManager::canViewForRecord($document, EditDocument::class))->toBeFalse();
});

it('administratorul vede versiunile în istoric și poate descărca o versiune arhivată', function () {
    Storage::fake('local');
    Storage::disk('local')->put('documents/arhiva.pdf', '%PDF-1.4 arhiva');

    $document = Document::factory()->create();
    $version = DocumentVersion::factory()->create([
        'document_id' => $document->id,
        'file_path' => 'documents/arhiva.pdf',
        'file_name' => 'arhiva.pdf',
        'version_label' => 'ed. 2024',
    ]);

    actingAs(versioningUser(UserRole::AdministratorOperational->value));

    Livewire::test(VersionsRelationManager::class, [
        'ownerRecord' => $document,
        'pageClass' => EditDocument::class,
    ])
        ->assertCanSeeTableRecords([$version])
        ->assertTableActionVisible('download', $version)
        ->callTableAction('download', $version)
        ->assertFileDownloaded('arhiva.pdf');
});

it('descărcarea unei versiuni cu fișierul dispărut de pe disc nu e oferită', function () {
    Storage::fake('local');

    $document = Document::factory()->create();
    $version = DocumentVersion::factory()->create([
        'document_id' => $document->id,
        'file_path' => 'documents/lipsa.pdf',
    ]);

    actingAs(versioningUser(UserRole::AdministratorOperational->value));

    Livewire::test(VersionsRelationManager::class, [
        'ownerRecord' => $document,
        'pageClass' => EditDocument::class,
    ])->assertTableActionHidden('download', $version);
});

// ─── Foaia matricolă bilingvă RO/EN (Faza 4) ─────────────────────────────────────────────

it('transcriptul poartă numele EN al disciplinei, iar șablonul randează etichete duale', function () {
    $student = Student::factory()->create();
    $subject = Subject::factory()->create(['name' => 'Matematică']);
    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'grade_level' => 7,
        'period' => AcademicRecordPeriod::Annual,
        'value' => 8.5,
    ]);

    $method = new ReflectionMethod(CabinetController::class, 'generatedDocumentData');
    $data = $method->invoke(app(CabinetController::class), GeneratedDocumentType::Transcript, $student);

    $row = collect($data['levels'][0]['subjects'])->firstWhere('subject_ro', 'Matematică');
    expect($row['subject_en'])->toBe('Mathematics');

    $html = view(GeneratedDocumentType::Transcript->blade(), $data)->render();

    expect($html)
        ->toContain('School Transcript')
        ->toContain('Mathematics')
        ->toContain('Grade 7')
        ->toContain('the Romanian version prevails');
});

it('o disciplină fără traducere EN nu primește dublură (subject_en = null)', function () {
    $student = Student::factory()->create();
    $subject = Subject::factory()->create(['name' => 'Disciplină Fără Traducere']);
    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'grade_level' => 5,
        'period' => AcademicRecordPeriod::Annual,
        'value' => 9,
    ]);

    $method = new ReflectionMethod(CabinetController::class, 'generatedDocumentData');
    $data = $method->invoke(app(CabinetController::class), GeneratedDocumentType::Transcript, $student);

    $row = collect($data['levels'][0]['subjects'])->firstWhere('subject_ro', 'Disciplină Fără Traducere');
    expect($row['subject_en'])->toBeNull();
});

// ─── Documentele generate pe fișa elevului din panou ─────────────────────────────────────

it('acțiunile de documente ale elevului: administrația și dirigintele DA, profesorul de disciplină NU', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    $subject = Subject::factory()->create();

    // Profesor de disciplină în clasa elevului (vede fișa prin scoping, dar NU generează documente).
    $teacherUser = versioningUser(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id,
    ]);

    // Diriginte al clasei elevului.
    $homeroomUser = versioningUser(UserRole::Diriginte->value);
    $homeroomTeacher = Teacher::factory()->create(['user_id' => $homeroomUser->id]);
    $class->update(['homeroom_teacher_id' => $homeroomTeacher->id]);

    actingAs(versioningUser(UserRole::Director->value));
    Livewire::test(ViewStudent::class, ['record' => $student->id])
        ->assertActionVisible('doc-transcript')
        ->assertActionVisible('doc-student_file');

    actingAs($homeroomUser);
    Livewire::test(ViewStudent::class, ['record' => $student->id])
        ->assertActionVisible('doc-transcript');

    actingAs($teacherUser);
    Livewire::test(ViewStudent::class, ['record' => $student->id])
        ->assertActionHidden('doc-transcript');
});

// ─── Dosarul elevului — PDF combinat (Faza 5) ────────────────────────────────────────────

it('dosarul elevului combină situația semestrului cu evoluția pe ani și se generează pentru familie', function () {
    $student = Student::factory()->create();
    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'subject_id' => Subject::factory()->create(['name' => 'Matematică'])->id,
        'grade_level' => 6,
        'period' => AcademicRecordPeriod::Annual,
        'value' => 8,
    ]);

    // Datele documentului: cheile situației semestriale + dinamica multi-anuală, împreună.
    $method = new ReflectionMethod(CabinetController::class, 'generatedDocumentData');
    $data = $method->invoke(app(CabinetController::class), GeneratedDocumentType::StudentFile, $student);

    expect($data)->toHaveKeys(['termLabel', 'subjects', 'absences', 'average', 'dynamics'])
        ->and($data['dynamics']['general'][0])->toBe(['level' => 6, 'average' => 8.0]);

    $html = view(GeneratedDocumentType::StudentFile->blade(), $data)->render();
    expect($html)
        ->toContain('Dosarul elevului')
        ->toContain('Evoluția mediei generale pe ani')
        ->toContain('Clasa a 6-a');

    // Fluxul complet: familia descarcă PDF-ul; gardul serverului rămâne cel comun (403 la străin).
    $parent = User::factory()->create(['email_verified_at' => now()]);
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    actingAs($parent)
        ->get(route('cabinet.document.generate', ['student' => $student->id, 'type' => 'student_file']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

// ─── Raportul absențelor — anul curent, pe date (Faza 5) ─────────────────────────────────

it('raportul absențelor grupează pe semestre, etichetează ziua întreagă și numără motivarea', function () {
    $year = AcademicYear::factory()->create(['name' => '2025–2026']);
    $term1 = Term::factory()->for($year)->create(['number' => 1, 'is_current' => false]);
    $term2 = Term::factory()->for($year)->create(['number' => 2, 'is_current' => true]);
    $class = SchoolClass::factory()->for($year)->create();

    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    $subject = Subject::factory()->create(['name' => 'Matematică']);

    // Sem. I: absență MOTIVATĂ la disciplină; Sem. II: absență NEMOTIVATĂ pe zi întreagă.
    Absence::factory()->create([
        'student_id' => $student->id, 'school_class_id' => $class->id,
        'subject_id' => $subject->id, 'term_id' => $term1->id,
        'occurred_on' => '2025-10-06', 'is_motivated' => true,
    ]);
    Absence::factory()->create([
        'student_id' => $student->id, 'school_class_id' => $class->id,
        'subject_id' => null, 'term_id' => $term2->id,
        'occurred_on' => '2026-03-11', 'is_motivated' => false,
    ]);

    $method = new ReflectionMethod(CabinetController::class, 'generatedDocumentData');
    $data = $method->invoke(app(CabinetController::class), GeneratedDocumentType::AbsenceReport, $student);

    expect($data['yearLabel'])->toBe('2025–2026')
        ->and($data['total'])->toBe(2)
        ->and($data['totalMotivated'])->toBe(1)
        ->and($data['totalUnmotivated'])->toBe(1)
        ->and($data['sections'][0]['label'])->toBe('Semestrul I')
        ->and($data['sections'][0]['rows'][0])->toBe(['date' => '06.10.2025', 'subject' => 'Matematică', 'motivated' => true])
        ->and($data['sections'][1]['rows'][0]['subject'])->toBe('Zi întreagă')
        ->and($data['sections'][1]['unmotivated'])->toBe(1);

    $html = view(GeneratedDocumentType::AbsenceReport->blade(), $data)->render();
    expect($html)
        ->toContain('Raportul absențelor')
        ->toContain('Anul școlar 2025–2026')
        ->toContain('Zi întreagă');

    // Fluxul complet prin ruta gardată.
    $parent = User::factory()->create(['email_verified_at' => now()]);
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    actingAs($parent)
        ->get(route('cabinet.document.generate', ['student' => $student->id, 'type' => 'absence_report']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('REGRESIE: situația școlară nu mai crapă la absența pe zi întreagă (subject null)', function () {
    $year = AcademicYear::factory()->create();
    $term = Term::factory()->for($year)->create(['number' => 2, 'is_current' => true]);
    $class = SchoolClass::factory()->for($year)->create();

    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    Absence::factory()->create([
        'student_id' => $student->id, 'school_class_id' => $class->id,
        'subject_id' => null, 'term_id' => $term->id,
        'occurred_on' => '2026-03-12', 'is_motivated' => false,
    ]);

    $method = new ReflectionMethod(CabinetController::class, 'generatedDocumentData');
    $data = $method->invoke(app(CabinetController::class), GeneratedDocumentType::TermSituation, $student);

    expect(collect($data['absences'])->pluck('subject'))->toContain('Zi întreagă');
});
