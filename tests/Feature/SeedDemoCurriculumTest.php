<?php

/**
 * Școala demo completată (cerința beneficiarului, 04.08.2026): clasele I ale anului nou + gama
 * completă de discipline pe treaptă, cu note, absențe, teme și orar.
 *
 * Testele apără exact promisiunile care se pot strica tăcut: că nu se alocă discipline imposibile,
 * că dirigintele primește o coadă de triaj reală, că tot ce se creează e marcat sau înregistrat în
 * manifest — și că `--remove` chiar întoarce baza la starea dinainte (curățarea de go-live).
 */

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::delete(storage_path('app/demo/curriculum.json'));

    // Nomenclator minim, pe trepte — potrivirea comenzii se face pe NUME + treaptă.
    foreach ([
        ['Limba și literatura română', 1, 4, 'cd'],
        ['Matematică', 1, 4, 'cd'],
        ['Educație muzicală', 1, 4, 'd'],
        ['Limba și literatura română', 5, 12, 'n'],
        ['Matematică', 5, 12, 'n'],
        ['Fizică', 6, 12, 'n'],
    ] as [$name, $min, $max, $type]) {
        Subject::factory()->create(['name' => $name, 'grade_levels' => range($min, $max), 'grading_type' => $type]);
    }

    $this->year = AcademicYear::factory()->create([
        'name' => '2025–2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-06-30', 'is_current' => true,
    ]);
    $this->term = Term::factory()->for($this->year)->create([
        'number' => 2, 'is_current' => true,
        'starts_on' => Carbon::today()->subMonths(3)->toDateString(),
        'ends_on' => Carbon::today()->addMonth()->toDateString(),
    ]);

    $this->nextYear = AcademicYear::factory()->create([
        'name' => '2026–2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30',
    ]);

    // O clasă demo primară, cu un elev demo și o alocare IMPOSIBILĂ (Fizică într-a II-a).
    $this->teacher = Teacher::factory()->create(['last_name' => '[DEMO]', 'first_name' => 'Învățătoare']);
    $this->class = SchoolClass::factory()->for($this->year)->create([
        'name' => '[DEMO] 2A', 'section' => 'A', 'grade_level' => 2, 'homeroom_teacher_id' => $this->teacher->id,
    ]);
    $this->student = Student::factory()->create(['last_name' => '[DEMO] Popescu', 'first_name' => 'Ana']);
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create(['left_on' => null]);

    DB::table('teaching_assignments')->insert([
        'teacher_id' => $this->teacher->id,
        'subject_id' => Subject::query()->where('name', 'Fizică')->value('id'),
        'school_class_id' => $this->class->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
});

afterEach(fn () => File::delete(storage_path('app/demo/curriculum.json')));

it('dă clasei gama treptei ei și curăță disciplinele imposibile', function () {
    $this->artisan('app:seed-demo-curriculum', ['--first-graders' => 2])->assertSuccessful();

    $subjects = DB::table('teaching_assignments')
        ->join('subjects', 'subjects.id', '=', 'teaching_assignments.subject_id')
        ->where('teaching_assignments.school_class_id', $this->class->id)
        ->whereNull('teaching_assignments.deleted_at')
        ->pluck('subjects.name')
        ->all();

    // Fizica (min. clasa a VI-a) a dispărut; disciplinele primare au intrat.
    expect($subjects)->not->toContain('Fizică')
        ->and($subjects)->toContain('Limba și literatura română', 'Matematică', 'Educație muzicală');

    // Notele respectă tipul de notare: la primar, calificative, nu cifre.
    $grade = DB::table('grades')->where('school_class_id', $this->class->id)->first();

    expect($grade)->not->toBeNull()
        ->and($grade->value)->toBeNull()
        ->and($grade->calificativ)->not->toBeNull();
});

it('creează clasele I ale anului NOU cu boboci, discipline și orar', function () {
    $this->artisan('app:seed-demo-curriculum', ['--first-graders' => 3])->assertSuccessful();

    $first = SchoolClass::query()
        ->where('academic_year_id', $this->nextYear->id)
        ->where('grade_level', 1)
        ->get();

    expect($first)->toHaveCount(2)
        ->and($first->every(fn (SchoolClass $c): bool => str_starts_with((string) $c->name, '[DEMO]')))->toBeTrue();

    $one = $first->first();

    expect(Enrollment::query()->where('school_class_id', $one->id)->count())->toBe(3)
        ->and(DB::table('teaching_assignments')->where('school_class_id', $one->id)->count())->toBeGreaterThan(0)
        ->and(DB::table('lessons')->where('school_class_id', $one->id)->count())->toBe(25)
        // Anul nou n-a început: fără note.
        ->and(DB::table('grades')->where('school_class_id', $one->id)->count())->toBe(0);
});

it('lasă dirigintelui absențe FĂRĂ STATUT — coada lui de triaj', function () {
    $this->artisan('app:seed-demo-curriculum', ['--first-graders' => 2])->assertSuccessful();

    expect(DB::table('absences')->where('school_class_id', $this->class->id)->whereNull('is_motivated')->count())
        ->toBeGreaterThan(0);
});

it('„--remove" întoarce baza la starea dinainte — curățarea de go-live', function () {
    $before = [
        'classes' => SchoolClass::query()->count(),
        'students' => Student::query()->count(),
        'teachers' => Teacher::query()->count(),
        'lessons' => DB::table('lessons')->count(),
        'homework' => DB::table('homework_assignments')->count(),
    ];

    $this->artisan('app:seed-demo-curriculum', ['--first-graders' => 3])->assertSuccessful();

    expect(SchoolClass::query()->count())->toBeGreaterThan($before['classes']);

    $this->artisan('app:seed-demo-curriculum', ['--remove' => true])->assertSuccessful();

    // Tot ce a creat comanda a dispărut. Alocările NU revin la numărul inițial: cea imposibilă
    // (Fizică într-a II-a) a fost curățată deliberat și rămâne ștearsă — vezi NOTE-DEV-DEPLOY §1.2.
    expect(SchoolClass::query()->count())->toBe($before['classes'])
        ->and(Student::query()->count())->toBe($before['students'])
        ->and(Teacher::query()->count())->toBe($before['teachers'])
        ->and(DB::table('lessons')->count())->toBe($before['lessons'])
        ->and(DB::table('homework_assignments')->count())->toBe($before['homework'])
        ->and(DB::table('teaching_assignments')->count())->toBe(0)
        ->and(File::exists(storage_path('app/demo/curriculum.json')))->toBeFalse();
});

it('refuză o a doua rulare peste un manifest existent', function () {
    $this->artisan('app:seed-demo-curriculum', ['--first-graders' => 2])->assertSuccessful();
    $this->artisan('app:seed-demo-curriculum')->assertFailed();
});

it('lasă contul demo de profesor CU discipline de predat', function () {
    // Contul demo, legat de o fișă de profesor care nu preda nimic (alocările lui vechi fuseseră
    // curățate ca imposibile). Fără el în catedră, testerul se autentifica pe rolul „profesor" și
    // găsea catalogul gol.
    $account = User::factory()->create(['name' => '[DEMO] Profesor', 'email' => 'profesor@columna.test']);
    $teacher = Teacher::factory()->create(['last_name' => '[DEMO] Catedră', 'user_id' => $account->id]);

    // O clasă de gimnaziu demo, cu Matematica deja alocată ALTCUIVA (zona demo veche).
    $other = Teacher::factory()->create(['last_name' => '[DEMO] Altcineva']);
    $gimnaziu = SchoolClass::factory()->for($this->year)->create([
        'name' => '[DEMO] 6A', 'section' => 'A', 'grade_level' => 6, 'homeroom_teacher_id' => $other->id,
    ]);
    $student = Student::factory()->create(['last_name' => '[DEMO] Rusu']);
    Enrollment::factory()->for($student)->for($gimnaziu)->for($this->year)->create(['left_on' => null]);

    DB::table('teaching_assignments')->insert([
        'teacher_id' => $other->id,
        'subject_id' => Subject::query()->where('name', 'Matematică')->coveringGrade(5)->value('id'),
        'school_class_id' => $gimnaziu->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('app:seed-demo-curriculum', ['--first-graders' => 2])->assertSuccessful();

    $mine = DB::table('teaching_assignments')->where('teacher_id', $teacher->id)->whereNull('deleted_at');

    expect($mine->count())->toBeGreaterThan(0)
        // Notele DISCIPLINEI îl urmează: catalogul nu poate arăta note puse de cineva care nu predă.
        ->and(DB::table('grades')
            ->where('school_class_id', $gimnaziu->id)
            ->where('subject_id', Subject::query()->where('name', 'Matematică')->coveringGrade(5)->value('id'))
            ->where('teacher_id', '!=', $teacher->id)
            ->count())->toBe(0);
});

it('nu ia învățătorului disciplina de trunchi când o preia contul demo', function () {
    $account = User::factory()->create(['name' => '[DEMO] Profesor', 'email' => 'profesor@columna.test']);
    Teacher::factory()->create(['last_name' => '[DEMO] Catedră', 'user_id' => $account->id]);

    $this->artisan('app:seed-demo-curriculum', ['--first-graders' => 2])->assertSuccessful();

    // Clasa primară din fixture: Matematica rămâne la învățătoarea-dirigintă.
    $primary = DB::table('teaching_assignments')
        ->join('subjects', 'subjects.id', '=', 'teaching_assignments.subject_id')
        ->where('teaching_assignments.school_class_id', $this->class->id)
        ->where('subjects.name', 'Matematică')
        ->value('teaching_assignments.teacher_id');

    expect((int) $primary)->toBe($this->teacher->id);
});

it('curăță clasele demo rămase FĂRĂ manifest — plasa de siguranță de go-live', function () {
    $before = ['classes' => SchoolClass::query()->count(), 'students' => Student::query()->count()];

    $this->artisan('app:seed-demo-curriculum', ['--first-graders' => 2])->assertSuccessful();

    // Manifestul se pierde (rulare de teste care curăță `storage/app/demo`, restaurare parțială,
    // proiect copiat fără `storage`). Rândurile din baza de date rămân — și ele contează.
    File::delete(storage_path('app/demo/curriculum.json'));

    $this->artisan('app:seed-demo-curriculum', ['--remove' => true])->assertSuccessful();

    expect(SchoolClass::query()->where('grade_level', 1)->count())->toBe(0)
        ->and(SchoolClass::query()->count())->toBe($before['classes'])
        ->and(Student::query()->count())->toBe($before['students'])
        // Temele nu poartă clasa (treaptă + literă), deci se pot rata tăcut la măturare.
        ->and(DB::table('homework_assignments')->where('grade_level', 1)->count())->toBe(0);
});

it('se re-rulează peste o clasă I demo rămasă, fără să lovească indexul unic', function () {
    $this->artisan('app:seed-demo-curriculum', ['--first-graders' => 2])->assertSuccessful();

    $existing = SchoolClass::query()->where('grade_level', 1)->pluck('id')->sort()->values();

    File::delete(storage_path('app/demo/curriculum.json'));

    // Fără manifest, comanda pornește din nou: clasa I A există deja, iar (an, treaptă, literă) e
    // unic — inclusiv pentru rândurile șterse logic. Rândul demo se reutilizează.
    $this->artisan('app:seed-demo-curriculum', ['--first-graders' => 2])->assertSuccessful();

    expect(SchoolClass::query()->where('grade_level', 1)->pluck('id')->sort()->values()->all())
        ->toBe($existing->all());
});

it('nu atinge o clasă I REALĂ de pe aceeași poziție', function () {
    $real = SchoolClass::factory()->for($this->nextYear)->create([
        'name' => 'I', 'section' => 'A', 'grade_level' => 1,
    ]);

    $this->artisan('app:seed-demo-curriculum', ['--first-graders' => 2])->assertFailed();

    expect($real->fresh()->name)->toBe('I');
});

it('contul demo de director primește dirigenția clasei a II-a, cu trunchiul ei de discipline', function () {
    // Cerința beneficiarului (05.08.2026): director@ să fie diriginte la „[DEMO] 2A" ȘI să aibă
    // acces la clasă și ca profesor. La primar cele două sunt același om — învățătorul.
    $account = User::factory()->create(['name' => '[DEMO] Director', 'email' => 'director@columna.test']);
    $teacher = Teacher::factory()->create(['last_name' => '[DEMO] Ursu', 'user_id' => $account->id]);

    $inainte = $this->class->homeroom_teacher_id;

    $this->artisan('app:seed-demo-curriculum', ['--first-graders' => 2])->assertSuccessful();

    expect((int) $this->class->fresh()->homeroom_teacher_id)->toBe($teacher->id)
        ->and($inainte)->not->toBe($teacher->id);

    // Trunchiul (română, matematică…) e al lui; specialiștii rămân ai catedrei.
    $mine = DB::table('teaching_assignments')
        ->join('subjects', 'subjects.id', '=', 'teaching_assignments.subject_id')
        ->where('teaching_assignments.school_class_id', $this->class->id)
        ->where('teaching_assignments.teacher_id', $teacher->id)
        ->pluck('subjects.name')
        ->all();

    expect($mine)->toContain('Limba și literatura română', 'Matematică')
        ->and($mine)->not->toContain('Educație muzicală')
        // Notele trunchiului îl urmează: catalogul nu poate arăta note puse de cine nu predă.
        ->and(DB::table('grades')
            ->where('school_class_id', $this->class->id)
            ->where('subject_id', Subject::query()->where('name', 'Matematică')->coveringGrade(1)->value('id'))
            ->where('teacher_id', '!=', $teacher->id)
            ->count())->toBe(0);
});

it('„--fix-accounts" leagă conturile fără re-seed și e idempotentă', function () {
    $this->artisan('app:seed-demo-curriculum', ['--first-graders' => 2])->assertSuccessful();

    $noteInainte = DB::table('grades')->count();

    // Contul apare ABIA acum (așa se întâmplă în practică: zona demo e deja populată).
    $account = User::factory()->create(['name' => '[DEMO] Director', 'email' => 'director@columna.test']);
    $teacher = Teacher::factory()->create(['last_name' => '[DEMO] Ursu', 'user_id' => $account->id]);

    $this->artisan('app:seed-demo-curriculum', ['--fix-accounts' => true])->assertSuccessful();

    expect((int) $this->class->fresh()->homeroom_teacher_id)->toBe($teacher->id)
        // Reparația NU rescrie datele: notele rămân câte erau.
        ->and(DB::table('grades')->count())->toBe($noteInainte);

    // A doua rulare nu mai are ce muta.
    $this->artisan('app:seed-demo-curriculum', ['--fix-accounts' => true])->assertSuccessful();

    expect((int) $this->class->fresh()->homeroom_teacher_id)->toBe($teacher->id)
        ->and(DB::table('grades')->count())->toBe($noteInainte);
});
