<?php

/**
 * Operațiunile de REGISTRU (restructurare 2026-08-02): înmatricularea în masă, promovarea între
 * ani, transferul între clase și plecarea — scoase din formular/tabel în Actions, ca să existe o
 * singură cale (testabilă) pentru toate suprafețele: un rând, o selecție sau o clasă întreagă.
 */

use App\Actions\Enrollments\EnrollStudents;
use App\Actions\Enrollments\MarkDeparture;
use App\Actions\Enrollments\PromoteClass;
use App\Actions\Enrollments\TransferEnrollment;
use App\Enums\UserRole;
use App\Filament\Resources\Enrollments\Pages\ListEnrollments;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

afterEach(fn () => Carbon::setTestNow());

/** Un configurator autentificat (AO) — scrierea din registru îi aparține. */
function enrollmentOperator(): User
{
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $user = User::factory()->create();
    $user->assignRole(UserRole::AdministratorOperational->value);
    test()->actingAs($user);

    return $user;
}

it('înmatriculează mai mulți elevi dintr-o dată, sărind pe cei care au deja un rând în anul acela', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 5]);
    $other = SchoolClass::factory()->for($year)->create(['grade_level' => 5]);

    $fresh = Student::factory()->count(3)->create();
    $alreadyActive = Student::factory()->create();
    $alreadyArchived = Student::factory()->create();

    Enrollment::factory()->for($alreadyActive)->for($other)->for($year)->create();
    // Rândul ARHIVAT ocupă la fel de tare anul: indexul unic îl vede, deci recrearea ar cădea.
    Enrollment::factory()->for($alreadyArchived)->for($other)->for($year)->create()->delete();

    $result = app(EnrollStudents::class)->handle(
        $class,
        [...$fresh->pluck('id')->all(), $alreadyActive->id, $alreadyArchived->id],
    );

    expect($result['enrolled'])->toBe(3)
        ->and($result['skipped'])->toBe(2)
        ->and($result['blocked'])->toBeFalse()
        ->and(Enrollment::query()->where('school_class_id', $class->id)->count())->toBe(3)
        // Data implicită = azi, pe ora școlii; anul vine din clasă, nu din apelant.
        ->and(Enrollment::query()->where('school_class_id', $class->id)->first()->academic_year_id)->toBe($year->id);
});

it('nu înmatriculează în anul ÎNCHIS — registrul lui e istorie, nu spațiu de lucru', function () {
    // Clasa se creează cât anul e deschis (modelul ei refuză anii închiși), apoi anul se închide.
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $year->update(['closed_at' => now()]);
    $class->refresh();

    $student = Student::factory()->create();

    $result = app(EnrollStudents::class)->handle($class, [$student->id]);

    expect($result['blocked'])->toBeTrue()
        ->and(Enrollment::query()->count())->toBe(0);
});

it('promovează în anul următor doar elevii ACTIVI, fără să atingă registrul anului sursă', function () {
    $source = AcademicYear::factory()->create(['name' => '2025–2026']);
    $target = AcademicYear::factory()->create(['name' => '2026–2027']);

    $sourceClass = SchoolClass::factory()->for($source)->create(['grade_level' => 5, 'section' => 'A']);
    $targetClass = SchoolClass::factory()->for($target)->create(['grade_level' => 6, 'section' => 'A']);

    $staying = Student::factory()->count(2)->create();
    $departed = Student::factory()->create();

    foreach ($staying as $student) {
        Enrollment::factory()->for($student)->for($sourceClass, 'schoolClass')->for($source, 'academicYear')->create(['left_on' => null]);
    }
    Enrollment::factory()->for($departed)->for($sourceClass, 'schoolClass')->for($source, 'academicYear')
        ->create(['left_on' => now()->subMonth()]);

    $result = app(PromoteClass::class)->handle($sourceClass, $targetClass);

    expect($result['enrolled'])->toBe(2)
        ->and(Enrollment::query()->where('school_class_id', $targetClass->id)->count())->toBe(2)
        // Cine a plecat nu urcă odată cu clasa.
        ->and(Enrollment::query()->where('school_class_id', $targetClass->id)->where('student_id', $departed->id)->exists())->toBeFalse()
        // Registrul anului sursă rămâne întreg — promovarea adaugă, nu mută.
        ->and(Enrollment::query()->where('school_class_id', $sourceClass->id)->count())->toBe(3);

    // Re-rulare: nimeni nu se dublează (idempotentă pe an).
    expect(app(PromoteClass::class)->handle($sourceClass, $targetClass)['enrolled'])->toBe(0);
});

it('promovarea cere ANI diferiți; sugestia de țintă urcă o treaptă pe aceeași secțiune', function () {
    $year = AcademicYear::factory()->create();
    $next = AcademicYear::factory()->create();

    $sourceClass = SchoolClass::factory()->for($year)->create(['grade_level' => 5, 'section' => 'A']);
    $sameYearClass = SchoolClass::factory()->for($year)->create(['grade_level' => 6, 'section' => 'A']);
    $expected = SchoolClass::factory()->for($next)->create(['grade_level' => 6, 'section' => 'A']);
    SchoolClass::factory()->for($next)->create(['grade_level' => 6, 'section' => 'B']);

    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($sourceClass, 'schoolClass')->for($year, 'academicYear')->create();

    // Aceeași pereche de ani = transfer, nu promovare.
    expect(app(PromoteClass::class)->handle($sourceClass, $sameYearClass)['blocked'])->toBeTrue()
        ->and(Enrollment::query()->where('school_class_id', $sameYearClass->id)->count())->toBe(0);

    expect(app(PromoteClass::class)->suggestTarget($sourceClass, $next->id)?->id)->toBe($expected->id);
});

it('transferul mută în aceeași clasă-an și refuză ținta din alt an sau elevul plecat', function () {
    $year = AcademicYear::factory()->create();
    $otherYear = AcademicYear::factory()->create();

    $from = SchoolClass::factory()->for($year)->create(['grade_level' => 7]);
    $to = SchoolClass::factory()->for($year)->create(['grade_level' => 7]);
    $foreign = SchoolClass::factory()->for($otherYear)->create(['grade_level' => 7]);

    $active = Enrollment::factory()->for(Student::factory())->for($from, 'schoolClass')->for($year, 'academicYear')->create(['left_on' => null]);
    $departed = Enrollment::factory()->for(Student::factory())->for($from, 'schoolClass')->for($year, 'academicYear')->create(['left_on' => now()]);

    expect(app(TransferEnrollment::class)->handle([$active->id, $departed->id], $to)['moved'])->toBe(1)
        ->and($active->fresh()->school_class_id)->toBe($to->id)
        ->and($departed->fresh()->school_class_id)->toBe($from->id);

    // Țintă din alt an: transferul nu traversează ani (aceea e promovarea).
    expect(app(TransferEnrollment::class)->handle([$active->id], $foreign)['moved'])->toBe(0)
        ->and($active->fresh()->school_class_id)->toBe($to->id);
});

it('plecarea se poate marca CHIAR în ziua înmatriculării, dar nu înainte de ea', function () {
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();

    $sameDay = Enrollment::factory()->for(Student::factory())->for($class, 'schoolClass')->for($year, 'academicYear')
        ->create(['enrolled_on' => '2026-09-15', 'left_on' => null]);
    $tooEarly = Enrollment::factory()->for(Student::factory())->for($class, 'schoolClass')->for($year, 'academicYear')
        ->create(['enrolled_on' => '2026-09-20', 'left_on' => null]);

    // Înscris și retras în aceeași zi = zi de registru validă (regula veche cerea strict „după").
    $result = app(MarkDeparture::class)->handle([$sameDay->id, $tooEarly->id], Carbon::parse('2026-09-15'));

    expect($result['marked'])->toBe(1)
        ->and($result['skipped'])->toBe(1)
        ->and($sameDay->fresh()->left_on->toDateString())->toBe('2026-09-15')
        // Intervalul negativ rămâne imposibil.
        ->and($tooEarly->fresh()->left_on)->toBeNull();
});

it('promovarea din pagină duce toată școala în anul nou, cu clasele fără corespondent raportate', function () {
    enrollmentOperator();

    $source = AcademicYear::factory()->create(['name' => '2025–2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-06-30']);
    $target = AcademicYear::factory()->create(['name' => '2026–2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_current' => true]);

    $fifth = SchoolClass::factory()->for($source)->create(['name' => 'V', 'grade_level' => 5, 'section' => 'A']);
    $twelfth = SchoolClass::factory()->for($source)->create(['name' => 'XII', 'grade_level' => 12, 'section' => 'A']);
    $sixth = SchoolClass::factory()->for($target)->create(['name' => 'VI', 'grade_level' => 6, 'section' => 'A']);

    foreach (Student::factory()->count(3)->create() as $student) {
        Enrollment::factory()->for($student)->for($fifth, 'schoolClass')->for($source, 'academicYear')->create(['left_on' => null]);
    }
    // Absolvenții: a XII-a n-are treaptă următoare, deci nu are unde fi promovată — se RAPORTEAZĂ.
    Enrollment::factory()->for(Student::factory())->for($twelfth, 'schoolClass')->for($source, 'academicYear')->create(['left_on' => null]);

    $component = Livewire::test(ListEnrollments::class);
    $page = $component->instance();

    expect($page->promotionSourceYears())->toHaveKey($source->id)
        ->and($page->promotionSummary($source->id))->toContain('XII');

    $page->runPromotion($source->id);

    expect(Enrollment::query()->where('school_class_id', $sixth->id)->count())->toBe(3)
        // Anul sursă rămâne întreg: promovarea adaugă un registru nou, nu îl mută pe cel vechi.
        ->and(Enrollment::query()->where('academic_year_id', $source->id)->count())->toBe(4);
});

it('„Adaugă elevi" înmatriculează o selecție întreagă în clasa deschisă', function () {
    enrollmentOperator();

    $year = AcademicYear::factory()->create(['is_current' => true]);
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 3]);
    $students = Student::factory()->count(4)->create();

    $component = Livewire::test(ListEnrollments::class);
    $component->call('openClass', $class->id);

    $page = $component->instance();

    expect($page->enrollableStudents())->toHaveCount(4);

    $page->enrollIntoActiveClass($students->pluck('id')->all());

    expect(Enrollment::query()->where('school_class_id', $class->id)->count())->toBe(4)
        // Lista de înmatriculabili se golește pe măsură ce clasa se umple.
        ->and($page->enrollableStudents())->toHaveCount(0);
});

it('căutarea filtrează clasele, iar o clasă din alt an nu poate fi deschisă prin URL', function () {
    enrollmentOperator();

    $year = AcademicYear::factory()->create(['is_current' => true]);
    $otherYear = AcademicYear::factory()->create();

    SchoolClass::factory()->for($year)->create(['name' => 'IX', 'grade_level' => 9, 'section' => 'A']);
    SchoolClass::factory()->for($year)->create(['name' => 'X', 'grade_level' => 10, 'section' => 'B']);
    $foreign = SchoolClass::factory()->for($otherYear)->create(['name' => 'XI', 'grade_level' => 11]);

    $component = Livewire::test(ListEnrollments::class);
    $page = $component->instance();

    // Fără căutare: două cicluri (gimnaziu + liceu).
    expect($page->classGroups())->toHaveCount(2);

    $component->set('classSearch', 'IX');
    $page = $component->instance();

    expect($page->classGroups())->toHaveCount(1)
        ->and($page->classGroups()[0]['cards'])->toHaveCount(1);

    // Clasa altui an nu deschide registrul sub pastila anului activ.
    $component->call('openClass', $foreign->id);

    expect($component->instance()->activeClass())->toBeNull();
});
