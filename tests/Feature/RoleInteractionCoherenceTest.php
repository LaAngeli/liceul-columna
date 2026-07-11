<?php

/**
 * Cluster nomenclatoare, lotul C — coerența interacțiunilor între roluri:
 *  - validarea OFICIALĂ a statutului anunță familia și curăță examenele de corigență rămase
 *    fără obiect la re-validare (Corigent → Promovat);
 *  - dreptul de validare a motivărilor urmează dirigintele CURENT (înmatricularea cea mai
 *    recentă), nu pe cel din anii trecuți;
 *  - radarul „clase fără diriginte" vede și clasa al cărei diriginte are fișa ARHIVATĂ;
 *  - cozile pending (motivări, cereri) nu se blochează cu cererile elevilor ARHIVAȚI.
 */

use App\Enums\CorigentaSeason;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\AbsenceMotivations\AbsenceMotivationResource;
use App\Filament\Resources\DocumentRequests\DocumentRequestResource;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Models\AbsenceMotivation;
use App\Models\AcademicYear;
use App\Models\CorigentaExam;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TermAverage;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    $this->term = Term::factory()->for($this->year)->create(['is_current' => true]);
    $this->class = SchoolClass::factory()->for($this->year)->create();

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
});

// ─── Validarea oficială a statutului ─────────────────────────────────────────────────────

it('validarea statutului anunță familia și curăță examenele de corigență NEDATE la re-validare', function () {
    $familyUser = User::factory()->create();
    $familyUser->assignRole(UserRole::Elev->value);
    $student = Student::factory()->create(['user_id' => $familyUser->id]);
    Enrollment::factory()->for($student)->for($this->class)->for($this->year)->create();
    TermAverage::factory()->create([
        'student_id' => $student->id, 'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $this->class->id, 'term_id' => $this->term->id, 'value' => 4.00,
    ]);

    // Din validarea anterioară „Corigent": un examen DAT (cu notă) + unul NEDAT.
    $marked = CorigentaExam::create([
        'student_id' => $student->id, 'subject_id' => Subject::factory()->create()->id,
        'term_id' => $this->term->id, 'season' => CorigentaSeason::Vara, 'mark' => 6.00,
    ]);
    $unmarked = CorigentaExam::create([
        'student_id' => $student->id, 'subject_id' => Subject::factory()->create()->id,
        'term_id' => $this->term->id, 'season' => CorigentaSeason::Vara, 'mark' => null,
    ]);

    actingAs($this->director);
    Notification::fake();

    Livewire::test(ListStudents::class)
        ->callTableAction('validateStatus', $student, ['status' => 'promovat'])
        ->assertHasNoTableActionErrors();

    // Familia află statutul OFICIAL — nu depinde de vizita spontană în cabinet.
    Notification::assertSentTo(
        $familyUser,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::StatusChange,
    );

    // Examenul NEDAT a rămas fără obiect → scos; cel DAT = istoric de examen, rămâne.
    expect(CorigentaExam::query()->whereKey($unmarked->id)->exists())->toBeFalse()
        ->and(CorigentaExam::query()->whereKey($marked->id)->exists())->toBeTrue();
});

// ─── Dirigintele CURENT validează motivările, nu cel fost ────────────────────────────────

it('fostul diriginte nu mai vede și nu mai validează motivările fostului elev', function () {
    // Doi ani cu ordine EXPLICITĂ (id-ul anului nou > id-ul anului vechi, ca și cronologia).
    $oldYear = AcademicYear::factory()->create(['name' => '2098–2099', 'starts_on' => '2098-09-01', 'ends_on' => '2099-06-30']);
    $newYear = AcademicYear::factory()->create(['name' => '2099–2100', 'starts_on' => '2099-09-01', 'ends_on' => '2100-06-30']);

    // Anul TRECUT: clasa veche, diriginte T1. Anul CURENT: clasa nouă, diriginte T2.
    $t1User = User::factory()->create();
    $t1User->assignRole(UserRole::Diriginte->value);
    $t1 = Teacher::factory()->create(['user_id' => $t1User->id]);
    $oldClass = SchoolClass::factory()->for($oldYear)->create(['homeroom_teacher_id' => $t1->id]);

    $t2User = User::factory()->create();
    $t2User->assignRole(UserRole::Diriginte->value);
    $t2 = Teacher::factory()->create(['user_id' => $t2User->id]);
    $newClass = SchoolClass::factory()->for($newYear)->create(['homeroom_teacher_id' => $t2->id]);

    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->create(['school_class_id' => $oldClass->id, 'academic_year_id' => $oldYear->id]);
    Enrollment::factory()->for($student)->create(['school_class_id' => $newClass->id, 'academic_year_id' => $newYear->id]);

    $motivation = AbsenceMotivation::factory()->create([
        'student_id' => $student->id,
        'status' => RequestStatus::Pending,
    ]);

    // Dreptul de validare urmează înmatricularea cea mai RECENTĂ: T2 da, T1 (fostul) nu.
    expect($motivation->canBeReviewedBy($t2User))->toBeTrue()
        ->and($motivation->canBeReviewedBy($t1User))->toBeFalse();

    // Coada dirigintelui urmează aceeași regulă: T1 nu o mai vede, T2 da.
    actingAs($t1User);
    expect(AbsenceMotivationResource::getEloquentQuery()->pluck('id'))->not->toContain($motivation->id);

    actingAs($t2User);
    expect(AbsenceMotivationResource::getEloquentQuery()->pluck('id'))->toContain($motivation->id);
});

// ─── Radarul claselor fără diriginte ─────────────────────────────────────────────────────

it('clasa cu dirigintele ARHIVAT apare în radarul „clase fără diriginte"', function () {
    $teacher = Teacher::factory()->create();
    $this->class->update(['homeroom_teacher_id' => $teacher->id]);
    Enrollment::factory()->for(Student::factory()->create())->for($this->class)->for($this->year)->create();

    // Cu diriginte activ → nu e în radar.
    expect(SchoolClass::query()->withoutHomeroom()->pluck('id'))->not->toContain($this->class->id);

    // Fișa dirigintelui e arhivată → clasa rămâne fără diriginte REAL → intră în radar.
    $teacher->delete();

    expect(SchoolClass::query()->withoutHomeroom()->pluck('id'))->toContain($this->class->id);
});

// ─── Cozile pending și elevii arhivați ───────────────────────────────────────────────────

it('cozile pending (motivări + cereri) nu numără cererile elevilor ARHIVAȚI', function () {
    $student = Student::factory()->create();
    AbsenceMotivation::factory()->create(['student_id' => $student->id, 'status' => RequestStatus::Pending]);
    DocumentRequest::factory()->create(['student_id' => $student->id, 'status' => RequestStatus::Pending]);

    actingAs($this->director);

    AbsenceMotivationResource::flushPendingCache();
    expect(AbsenceMotivationResource::pendingMotivations()->count())->toBe(1)
        ->and(DocumentRequestResource::getNavigationBadge())->toBe('1');

    $student->delete();

    AbsenceMotivationResource::flushPendingCache();
    expect(AbsenceMotivationResource::pendingMotivations()->count())->toBe(0)
        ->and(DocumentRequestResource::getNavigationBadge())->toBeNull();
});
