<?php

/**
 * Corigența ca PROCES (LOT 7 al restructurării „Configurare").
 *
 * Până aici existau piesele (sesiune, comisie, examen), dar nu și lanțul care le leagă: examenele se
 * introduceau una câte una, se legau manual de sesiune, iar data putea cădea oriunde. Testele
 * ancorează regulile procesului — mai ales pe cele care spun ce NU are voie să se întâmple automat.
 */

use App\Actions\GenerateCorigentaExams;
use App\Enums\CorigentaSeason;
use App\Enums\CorigentaSessionStatus;
use App\Enums\CorigentaSessionType;
use App\Enums\NotificationType;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\CorigentaSessions\Pages\ListCorigentaSessions;
use App\Models\AcademicYear;
use App\Models\CorigentaExam;
use App\Models\CorigentaSession;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\SemesterValidation;
use App\Models\Student;
use App\Models\Subject;
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
    $this->term = Term::factory()->for($this->year)->create(['number' => 2, 'is_current' => true]);
    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 8]);
    $this->subject = Subject::factory()->create(['min_grade' => 5, 'max_grade' => 12]);
});

/** Elev înmatriculat, cu o medie restantă la disciplina testului. */
function failingStudent(object $ctx, float $value = 3.5): Student
{
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($ctx->class)->for($ctx->year)->create();

    TermAverage::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $ctx->subject->id,
        'term_id' => $ctx->term->id,
        'value' => $value,
    ]);

    return $student;
}

it('generarea pe semestru urmează hotărârea Consiliului, nu mediile brute', function () {
    $validat = failingStudent($this);
    $nevalidat = failingStudent($this);

    SemesterValidation::create([
        'student_id' => $validat->id,
        'term_id' => $this->term->id,
        'status' => StudentStatus::Corigent,
        'validated_at' => now(),
    ]);

    $result = app(GenerateCorigentaExams::class)->forTerm($this->term);

    expect($result['students'])->toBe(1)
        ->and($result['exams'])->toBe(1)
        // Al doilea elev are medie sub 5, dar Consiliul nu s-a pronunțat: e RAPORTAT, nu procesat.
        // A genera pe medii ar transforma un raport statistic în hotărâre.
        ->and($result['pending'])->toBe(1);

    expect(CorigentaExam::query()->where('student_id', $validat->id)->count())->toBe(1)
        ->and(CorigentaExam::query()->where('student_id', $nevalidat->id)->count())->toBe(0);
});

it('generarea pe semestru e idempotentă — se poate relua fără duplicate', function () {
    $student = failingStudent($this);
    SemesterValidation::create([
        'student_id' => $student->id,
        'term_id' => $this->term->id,
        'status' => StudentStatus::Corigent,
        'validated_at' => now(),
    ]);

    $action = app(GenerateCorigentaExams::class);
    $action->forTerm($this->term);
    $action->forTerm($this->term);

    expect(CorigentaExam::query()->where('student_id', $student->id)->count())->toBe(1);
});

it('comanda lucrează implicit pe semestrul curent și semnalează elevii neajunși la Consiliu', function () {
    $student = failingStudent($this);
    SemesterValidation::create([
        'student_id' => $student->id,
        'term_id' => $this->term->id,
        'status' => StudentStatus::Corigent,
        'validated_at' => now(),
    ]);

    // Un al doilea elev restant, fără validare → apare în avertisment.
    failingStudent($this);

    $this->artisan('app:generate-corigenta')
        ->expectsOutputToContain('1 elevi, 1 intrări de corigență')
        ->expectsOutputToContain('fără statut validat')
        ->assertSuccessful();
});

it('sesiunea își adună examenele nelegate din același an și sezon, nu pe ale altora', function () {
    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);
    actingAs($ao);

    $student = failingStudent($this);
    // Discipline distincte: examenul e unic pe (elev, disciplină, semestru).
    $altaDisciplina = Subject::factory()->create(['min_grade' => 5, 'max_grade' => 12]);
    $aTreiaDisciplina = Subject::factory()->create(['min_grade' => 5, 'max_grade' => 12]);

    // Examen din anul și sezonul sesiunii (sem. II → vară) — trebuie atras.
    $alSesiunii = CorigentaExam::create([
        'student_id' => $student->id,
        'subject_id' => $this->subject->id,
        'term_id' => $this->term->id,
        'season' => CorigentaSeason::Vara,
        'corigenta_session_id' => null,
    ]);

    // Alt sezon, același an — NU e al acestei sesiuni.
    $altSezon = CorigentaExam::create([
        'student_id' => $student->id,
        'subject_id' => $altaDisciplina->id,
        'term_id' => Term::factory()->for($this->year)->create(['number' => 1])->id,
        'season' => CorigentaSeason::Iarna,
        'corigenta_session_id' => null,
    ]);

    // Deja legat de altă sesiune — nu se re-atribuie.
    $altaSesiune = CorigentaSession::create([
        'academic_year_id' => $this->year->id,
        'season' => CorigentaSeason::Vara,
        'type' => CorigentaSessionType::Baza,
        'starts_on' => '2027-06-10',
        'ends_on' => '2027-06-20',
        'status' => CorigentaSessionStatus::Approved,
    ]);
    $legatDeja = CorigentaExam::create([
        'student_id' => $student->id,
        'subject_id' => $aTreiaDisciplina->id,
        'term_id' => $this->term->id,
        'season' => CorigentaSeason::Vara,
        'corigenta_session_id' => $altaSesiune->id,
    ]);

    // PUBLICATĂ deliberat: cazul operațional real e elevul validat corigent DUPĂ publicare, care
    // trebuie totuși legat de sesiunea în curs — dacă acțiunea s-ar ascunde aici, exact el ar
    // rămâne de rezolvat manual.
    $session = CorigentaSession::create([
        'academic_year_id' => $this->year->id,
        'season' => CorigentaSeason::Vara,
        'type' => CorigentaSessionType::Baza,
        'starts_on' => '2027-06-10',
        'ends_on' => '2027-06-20',
        'status' => CorigentaSessionStatus::Published,
    ]);

    Livewire::test(ListCorigentaSessions::class)
        ->callTableAction('attachExams', $session);

    expect($alSesiunii->refresh()->corigenta_session_id)->toBe($session->id)
        ->and($altSezon->refresh()->corigenta_session_id)->toBeNull()
        ->and($legatDeja->refresh()->corigenta_session_id)->toBe($altaSesiune->id);
});

it('familia vede data și comisia doar după PUBLICAREA sesiunii', function () {
    $student = failingStudent($this);

    $session = CorigentaSession::create([
        'academic_year_id' => $this->year->id,
        'season' => CorigentaSeason::Vara,
        'type' => CorigentaSessionType::Baza,
        'starts_on' => '2027-06-10',
        'ends_on' => '2027-06-20',
        'status' => CorigentaSessionStatus::Approved,
    ]);

    CorigentaExam::create([
        'student_id' => $student->id,
        'subject_id' => $this->subject->id,
        'term_id' => $this->term->id,
        'season' => CorigentaSeason::Vara,
        'corigenta_session_id' => $session->id,
        'scheduled_on' => '2027-06-15',
    ]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    // `corigentaExams` e prop DEFER — se citește prin partial reload (JSON), nu din randarea inițială.
    $read = fn (): array => $this->actingAs($parent)
        ->get(
            "/cabinet/elev/{$student->id}",
            inertiaPartialHeaders('cabinet/student-profile', 'corigentaExams'),
        )
        ->json('props.corigentaExams');

    $beforePublish = $read();

    // Disciplina restantă se vede (vine din propriile medii), calendarul NU: sesiunea aprobată dar
    // nepublicată e încă o propunere de lucru.
    expect($beforePublish)->toHaveCount(1)
        ->and($beforePublish[0]['scheduledOn'])->toBeNull()
        ->and($beforePublish[0]['sessionType'])->toBeNull();

    $session->update(['status' => CorigentaSessionStatus::Published]);

    $afterPublish = $read();

    expect($afterPublish[0]['scheduledOn'])->toBe('15.06.2027')
        ->and($afterPublish[0]['sessionType'])->not->toBeNull();
});

it('nota de corigență anunță familia o singură dată, la consemnare', function () {
    Notification::fake();

    $student = failingStudent($this);
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    $exam = CorigentaExam::create([
        'student_id' => $student->id,
        'subject_id' => $this->subject->id,
        'term_id' => $this->term->id,
        'season' => CorigentaSeason::Vara,
        'mark' => null,
    ]);

    // Programarea (fără notă) nu e o veste de dat familiei.
    $exam->update(['scheduled_on' => '2027-06-15']);
    Notification::assertNothingSent();

    $exam->update(['mark' => 7]);

    Notification::assertSentTo(
        $parent,
        fn (CatalogNotification $notification): bool => $notification->type === NotificationType::CorigentaResult,
    );

    // A doua salvare, fără schimbarea notei → nicio repetare a aceleiași vești.
    Notification::fake();
    $exam->update(['exam_commission_id' => null]);
    Notification::assertNothingSent();
});
