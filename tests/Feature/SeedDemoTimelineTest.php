<?php

/**
 * Axa de timp a școlii demo (cerința beneficiarului, 04.08.2026): activitate în TRECUT și în
 * PREZENT. Testele apără ce s-ar strica tăcut — că trecutul chiar acoperă semestrul precedent, că
 * un semestru încheiat nu poartă absențe „fără statut" (imposibil în realitate: termenul a expirat),
 * că elevii conturilor demo primesc GARANTAT ce se testează pe ei, și că `--remove` curăță exact.
 */

use App\Enums\UserRole;
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
use Spatie\Permission\Models\Role;

beforeEach(function () {
    File::delete(storage_path('app/demo/timeline.json'));

    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create([
        'name' => '2025–2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-06-30', 'is_current' => true,
    ]);
    $this->past = Term::factory()->for($this->year)->create([
        'number' => 1, 'is_current' => false,
        'starts_on' => Carbon::today()->subMonths(9)->toDateString(),
        'ends_on' => Carbon::today()->subMonths(5)->toDateString(),
    ]);
    $this->current = Term::factory()->for($this->year)->create([
        'number' => 2, 'is_current' => true,
        'starts_on' => Carbon::today()->subMonths(4)->toDateString(),
        'ends_on' => Carbon::today()->addMonth()->toDateString(),
    ]);

    $this->subject = Subject::factory()->create(['grade_levels' => range(1, 12), 'grading_type' => 'n']);
    $this->teacher = Teacher::factory()->create(['last_name' => '[DEMO]', 'first_name' => 'Profesor']);

    $this->class = SchoolClass::factory()->for($this->year)->create([
        'name' => '[DEMO] 5A', 'section' => 'A', 'grade_level' => 5,
    ]);

    DB::table('teaching_assignments')->insert([
        'teacher_id' => $this->teacher->id, 'subject_id' => $this->subject->id,
        'school_class_id' => $this->class->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Un elev obișnuit + unul legat de un CONT demo (pe el se intră la testare).
    foreach ([null, '[DEMO] Elev de test'] as $accountName) {
        $student = Student::factory()->create(['last_name' => '[DEMO] Popescu']);

        if ($accountName !== null) {
            $account = User::factory()->create(['name' => $accountName]);
            $account->assignRole(UserRole::Elev->value);
            $student->update(['user_id' => $account->id]);
            $this->accountStudent = $student;
        }

        Enrollment::factory()->for($student)->for($this->class)->for($this->year)->create(['left_on' => null]);
    }
});

afterEach(function () {
    File::delete(storage_path('app/demo/timeline.json'));
    Carbon::setTestNow();
});

it('populează ȘI semestrul precedent, ȘI ultimele săptămâni ale celui curent', function () {
    $this->artisan('app:seed-demo-timeline')->assertSuccessful();

    $past = DB::table('grades')->where('term_id', $this->past->id)->count();
    $present = DB::table('grades')->where('term_id', $this->current->id)->count();

    expect($past)->toBeGreaterThan(0)
        ->and($present)->toBeGreaterThan(0);

    // Prezentul e o fereastră scurtă, lipită de azi — nu tot semestrul.
    $earliestPresent = DB::table('grades')->where('term_id', $this->current->id)->min('graded_on');

    expect(Carbon::parse($earliestPresent)->greaterThanOrEqualTo(Carbon::today()->subDays(22)))->toBeTrue();
});

it('un semestru încheiat nu poartă absențe „fără statut"', function () {
    $this->artisan('app:seed-demo-timeline')->assertSuccessful();

    // În trecut totul e decis (termenul de motivare a expirat, consolidarea le-ar fi închis)…
    expect(DB::table('absences')->where('term_id', $this->past->id)->whereNull('is_motivated')->count())->toBe(0)
        ->and(DB::table('absences')->where('term_id', $this->past->id)->count())->toBeGreaterThan(0)
        // …iar nemotivatele din trecut sunt consolidate.
        ->and(DB::table('absences')->where('term_id', $this->past->id)->where('is_motivated', false)->whereNull('motivation_locked_at')->count())->toBe(0);

    // În prezent există coadă de triaj.
    expect(DB::table('absences')->where('term_id', $this->current->id)->whereNull('is_motivated')->count())->toBeGreaterThan(0);
});

it('elevul unui CONT demo primește garantat absențe în ambele semestre', function () {
    $this->artisan('app:seed-demo-timeline')->assertSuccessful();

    $id = $this->accountStudent->id;

    expect(DB::table('absences')->where('student_id', $id)->where('term_id', $this->past->id)->count())->toBeGreaterThanOrEqual(4)
        ->and(DB::table('absences')->where('student_id', $id)->where('term_id', $this->current->id)->count())->toBeGreaterThanOrEqual(2)
        // Cel puțin una fără statut: dirigintele are ce tria, indiferent de zar.
        ->and(DB::table('absences')->where('student_id', $id)->whereNull('is_motivated')->count())->toBeGreaterThan(0);
});

it('„--remove" șterge exact ce a adăugat', function () {
    $before = [
        'grades' => DB::table('grades')->count(),
        'absences' => DB::table('absences')->count(),
        'homework' => DB::table('homework_assignments')->count(),
    ];

    $this->artisan('app:seed-demo-timeline')->assertSuccessful();

    expect(DB::table('grades')->count())->toBeGreaterThan($before['grades']);

    $this->artisan('app:seed-demo-timeline', ['--remove' => true])->assertSuccessful();

    expect(DB::table('grades')->count())->toBe($before['grades'])
        ->and(DB::table('absences')->count())->toBe($before['absences'])
        ->and(DB::table('homework_assignments')->count())->toBe($before['homework'])
        ->and(File::exists(storage_path('app/demo/timeline.json')))->toBeFalse();
});

it('recalculul mediilor retrage mediile rămase fără note', function () {
    // O medie fantomă: disciplina n-are nicio notă pentru elevul ăsta.
    DB::table('term_averages')->insert([
        'student_id' => $this->accountStudent->id,
        'subject_id' => $this->subject->id,
        'school_class_id' => $this->class->id,
        'term_id' => $this->past->id,
        'value' => 9.5,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('app:compute-averages')->assertSuccessful();

    expect(DB::table('term_averages')
        ->where('student_id', $this->accountStudent->id)
        ->where('subject_id', $this->subject->id)
        ->where('term_id', $this->past->id)
        ->whereNull('deleted_at')
        ->count())->toBe(0);
});
