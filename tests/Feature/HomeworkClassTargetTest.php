<?php

/**
 * ȚINTA TEMEI = CLASA (2026-08-07).
 *
 * Până acum tema își găsea publicul prin (`grade_level`, `section`) — o pereche unică doar cât
 * timp exista un singur an școlar. De la deschiderea anului 2026–2027, „III L" există de două ori
 * (an vechi și an nou), iar tema uneia apărea și la cealaltă. În paralel, DREPTUL de a da temă se
 * verifică pe `school_class_id` (alocările profesorului) — ținta și permisiunea vorbeau chei
 * diferite. Aici se fixează cheia unică, pe toate ușile: cabinet, panou, policy, calendar.
 *
 * Ramura pe pereche NU dispare — o păstrează temele pe TOATĂ treapta și rândurile vechi pe care
 * backfill-ul nu le-a putut atribui (teme din golul dintre ani). Ambele ramuri se testează aici.
 */

use App\Enums\UserRole;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
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
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    // Doi ani, fiecare cu propria „7 A" — clasele OMONIME care produceau scurgerea.
    $this->oldYear = AcademicYear::factory()->create(['starts_on' => '2025-09-01', 'ends_on' => '2026-07-31']);
    $this->year = AcademicYear::factory()->create([
        'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31', 'is_current' => true,
    ]);
    Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31', 'is_current' => true,
    ]);

    $this->oldClass = SchoolClass::factory()->for($this->oldYear)->create(['grade_level' => 7, 'section' => 'A']);
    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 7, 'section' => 'A']);
    $this->subject = Subject::factory()->create(['name' => 'Matematică']);
});

/** Temă țintită pe o clasă anume (cum scrie panoul de la 07.08.2026 încoace). */
function hwTargetFor(SchoolClass $class, Subject $subject, ?Teacher $author = null): HomeworkAssignment
{
    return HomeworkAssignment::factory()->create([
        'school_class_id' => $class->id,
        'grade_level' => $class->grade_level,
        'section' => $class->section,
        'subject_id' => $subject->id,
        'subject_name' => $subject->name,
        'teacher_id' => $author?->id,
        'assigned_on' => '2026-10-01',
    ]);
}

// ─── Regula de potrivire (scopeForClass) ─────────────────────────────────────────────────

it('tema cu clasă se vede DOAR la clasa ei, nu și la omonima din alt an', function () {
    $mine = hwTargetFor($this->class, $this->subject);
    $theirs = hwTargetFor($this->oldClass, $this->subject);

    expect(HomeworkAssignment::query()->forClass($this->class)->pluck('id')->all())->toBe([$mine->id])
        ->and(HomeworkAssignment::query()->forClass($this->oldClass)->pluck('id')->all())->toBe([$theirs->id]);
});

it('rândul vechi (fără clasă) rămâne pe pereche — ambele clase îl văd', function () {
    $legacy = HomeworkAssignment::factory()->create([
        'school_class_id' => null, 'grade_level' => 7, 'section' => 'A',
        'subject_id' => $this->subject->id, 'assigned_on' => '2026-08-05',
    ]);

    expect(HomeworkAssignment::query()->forClass($this->class)->pluck('id')->all())->toBe([$legacy->id])
        ->and(HomeworkAssignment::query()->forClass($this->oldClass)->pluck('id')->all())->toBe([$legacy->id]);
});

it('tema pe TOATĂ treapta ajunge la fiecare clasă a treptei, dar nu la altă treaptă', function () {
    $wholeGrade = HomeworkAssignment::factory()->create([
        'school_class_id' => null, 'grade_level' => 7, 'section' => null,
        'subject_id' => $this->subject->id, 'assigned_on' => '2026-10-01',
    ]);
    $otherLetter = SchoolClass::factory()->for($this->year)->create(['grade_level' => 7, 'section' => 'B']);
    $otherGrade = SchoolClass::factory()->for($this->year)->create(['grade_level' => 8, 'section' => 'A']);

    expect(HomeworkAssignment::query()->forClass($this->class)->pluck('id')->all())->toBe([$wholeGrade->id])
        ->and(HomeworkAssignment::query()->forClass($otherLetter)->pluck('id')->all())->toBe([$wholeGrade->id])
        ->and(HomeworkAssignment::query()->forClass($otherGrade)->count())->toBe(0);
});

// ─── Cabinetul familiei: elevul clasei noi nu vede tema clasei vechi ──────────────────────

it('CABINET: elevul vede temele clasei lui, nu pe ale clasei omonime din anul trecut', function () {
    $mine = hwTargetFor($this->class, $this->subject);
    hwTargetFor($this->oldClass, $this->subject);

    $user = User::factory()->create();
    $user->assignRole(UserRole::Elev->value);
    $student = Student::factory()->create(['user_id' => $user->id]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'school_class_id' => $this->class->id,
        'academic_year_id' => $this->year->id,
    ]);

    actingAs($user)->get(route('cabinet.homework'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('homework.0.id', $mine->id)
            ->count('homework', 1));
});

// ─── Accesul la fișierele temei trece prin aceeași regulă ─────────────────────────────────

it('ACCES: tema clasei omonime nu e „a familiei" — pagina de detaliu dă 404', function () {
    $theirs = hwTargetFor($this->oldClass, $this->subject);

    $user = User::factory()->create();
    $user->assignRole(UserRole::Elev->value);
    $student = Student::factory()->create(['user_id' => $user->id]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'school_class_id' => $this->class->id,
        'academic_year_id' => $this->year->id,
    ]);

    expect($theirs->isVisibleToFamilyOf($user))->toBeFalse();

    actingAs($user)->get(route('cabinet.homework.show', $theirs))->assertNotFound();
});

// ─── Panoul: profesorul clasei noi nu ajunge la tema clasei vechi ─────────────────────────

it('PANOU: profesorul alocat pe clasa nouă nu vede tema clasei omonime din anul trecut', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);

    // Ambele teme sunt semnate de ALT autor: singura cale de acces rămâne ramura alocărilor.
    $other = Teacher::factory()->create();
    $mine = hwTargetFor($this->class, $this->subject, $other);
    hwTargetFor($this->oldClass, $this->subject, $other);

    actingAs($user);

    expect(HomeworkAssignmentResource::getEloquentQuery()->pluck('id')->all())->toBe([$mine->id]);
});

// ─── Policy: dirigenția e a clasei, nu a perechii ─────────────────────────────────────────

it('POLICY: dirigintele corectează tema clasei lui, nu pe a clasei omonime', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Diriginte->value);
    $homeroom = Teacher::factory()->create(['user_id' => $user->id]);
    $this->class->update(['homeroom_teacher_id' => $homeroom->id]);

    $mine = hwTargetFor($this->class, $this->subject, Teacher::factory()->create());
    $theirs = hwTargetFor($this->oldClass, $this->subject, Teacher::factory()->create());

    expect($user->can('update', $mine))->toBeTrue()
        ->and($user->can('update', $theirs))->toBeFalse();
});

it('POLICY: dirigintele unei clase FĂRĂ literă își corectează tema — clasa o identifică', function () {
    // Regula veche refuza orice temă cu secție NULL („toată treapta"). Cu clasa salvată, o clasă
    // unică pe treaptă (fără literă) nu mai e ambiguă.
    $single = SchoolClass::factory()->for($this->year)->create(['grade_level' => 5, 'section' => null]);

    $user = User::factory()->create();
    $user->assignRole(UserRole::Diriginte->value);
    $homeroom = Teacher::factory()->create(['user_id' => $user->id]);
    $single->update(['homeroom_teacher_id' => $homeroom->id]);

    $own = hwTargetFor($single, $this->subject, Teacher::factory()->create());
    $wholeGrade = HomeworkAssignment::factory()->create([
        'school_class_id' => null, 'grade_level' => 5, 'section' => null,
        'subject_id' => $this->subject->id, 'teacher_id' => Teacher::factory()->create()->id,
    ]);

    expect($user->can('update', $own))->toBeTrue()
        // Tema pe toată treapta rămâne a autorului/administrației.
        ->and($user->can('update', $wholeGrade))->toBeFalse();
});
