<?php

/**
 * Fișiere atașate temelor (cerința beneficiarului, 05.08.2026): profesorul încarcă fișe de lucru,
 * elevul le descarcă din cabinet. Testele apără exact ce s-ar putea strica tăcut: că descărcarea
 * respectă vizibilitatea temei (elevul ALTEI clase nu ajunge la fișier), că numele ORIGINAL — nu
 * cel de pe disc — e cel servit, și că fișierele scoase din temă nu rămân orfane pe disc.
 */

use App\Enums\UserRole;
use App\Filament\Resources\HomeworkAssignments\Pages\CreateHomeworkAssignment;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Storage::fake('local');

    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    Term::factory()->for($this->year)->create(['number' => 1, 'is_current' => true]);

    $this->class = SchoolClass::factory()->for($this->year)->create(['name' => '7', 'section' => 'A', 'grade_level' => 7]);
    $this->otherClass = SchoolClass::factory()->for($this->year)->create(['name' => '8', 'section' => 'B', 'grade_level' => 8]);
    $this->subject = Subject::factory()->create();

    // Elevul clasei vizate, cu cont propriu.
    $this->pupilUser = User::factory()->create();
    $this->pupilUser->assignRole(UserRole::Elev->value);
    $this->pupil = Student::factory()->create(['user_id' => $this->pupilUser->id]);
    Enrollment::factory()->for($this->pupil)->for($this->class)->for($this->year)->create(['left_on' => null]);

    // Elev într-o ALTĂ clasă — nu trebuie să ajungă la fișier.
    $this->strangerUser = User::factory()->create();
    $this->strangerUser->assignRole(UserRole::Elev->value);
    $stranger = Student::factory()->create(['user_id' => $this->strangerUser->id]);
    Enrollment::factory()->for($stranger)->for($this->otherClass)->for($this->year)->create(['left_on' => null]);
});

/** Temă cu un fișier atașat pe discul (fals) privat — calea hashed, numele original separat. */
function hwAttachHomework(SchoolClass $class, Subject $subject, ?string $section = null): HomeworkAssignment
{
    Storage::disk('local')->put('homework-attachments/abc123.pdf', '%PDF-1.4 fisa de lucru');

    return HomeworkAssignment::factory()->create([
        'subject_id' => $subject->id,
        'grade_level' => $class->grade_level,
        'section' => $section === 'toata-treapta' ? null : $class->section,
        'attachments' => ['homework-attachments/abc123.pdf'],
        'attachment_names' => ['homework-attachments/abc123.pdf' => 'Fișa de lucru nr. 4.pdf'],
    ]);
}

it('elevul clasei descarcă fișierul sub numele lui ORIGINAL', function () {
    $homework = hwAttachHomework($this->class, $this->subject);

    actingAs($this->pupilUser);

    $response = get(route('cabinet.homework.attachment', ['homework' => $homework->id, 'index' => 0]));

    $response->assertOk();
    expect((string) $response->headers->get('content-disposition'))
        ->toContain('attachment')
        // Numele de pe disc (abc123.pdf) nu se vede nicăieri — doar cel dat de profesor.
        ->toContain('Fisa de lucru nr. 4.pdf')
        ->not->toContain('abc123');
});

it('elevul ALTEI clase primește 403 — vizibilitatea temei se aplică și fișierului', function () {
    $homework = hwAttachHomework($this->class, $this->subject);

    actingAs($this->strangerUser);

    get(route('cabinet.homework.attachment', ['homework' => $homework->id, 'index' => 0]))->assertForbidden();
});

it('părintele unui elev din clasă descarcă; personalul didactic la fel; vizitatorul e trimis la login', function () {
    $homework = hwAttachHomework($this->class, $this->subject);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($this->pupil->id);

    actingAs($parent);
    get(route('cabinet.homework.attachment', ['homework' => $homework->id, 'index' => 0]))->assertOk();

    $teacherUser = User::factory()->create();
    $teacherUser->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $teacherUser->id]);

    actingAs($teacherUser->fresh());
    get(route('cabinet.homework.attachment', ['homework' => $homework->id, 'index' => 0]))->assertOk();

    auth('web')->logout();
    get(route('cabinet.homework.attachment', ['homework' => $homework->id, 'index' => 0]))->assertRedirect();
});

it('tema pe TOATĂ treapta (fără literă) se descarcă de orice elev al treptei', function () {
    $homework = hwAttachHomework($this->class, $this->subject, 'toata-treapta');

    actingAs($this->pupilUser);
    get(route('cabinet.homework.attachment', ['homework' => $homework->id, 'index' => 0]))->assertOk();

    // Treapta diferă (8 vs 7) → tot 403.
    actingAs($this->strangerUser);
    get(route('cabinet.homework.attachment', ['homework' => $homework->id, 'index' => 0]))->assertForbidden();
});

it('un index inexistent dă 404, nu alt fișier', function () {
    $homework = hwAttachHomework($this->class, $this->subject);

    actingAs($this->pupilUser);
    get(route('cabinet.homework.attachment', ['homework' => $homework->id, 'index' => 7]))->assertNotFound();
});

it('profesorul atașează fișiere din formularul de creare, cu numele original păstrat', function () {
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $this->class->id, 'subject_id' => $this->subject->id,
    ]);

    Livewire::actingAs($teacherUser->fresh())
        ->test(CreateHomeworkAssignment::class)
        ->fillForm([
            'class_target' => 'class:'.$this->class->id,
            'subject_id' => $this->subject->id,
            'assigned_on' => now()->toDateString(),
            'topic' => 'Recapitulare cu fișă',
            'attachments' => [UploadedFile::fake()->create('Fișa 4.pdf', 120, 'application/pdf')],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $homework = HomeworkAssignment::query()->latest('id')->firstOrFail();

    expect($homework->attachments)->toHaveCount(1)
        ->and($homework->attachmentName(0))->toBe('Fișa 4.pdf')
        // Numele de pe disc e generat aleator, nu cel al utilizatorului (securitate).
        ->and(basename((string) $homework->attachmentPath(0)))->not->toBe('Fișa 4.pdf')
        ->and(Storage::disk('local')->exists((string) $homework->attachmentPath(0)))->toBeTrue();
});

it('fișierul scos din temă dispare de pe disc; ștergerea definitivă curăță tot', function () {
    $homework = hwAttachHomework($this->class, $this->subject);
    $path = 'homework-attachments/abc123.pdf';

    // Scoaterea din listă (editare) șterge exact fișierul scos.
    $homework->update(['attachments' => [], 'attachment_names' => []]);

    expect(Storage::disk('local')->exists($path))->toBeFalse();

    // Ștergerea LOGICĂ păstrează fișierele (tema e restaurabilă)…
    Storage::disk('local')->put('homework-attachments/def456.pdf', 'x');
    $second = HomeworkAssignment::factory()->create([
        'subject_id' => $this->subject->id,
        'grade_level' => 7, 'section' => 'A',
        'attachments' => ['homework-attachments/def456.pdf'],
    ]);
    $second->delete();

    expect(Storage::disk('local')->exists('homework-attachments/def456.pdf'))->toBeTrue();

    // …cea DEFINITIVĂ le ia cu ea.
    $second->forceDelete();

    expect(Storage::disk('local')->exists('homework-attachments/def456.pdf'))->toBeFalse();
});
