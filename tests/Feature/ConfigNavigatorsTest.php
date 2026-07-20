<?php

/**
 * Categoria CONFIGURARE ca navigator (2026-07-16): Ani școlari = HUB-uri (carduri cu conținutul
 * anului + sărituri pre-filtrate + operațiuni), secțiunile per-an (Semestre / Sesiuni / Comisii /
 * Sumative) = pastile pe ani cu anul curent implicit, Examenele = pastile pe SESIUNE, Orarele =
 * carduri pe cele 9 tipuri, Orarul structurat = ani → clase → lecțiile clasei.
 */

use App\Enums\CorigentaSeason;
use App\Enums\CorigentaSessionStatus;
use App\Enums\CorigentaSessionType;
use App\Enums\ScheduleType;
use App\Enums\UserRole;
use App\Enums\Weekday;
use App\Filament\Resources\AcademicYears\Pages\ListAcademicYears;
use App\Filament\Resources\CorigentaExams\Pages\ListCorigentaExams;
use App\Filament\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Resources\Lessons\Pages\ListLessons;
use App\Filament\Resources\Schedules\Pages\ListSchedules;
use App\Filament\Resources\Terms\Pages\ListTerms;
use App\Models\AcademicYear;
use App\Models\CorigentaExam;
use App\Models\CorigentaSession;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->oldYear = AcademicYear::factory()->create(['name' => '2019–2020']);
    $this->year = AcademicYear::factory()->create(['name' => '2025–2026']);
    $this->currentTerm = Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);
    $this->oldTerm = Term::factory()->for($this->oldYear)->create([
        'number' => 1, 'starts_on' => '2019-09-01', 'ends_on' => '2020-01-31',
    ]);

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
    actingAs($this->director);
});

it('anii școlari sunt HUB-uri: badge „An curent", conținutul anului și sărituri pre-filtrate', function () {
    SchoolClass::factory()->for($this->year)->create();

    $cards = collect(Livewire::test(ListAcademicYears::class)->instance()->yearCards());

    // Cei mai noi întâi; anul curent poartă badge-ul.
    expect($cards->pluck('id')->all())->toBe([$this->year->id, $this->oldYear->id])
        ->and($cards[0]['current'])->toBeTrue()
        ->and($cards[1]['current'])->toBeFalse()
        // Conținutul: 1 semestru + 1 clasă în anul curent.
        ->and($cards[0]['stats'][0])->toContain('1')
        ->and($cards[0]['stats'][1])->toContain('1');

    // Săriturile duc în secțiunile pre-filtrate pe an.
    foreach ($cards[0]['links'] as $url) {
        expect($url)->toContain('an='.$this->year->id);
    }

    expect($cards[0]['edit_url'])->not->toBeNull()
        ->and($cards[0]['can_archive'])->toBeTrue();
});

it('semestrele au pastile pe ani, cu anul CURENT implicit și tabelul restrâns', function () {
    $component = Livewire::test(ListTerms::class);
    $page = $component->instance();

    expect($page->activeYearId())->toBe($this->year->id)
        ->and(collect($page->yearPills())->pluck('id')->all())->toBe([$this->year->id, $this->oldYear->id]);

    $component
        ->assertCanSeeTableRecords([$this->currentTerm])
        ->assertCanNotSeeTableRecords([$this->oldTerm]);

    $component->call('openYear', $this->oldYear->id)
        ->assertCanSeeTableRecords([$this->oldTerm])
        ->assertCanNotSeeTableRecords([$this->currentTerm]);
});

it('examenele de corigență au pastile pe SESIUNE, cu bucket pentru cele fără sesiune', function () {
    $session = CorigentaSession::create([
        'academic_year_id' => $this->year->id,
        'season' => CorigentaSeason::Vara,
        'type' => CorigentaSessionType::Baza,
        'starts_on' => '2026-06-15',
        'ends_on' => '2026-06-25',
        'status' => CorigentaSessionStatus::Draft,
    ]);

    $subject = Subject::factory()->create();

    $inSession = CorigentaExam::create([
        'student_id' => Student::factory()->create()->id, 'subject_id' => $subject->id,
        'term_id' => $this->currentTerm->id, 'season' => CorigentaSeason::Vara,
        'corigenta_session_id' => $session->id,
    ]);
    $orphan = CorigentaExam::create([
        'student_id' => Student::factory()->create()->id, 'subject_id' => $subject->id,
        'term_id' => $this->currentTerm->id, 'season' => CorigentaSeason::Vara,
    ]);

    $component = Livewire::test(ListCorigentaExams::class);
    $page = $component->instance();

    // Pastile: sesiunea (an · sezon) + bucket-ul „Fără sesiune"; implicit = sesiunea cea mai recentă.
    expect(collect($page->yearPills())->pluck('id')->all())->toBe([$session->id, 0])
        ->and($page->activeYearId())->toBe($session->id);

    $component
        ->assertCanSeeTableRecords([$inSession])
        ->assertCanNotSeeTableRecords([$orphan]);

    $component->call('openYear', 0)
        ->assertCanSeeTableRecords([$orphan])
        ->assertCanNotSeeTableRecords([$inSession]);
});

it('orarele publicabile sunt carduri pe cele 9 tipuri, cu „fără date" semnalat', function () {
    // Orarele sunt ale administratorului operațional (canManageSchedules), nu ale directorului.
    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);
    actingAs($ao);

    Schedule::factory()->create(['type' => ScheduleType::Bells, 'is_public' => true, 'label' => 'Sunete']);
    $lessonsSchedule = Schedule::factory()->create(['type' => ScheduleType::Lessons, 'is_public' => false, 'label' => 'Lecții X']);

    $component = Livewire::test(ListSchedules::class);
    $page = $component->instance();

    $cards = collect($page->typeCards());

    // Toate cele 9 tipuri, în ordinea enum-ului; tipul gol poartă avertismentul.
    expect($cards)->toHaveCount(count(ScheduleType::cases()));

    $bells = $cards->firstWhere('id', ScheduleType::Bells->value);
    $exams = $cards->firstWhere('id', ScheduleType::Exams->value);

    expect($bells['badge'])->toBeNull()
        ->and($bells['stats'][1])->toContain('1')
        ->and($exams['badge'])->not->toBeNull();

    // Contextul tipului restrânge tabelul.
    $component->call('openType', ScheduleType::Lessons->value)
        ->assertCanSeeTableRecords([$lessonsSchedule]);

    expect($component->instance()->activeType())->toBe(ScheduleType::Lessons);
});

it('orarul structurat: ani → clase (cu „fără orar" semnalat) → lecțiile clasei, cu adăugarea pre-completată', function () {
    // Orarul structurat e al administratorului operațional (canManageSchedules).
    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);
    actingAs($ao);

    $classA = SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'grade_level' => 7, 'section' => 'A']);
    $classB = SchoolClass::factory()->for($this->year)->create(['name' => 'VIII', 'grade_level' => 8, 'section' => 'B']);

    $lesson = Lesson::factory()->create([
        'academic_year_id' => $this->year->id,
        'school_class_id' => $classA->id,
        'subject_id' => Subject::factory()->create()->id,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 1,
    ]);

    $component = Livewire::test(ListLessons::class);
    $page = $component->instance();

    expect($page->activeYearId())->toBe($this->year->id);

    $cards = collect($page->classCards())->keyBy('id');
    expect($cards->get($classA->id)['badge'])->toBeNull()
        ->and($cards->get($classB->id)['badge'])->not->toBeNull();

    // REGULĂ RESCRISĂ (restructurarea UI 2026-07-20): destinația drill-down-ului e GRILA
    // săptămânală, nu tabelul — lecția apare în matricea zi × oră; tabelul clasic rămâne
    // vedere secundară (?vedere=lista), cu aceleași rânduri.
    $component->call('openClass', $classA->id);

    $grid = $component->instance()->timetableGrid();

    expect($grid)->not->toBeNull()
        ->and($grid['cells'][Weekday::Monday->value][1]['subject'] ?? null)->not->toBeNull();

    $component->call('openListView')
        ->assertCanSeeTableRecords([$lesson]);

    // Adăugarea din context vine pre-completată (an + clasă).
    Livewire::withQueryParams(['an' => (string) $this->year->id, 'clasa' => (string) $classA->id])
        ->test(CreateLesson::class)
        ->assertFormSet([
            'academic_year_id' => $this->year->id,
            'school_class_id' => $classA->id,
        ]);
});
