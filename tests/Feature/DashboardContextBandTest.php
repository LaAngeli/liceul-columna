<?php

use App\Enums\UserRole;
use App\Enums\Weekday;
use App\Filament\Widgets\WelcomeWidget;
use App\Models\AcademicYear;
use App\Models\Holiday;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Support\Holidays;
use App\Support\SchoolYearRuler;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Banda de context a dashboard-ului staff: rigla anului școlar + perimetrul rolului activ +
 * lecțiile de azi. Cele două invariante pe care se sprijină designul:
 *  — rigla spune adevărul în TOATE pozițiile zilei față de an, inclusiv în „gaura" din august;
 *  — perimetrul și lecțiile urmează ROLUL ACTIV, nu fișele pe care le poartă contul.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    Holidays::flush();
});

afterEach(function () {
    Carbon::setTestNow();
    Holidays::flush();
});

/** Un an cu două semestre despărțite de o vacanță de o lună — acoperă și segmentul „break". */
function bandYear(): AcademicYear
{
    $year = AcademicYear::factory()->create([
        'name' => '2025–2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-07-31',
        'is_current' => true,
    ]);

    Term::factory()->for($year)->create([
        'number' => 1, 'name' => 'Semestrul I',
        'starts_on' => '2025-09-01', 'ends_on' => '2025-12-31', 'is_current' => false,
    ]);
    Term::factory()->for($year)->create([
        'number' => 2, 'name' => 'Semestrul II',
        'starts_on' => '2026-02-01', 'ends_on' => '2026-07-31', 'is_current' => true,
    ]);

    return $year;
}

it('rigla acoperă exact 100% din an, cu vacanța dintre semestre ca segment propriu', function () {
    bandYear();
    Carbon::setTestNow(Carbon::parse('2025-10-15 09:00:00'));

    $ruler = SchoolYearRuler::build();

    expect($ruler)->not->toBeNull()
        ->and(collect($ruler['segments'])->pluck('kind')->all())->toBe(['term', 'break', 'term'])
        // Lățimile sunt procente din durata anului — suma lor NU are voie să depășească rigla.
        ->and(round(collect($ruler['segments'])->sum('width')))->toBe(100.0)
        ->and($ruler['inTerm'])->toBeTrue();
});

it('rigla spune unde suntem în fiecare poziție a zilei față de an', function (string $day, string $needle, bool $inTerm, bool $hasMarker) {
    bandYear();
    Carbon::setTestNow(Carbon::parse($day.' 09:00:00'));
    app()->setLocale('ro');

    $ruler = SchoolYearRuler::build();

    expect($ruler)->not->toBeNull()
        ->and($ruler['caption'])->toContain($needle)
        ->and($ruler['inTerm'])->toBe($inTerm)
        ->and($ruler['todayPercent'] !== null)->toBe($hasMarker);
})->with([
    'în semestru' => ['2025-10-15', 'Semestrul I', true, true],
    'în vacanța dintre semestre' => ['2026-01-15', 'Vacanță', false, true],
    // Ziua din „gaura" dintre ani (anul se încheie pe 31 iulie): fără marcaj pe riglă, dar cu
    // explicație — exact cazul în care data din antet nu spunea nimic util.
    'după finalul anului' => ['2026-08-03', 's-a încheiat', false, false],
]);

it('acordul la număr respectă regula limbii, nu concatenarea', function () {
    bandYear();
    app()->setLocale('ro');

    // Româna cere „de" de la 20 în sus: „5 zile", dar „29 DE zile". O interpolare simplă ar fi
    // produs „29 zile" — greșit gramatical în chiar propoziția cea mai citită a benzii.
    Carbon::setTestNow(Carbon::parse('2025-12-27 09:00:00')); // 4 zile până la 31.12
    expect(SchoolYearRuler::build()['caption'])->toContain('4 zile')->not->toContain('de zile');

    Carbon::setTestNow(Carbon::parse('2025-12-02 09:00:00')); // 29 de zile până la 31.12
    expect(SchoolYearRuler::build()['caption'])->toContain('29 de zile');
});

it('perimetrul urmează ROLUL ACTIV, nu fișele purtate de cont', function () {
    $year = bandYear();
    Carbon::setTestNow(Carbon::parse('2025-10-15 09:00:00'));

    // Director care are ȘI fișă de profesor cu clase — cazul care dădea „10 clase" în loc de
    // „Toată școala", adică opusul perimetrului sub care lucrează efectiv.
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $teacher = Teacher::factory()->create(['user_id' => $director->id]);
    SchoolClass::factory()->for($year)->create(['homeroom_teacher_id' => $teacher->id]);

    $this->actingAs($director);

    Livewire::test(WelcomeWidget::class)
        ->assertOk()
        ->assertSee(__('panel.role_switch.scope_school'))
        ->assertDontSee(__('panel.role_switch.scope_infra'));
});

it('profesorul își vede clasele nominal, iar lecțiile de azi doar pe ale lui', function () {
    $year = bandYear();
    // Miercuri în semestrul I.
    Carbon::setTestNow(Carbon::parse('2025-10-15 09:00:00'));

    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $other = Teacher::factory()->create();

    $class = SchoolClass::factory()->for($year)->create(['name' => 'IX', 'section' => 'A']);
    $subject = Subject::factory()->create();

    // Orarul refuză două lecții în același interval al aceleiași clase → fiecare la altă oră.
    foreach ([3, 4] as $number) {
        Lesson::factory()->create([
            'academic_year_id' => $year->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id,
            'teacher_id' => $teacher->id, 'day_of_week' => Weekday::Wednesday, 'lesson_number' => $number, 'room' => '24',
        ]);
    }
    // Lecțiile altui profesor, în aceeași zi, NU au voie să intre în numărătoare.
    foreach ([5, 6, 7] as $number) {
        Lesson::factory()->create([
            'academic_year_id' => $year->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id,
            'teacher_id' => $other->id, 'day_of_week' => Weekday::Wednesday, 'lesson_number' => $number,
        ]);
    }

    $this->actingAs($user);

    Livewire::test(WelcomeWidget::class)
        ->assertOk()
        ->assertSee('IX A')
        ->assertSee(trans_choice('panel.widgets.hero.today.lessons', 2, ['count' => 2]));
});

it('ziua liberă închide orarul, oricâte lecții ar fi în tabelă pentru acea zi', function () {
    $year = bandYear();
    Carbon::setTestNow(Carbon::parse('2025-10-15 09:00:00'));

    Holiday::factory()->create(['starts_on' => '2025-10-15', 'ends_on' => '2025-10-15']);
    Holidays::flush();

    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $class = SchoolClass::factory()->for($year)->create();

    foreach ([1, 2, 3, 4] as $number) {
        Lesson::factory()->create([
            'academic_year_id' => $year->id, 'school_class_id' => $class->id,
            'subject_id' => Subject::factory()->create()->id,
            'teacher_id' => $teacher->id, 'day_of_week' => Weekday::Wednesday, 'lesson_number' => $number,
        ]);
    }

    $this->actingAs($user);

    Livewire::test(WelcomeWidget::class)
        ->assertOk()
        ->assertSee(__('panel.widgets.hero.today.closed'));
});

it('rigla e clickabilă doar pentru cine poate configura școala', function () {
    bandYear();
    Carbon::setTestNow(Carbon::parse('2025-10-15 09:00:00'));

    // Administratorul operațional configurează școala → segmentele duc la Semestre / Zile libere.
    $operational = User::factory()->create();
    $operational->assignRole(UserRole::AdministratorOperational->value);
    $this->actingAs($operational);
    Livewire::test(WelcomeWidget::class)->assertOk()->assertSee('fi-ruler__segment', escape: false)
        ->assertSee('<a class="fi-ruler__segment', escape: false);

    // Prim-vicedirectorul NU configurează → aceleași segmente, dar fără linkuri: un indicator care
    // ar duce în 403 e mai rău decât unul care nu promite nimic.
    $viceDirector = User::factory()->create();
    $viceDirector->assignRole(UserRole::PrimVicedirector->value);
    $this->actingAs($viceDirector);
    Livewire::test(WelcomeWidget::class)->assertOk()->assertSee('fi-ruler__segment', escape: false)
        ->assertDontSee('<a class="fi-ruler__segment', escape: false);
});

it('administratorul tehnic nu primește nicio cifră academică', function () {
    bandYear();
    Carbon::setTestNow(Carbon::parse('2025-10-15 09:00:00'));

    $technical = User::factory()->create();
    $technical->assignRole(UserRole::AdministratorTehnic->value);
    $this->actingAs($technical);

    Livewire::test(WelcomeWidget::class)
        ->assertOk()
        ->assertSee(__('panel.role_switch.scope_infra'))
        // Zona „azi" lipsește cu totul: §3.2 îl ține în afara datelor academice, iar un număr de
        // lecții ar fi exact scurgerea pe care separarea lui o previne. Asertăm pe BLOCUL zonei,
        // nu pe eticheta „Azi" — aceasta apare și în `title`-ul marcajului de pe riglă.
        ->assertDontSee('fi-welcome__today', escape: false);
});
