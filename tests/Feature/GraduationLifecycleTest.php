<?php

use App\Actions\BroadcastAnnouncement;
use App\Actions\Enrollments\GraduateClasses;
use App\Actions\Enrollments\MarkDeparture;
use App\Enums\AnnouncementAudience;
use App\Enums\DepartureReason;
use App\Enums\DocumentRequestType;
use App\Enums\GeneratedDocumentType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * Ciclul de viață al ABSOLVENTULUI: iese din fluxurile operaționale, dar păstrează accesul
 * read-only la propria arhivă (decizia beneficiarului, 2026-08-03).
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Un an cu o clasă terminală (a XII-a) și una intermediară, plus elevi activi în fiecare.
 *
 * @return array{year: AcademicYear, twelfth: SchoolClass, seventh: SchoolClass}
 */
function graduationYear(): array
{
    $year = AcademicYear::factory()->create([
        'name' => '2025–2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31', 'is_current' => true,
    ]);
    Term::factory()->for($year)->create([
        'number' => 1, 'name' => 'Semestrul I', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31', 'is_current' => true,
    ]);

    return [
        'year' => $year,
        'twelfth' => SchoolClass::factory()->for($year)->create(['grade_level' => 12, 'name' => 'XII', 'section' => 'A']),
        'seventh' => SchoolClass::factory()->for($year)->create(['grade_level' => 7, 'name' => 'VII', 'section' => 'A']),
    ];
}

function enrolStudent(SchoolClass $class, AcademicYear $year): Student
{
    $student = Student::factory()->create();
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'school_class_id' => $class->id,
        'academic_year_id' => $year->id,
        'enrolled_on' => '2025-09-01',
        'left_on' => null,
    ]);

    return $student;
}

it('absolvirea scoate promoția din registru cu motiv, la data încheierii anului', function () {
    ['year' => $year, 'twelfth' => $twelfth, 'seventh' => $seventh] = graduationYear();

    $graduate = enrolStudent($twelfth, $year);
    $continuing = enrolStudent($seventh, $year);

    $result = app(GraduateClasses::class)->handle($year);

    expect($result['graduated'])->toBe(1)
        ->and($graduate->refresh()->hasActiveEnrollment())->toBeFalse()
        ->and($graduate->departureReason())->toBe(DepartureReason::Absolvire)
        ->and($graduate->isAlumnus())->toBeTrue()
        // Data e cea de REGISTRU (finalul anului), nu ziua în care s-a apăsat butonul.
        ->and($graduate->departedOn()?->toDateString())->toBe('2026-07-31')
        // Clasa a VII-a nu e atinsă: absolvește doar treapta terminală.
        ->and($continuing->refresh()->hasActiveEnrollment())->toBeTrue();
});

it('absolvirea e idempotentă — reluarea nu mai are ce marca', function () {
    ['year' => $year, 'twelfth' => $twelfth] = graduationYear();
    enrolStudent($twelfth, $year);

    $action = app(GraduateClasses::class);

    expect($action->pendingCount($year))->toBe(1)
        ->and($action->handle($year)['graduated'])->toBe(1)
        ->and($action->pendingCount($year))->toBe(0)
        ->and($action->handle($year)['graduated'])->toBe(0);
});

it('CEASUL DE RETENȚIE pornește abia la absolvire (L133 §7)', function () {
    ['year' => $year, 'twelfth' => $twelfth] = graduationYear();
    $graduate = enrolStudent($twelfth, $year);

    // Predicatul lui `app:purge-expired-students`: dosarul devine eligibil doar dacă are o
    // înmatriculare ÎNCHISĂ. Fără pasul de absolvire, `left_on` rămânea null → dosarul nu intra
    // NICIODATĂ în retenție, adică exact promoțiile care trebuie să iasă primele nu ieșeau deloc.
    $eligible = fn (): bool => Student::query()
        ->whereKey($graduate->id)
        ->whereDoesntHave('enrollments', fn ($q) => $q->whereNull('left_on'))
        ->whereHas('enrollments', fn ($q) => $q->whereNotNull('left_on'))
        ->exists();

    expect($eligible())->toBeFalse();

    app(GraduateClasses::class)->handle($year);

    expect($eligible())->toBeTrue();
});

it('absolventul iese din totalurile școlii și din audiența „toate familiile"', function () {
    ['year' => $year, 'twelfth' => $twelfth, 'seventh' => $seventh] = graduationYear();

    $graduate = enrolStudent($twelfth, $year);
    $continuing = enrolStudent($seventh, $year);

    foreach ([$graduate, $continuing] as $student) {
        $account = User::factory()->create();
        $account->assignRole(UserRole::Elev->value);
        $student->update(['user_id' => $account->id]);
    }

    app(GraduateClasses::class)->handle($year);

    $announcement = Announcement::factory()->create(['audience' => AnnouncementAudience::Families]);
    $recipients = app(BroadcastAnnouncement::class)->resolveRecipients($announcement);

    expect(Student::query()->currentlyEnrolled()->count())->toBe(1)
        ->and(Student::query()->count())->toBe(2)
        // Contul absolventului rămâne, dar anunțurile școlii nu-l mai privesc.
        ->and($recipients->pluck('id')->all())->toBe([$continuing->refresh()->user_id]);
});

it('absolventul păstrează accesul la profil, dar pierde fluxurile operaționale', function () {
    ['year' => $year, 'twelfth' => $twelfth] = graduationYear();
    $graduate = enrolStudent($twelfth, $year);

    $account = User::factory()->create();
    $account->assignRole(UserRole::Elev->value);
    $graduate->update(['user_id' => $account->id]);

    app(GraduateClasses::class)->handle($year);

    $this->actingAs($account)->withSession(['auth.password_confirmed_at' => time()])
        ->get('/cabinet/elev/'.$graduate->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/student-profile')
            ->where('isAlumnus', true)
            // Motivarea absențelor se stinge: nu mai există ore de la care să lipsească.
            ->where('canRequestMotivation', false)
            // Cererile rămân — pentru asta i s-a păstrat accesul.
            ->where('canRequestDocument', true)
            ->where('requestTypes', DocumentRequestType::alumniOptions())
        );
});

it('serverul refuză fluxurile închise, chiar dacă cineva ocolește interfața', function () {
    ['year' => $year, 'twelfth' => $twelfth] = graduationYear();
    $graduate = enrolStudent($twelfth, $year);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($graduate->id);

    app(GraduateClasses::class)->handle($year);

    $as = fn () => $this->actingAs($parent)->withSession(['auth.password_confirmed_at' => time()]);

    // Motivarea absențelor — flux încheiat.
    $as()->post("/cabinet/elev/{$graduate->id}/motivare", [
        'reason' => 'x', 'period_start' => '2026-03-01', 'period_end' => '2026-03-01',
    ])->assertForbidden();

    // Cerere de TRANSFER — presupune o școală de unde pleci.
    $as()->post("/cabinet/elev/{$graduate->id}/cereri", [
        'type' => DocumentRequestType::Transfer->value, 'details' => 'x',
    ])->assertForbidden();

    // Documentul „situația semestrului curent" — s-ar genera despre un semestru inexistent.
    $as()->get("/cabinet/elev/{$graduate->id}/document/".GeneratedDocumentType::TermSituation->value)
        ->assertForbidden();

    // Foaia matricolă — exact ce are voie, și motivul pentru care accesul rămâne deschis.
    $as()->get("/cabinet/elev/{$graduate->id}/document/".GeneratedDocumentType::Transcript->value)
        ->assertOk();
});

it('părintele cu un copil absolvent și unul activ vede fiecare copil altfel', function () {
    ['year' => $year, 'twelfth' => $twelfth, 'seventh' => $seventh] = graduationYear();

    $graduate = enrolStudent($twelfth, $year);
    $continuing = enrolStudent($seventh, $year);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach([$graduate->id, $continuing->id]);

    app(GraduateClasses::class)->handle($year);

    $as = fn () => $this->actingAs($parent)->withSession(['auth.password_confirmed_at' => time()]);

    // Gating-ul stă pe ELEV, nu pe cont: același părinte, două regimuri în același cabinet.
    $as()->get('/cabinet/elev/'.$graduate->id)
        ->assertInertia(fn (Assert $page) => $page->where('isAlumnus', true)->where('canRequestMotivation', false));

    $as()->get('/cabinet/elev/'.$continuing->id)
        ->assertInertia(fn (Assert $page) => $page->where('isAlumnus', false)->where('canRequestMotivation', true));

    // Contul păstrează un copil în școală → rămâne în circuitul operațional.
    expect($parent->hasAnyActiveStudent())->toBeTrue();
});

it('transferul și exmatricularea NU dau acces de absolvent', function (string $reason) {
    ['year' => $year, 'twelfth' => $twelfth] = graduationYear();
    $student = enrolStudent($twelfth, $year);

    app(MarkDeparture::class)->handle(
        array_values($student->enrollments()->pluck('id')->map(intval(...))->all()),
        Carbon::parse('2026-03-01'),
        DepartureReason::from($reason),
    );

    // Iese la fel de „inactiv", dar actele lui le eliberează școala unde a plecat.
    expect($student->refresh()->hasActiveEnrollment())->toBeFalse()
        ->and($student->isAlumnus())->toBeFalse();
})->with([DepartureReason::Transfer->value, DepartureReason::Exmatriculare->value]);

it('motivul nu poate exista fără dată de plecare', function () {
    ['year' => $year, 'twelfth' => $twelfth] = graduationYear();
    $student = enrolStudent($twelfth, $year);

    $enrollment = $student->enrollments()->first();
    $enrollment->update(['departure_reason' => DepartureReason::Absolvire]);

    // Garda de model îl golește: un rând „plecat, nu se știe când" ar apărea activ în totaluri
    // (care citesc data) și plecat în fișă (care citește motivul).
    expect($enrollment->refresh()->departure_reason)->toBeNull();
});
