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
        Subject::factory()->create(['name' => $name, 'min_grade' => $min, 'max_grade' => $max, 'grading_type' => $type]);
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
