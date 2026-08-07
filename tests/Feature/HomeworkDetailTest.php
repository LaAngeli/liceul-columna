<?php

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * Pagina de DETALIU a temei + servirea SIGURĂ a fișierelor atașate (cerința 2026-08-07).
 *
 * Modelul de amenințare pinuit aici: un fișier încărcat e conținut nedemn de încredere; servit
 * inline pe originea aplicației, un HTML deghizat ar deveni XSS stocat cu sesiunea victimei.
 * Testele verifică exact bariera: inline DOAR pentru tipuri pasive, decis din CONȚINUT (finfo),
 * niciodată din nume; restul — descărcare.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    $this->withoutVite();
});

/** PNG real 1×1 — semnătura validă pe care finfo o recunoaște drept image/png. */
function homeworkDetailPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
}

/**
 * Familie + temă a clasei copilului, cu fișiere pe discul fals: un PNG REAL și un HTML
 * DEGHIZAT în „.png" — perechea care desparte verificarea pe conținut de cea pe nume.
 *
 * @return array{parent: User, homework: HomeworkAssignment}
 */
function homeworkDetailWorld(): array
{
    Storage::fake('local');

    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 5, 'section' => 'A']);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    Storage::disk('local')->put('homework-attachments/real.png', homeworkDetailPng());
    Storage::disk('local')->put('homework-attachments/fals.png', '<!doctype html><script>alert(1)</script>');

    $homework = HomeworkAssignment::query()->create([
        'subject_name' => 'Matematică',
        'author_name' => 'Damian Iu.',
        'grade_level' => $class->grade_level,
        'section' => $class->section,
        'assigned_on' => '2026-03-12',
        'topic' => 'Recapitulare',
        'required_task' => 'Ex. 1–3',
        'links' => ['https://manual.example/cap4'],
        'printed_resources' => ['Culegerea, p. 12'],
        'attachments' => ['homework-attachments/real.png', 'homework-attachments/fals.png'],
        'attachment_names' => [
            'homework-attachments/real.png' => 'fisa.png',
            'homework-attachments/fals.png' => 'inselator.png',
        ],
    ]);

    return ['parent' => $parent, 'homework' => $homework];
}

it('familia deschide pagina temei, cu resursele și decizia de previzualizare a serverului', function () {
    $world = homeworkDetailWorld();

    $this->actingAs($world['parent'])
        ->get(route('cabinet.homework.show', $world['homework']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/tema')
            ->where('homework.subject', 'Matematică')
            ->where('homework.topic', 'Recapitulare')
            ->where('homework.links.0', 'https://manual.example/cap4')
            ->where('homework.resources.0', 'Culegerea, p. 12')
            ->has('homework.files', 2)
            // PNG-ul REAL primește previzualizare de imagine…
            ->where('homework.files.0.preview', 'image')
            // …iar HTML-ul deghizat în „.png" NU primește nimic: conținutul decide, nu numele.
            ->where('homework.files.1.preview', null));
});

it('tema ALTEI clase răspunde 404 — nu confirmă existența unui id iterat', function () {
    $world = homeworkDetailWorld();

    $foreign = HomeworkAssignment::query()->create([
        'subject_name' => 'Chimie',
        'author_name' => 'Alt Profesor',
        'grade_level' => 9,
        'section' => null,
        'assigned_on' => '2026-03-12',
        'topic' => 'Alt subiect',
    ]);

    $this->actingAs($world['parent'])
        ->get(route('cabinet.homework.show', $foreign))
        ->assertNotFound();
});

it('personalul e redirecționat din pagina temei spre panou (cabinetul e al familiei)', function () {
    homeworkDetailWorld();
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Profesor->value);

    $homework = HomeworkAssignment::query()->firstOrFail();

    $this->actingAs($staff)
        ->get(route('cabinet.homework.show', $homework))
        ->assertRedirect('/admin');
});

it('previzualizarea servește inline DOAR conținut pasiv verificat: PNG da, HTML deghizat nu', function () {
    $world = homeworkDetailWorld();

    // PNG real → inline, cu tipul DETECTAT și nosniff.
    $real = $this->actingAs($world['parent'])
        ->get(route('cabinet.homework.attachment.view', ['homework' => $world['homework']->id, 'index' => 0]));
    $real->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Type', 'image/png');
    expect($real->headers->get('content-disposition'))->toContain('inline');

    // HTML botezat „.png" → attachment: pică verificarea de conținut, nu se randează pe origine.
    // (Disposition-ul e garanția: cu `attachment` + nosniff, browserul salvează, nu execută.)
    $fake = $this->actingAs($world['parent'])
        ->get(route('cabinet.homework.attachment.view', ['homework' => $world['homework']->id, 'index' => 1]));
    $fake->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($fake->headers->get('content-disposition'))->toContain('attachment');
});

it('descărcarea rămâne attachment chiar și pentru tipurile care AR putea inline', function () {
    $world = homeworkDetailWorld();

    $response = $this->actingAs($world['parent'])
        ->get(route('cabinet.homework.attachment', ['homework' => $world['homework']->id, 'index' => 0]));

    $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('content-disposition'))->toContain('attachment');
});

it('fișierele temei rămân închise pentru familia ALTEI clase', function () {
    $world = homeworkDetailWorld();

    $otherYear = AcademicYear::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherYear)->create(['grade_level' => 9, 'section' => 'B']);
    $otherStudent = Student::factory()->create();
    Enrollment::factory()->for($otherStudent)->for($otherClass)->for($otherYear)->create();

    $stranger = User::factory()->create();
    $stranger->assignRole(UserRole::Parinte->value);
    $stranger->students()->attach($otherStudent->id);

    $this->actingAs($stranger)
        ->get(route('cabinet.homework.attachment.view', ['homework' => $world['homework']->id, 'index' => 0]))
        ->assertForbidden();

    $this->actingAs($stranger)
        ->get(route('cabinet.homework.show', $world['homework']))
        ->assertNotFound();
});
