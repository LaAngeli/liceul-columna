<?php

/**
 * Grila săptămânală a orarului structurat (restructurarea UI 2026-07-20).
 *
 * Orarul unei clase e o matrice zi × oră; tabelul plat îl pagina la 10 rânduri și făcea golurile
 * invizibile. Testele ancorează CONTRACTUL grilei: forma datelor, scheletul scriitorului vs
 * vederea cititorului, orele citite din orarul PUBLICAT (nu inventate), marcajele „Acum"/„Urmează"
 * și pre-completarea celulelor libere.
 */

use App\Enums\ScheduleType;
use App\Enums\UserRole;
use App\Enums\Weekday;
use App\Filament\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Resources\Lessons\Pages\ListLessons;
use App\Filament\Resources\Schedules\Pages\ListSchedules;
use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\SchoolCalendar;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    Term::factory()->for($this->year)->create(['is_current' => true]);
    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 8]);
    $this->subject = Subject::factory()->create(['name' => 'Matematică', 'min_grade' => 5, 'max_grade' => 12]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function gridUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);
    actingAs($user);

    return $user;
}

function gridFor(SchoolClass $class): ?array
{
    return Livewire::withQueryParams(['clasa' => (string) $class->id])
        ->test(ListLessons::class)
        ->instance()
        ->timetableGrid();
}

it('construiește matricea zi × lecție, cu sâmbăta doar dacă are lecții', function () {
    gridUser(UserRole::AdministratorOperational);

    Lesson::factory()->create([
        'school_class_id' => $this->class->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => $this->subject->id,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 2,
    ]);

    $grid = gridFor($this->class);

    expect($grid)->not->toBeNull()
        ->and(collect($grid['days'])->pluck('value')->all())->toBe([1, 2, 3, 4, 5])
        ->and($grid['cells'][1][2]['subject'])->toBe('Matematică')
        ->and($grid['lessons'])->toBe(1);

    // O lecție sâmbăta → coloana apare.
    Lesson::factory()->create([
        'school_class_id' => $this->class->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => $this->subject->id,
        'day_of_week' => Weekday::Saturday,
        'lesson_number' => 1,
    ]);

    expect(collect(gridFor($this->class)['days'])->pluck('value')->all())->toBe([1, 2, 3, 4, 5, 6]);
});

it('scriitorul primește scheletul cu celule libere pre-completate; cititorul doar ce există', function () {
    // SCRIITOR pe clasă GOALĂ: schelet de lucru (min. 7 rânduri), nu empty state.
    gridUser(UserRole::AdministratorOperational);

    $grid = gridFor($this->class);

    expect($grid)->not->toBeNull()
        ->and($grid['slots'])->toBe(range(1, 7))
        ->and($grid['can_write'])->toBeTrue();

    // Linkul celulei libere poartă TOT contextul: clasa, ziua, lecția.
    $url = Livewire::withQueryParams(['clasa' => (string) $this->class->id])
        ->test(ListLessons::class)
        ->instance()
        ->createSlotUrl(3, 5);

    expect($url)->toContain('clasa='.$this->class->id)
        ->and($url)->toContain('zi=3')
        ->and($url)->toContain('lectie=5');

    // CITITOR (profesor cu alocare la clasă) pe aceeași clasă goală: empty state, nu grilă goală.
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);
    actingAs($teacherUser);

    expect(gridFor($this->class))->toBeNull();

    // Cu o lecție, cititorul primește grila FĂRĂ afordanțe de scriere și fără rânduri de umplere.
    Lesson::factory()->create([
        'school_class_id' => $this->class->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => $this->subject->id,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 3,
    ]);

    $readerGrid = gridFor($this->class);

    expect($readerGrid['can_write'])->toBeFalse()
        ->and($readerGrid['slots'])->toBe(range(1, 3))
        ->and($readerGrid['cells'][1][3]['edit_url'])->toBeNull();
});

it('orele vin din orarul PUBLICAT al clasei, iar „Acum" se calculează din ele', function () {
    gridUser(UserRole::AdministratorOperational);

    Lesson::factory()->create([
        'school_class_id' => $this->class->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => $this->subject->id,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 1,
    ]);

    Schedule::factory()->create([
        'type' => ScheduleType::Lessons,
        'is_public' => true,
        'school_class_id' => $this->class->id,
        'headers' => ['', 'Luni'],
        'rows' => [
            ['Lecția 1 08.15 – 09.00', 'Matematică'],
            ['Lecția 2 09.10 – 09.55', 'Matematică'],
            // Rând fără ore în etichetă — se ignoră fără eroare.
            ['Pauză mare', ''],
        ],
    ]);

    // Luni, 08:30 ÎN FUSUL ȘCOLII — orele orarului sunt locale de Chișinău, iar comparația se face
    // în același fus (SchoolCalendar::TIMEZONE); un setTestNow în UTC ar testa alt moment al zilei.
    Carbon::setTestNow(Carbon::parse('2026-03-02 08:30:00', SchoolCalendar::TIMEZONE));

    $grid = gridFor($this->class);

    expect($grid['times'][1]['label'])->toBe('08:15 – 09:00')
        ->and($grid['today'])->toBe(1)
        ->and($grid['current_slot'])->toBe(1)
        // În timpul unei lecții, „urmează" e zgomot — un singur marcaj.
        ->and($grid['next_slot'])->toBeNull();

    // Înainte de prima lecție: nimic „acum", lecția 1 „urmează".
    Carbon::setTestNow(Carbon::parse('2026-03-02 07:50:00', SchoolCalendar::TIMEZONE));

    $early = gridFor($this->class);

    expect($early['current_slot'])->toBeNull()
        ->and($early['next_slot'])->toBe(1);

    // Duminica nu e în grilă → niciun marcaj temporal.
    Carbon::setTestNow(Carbon::parse('2026-03-01 08:30:00', SchoolCalendar::TIMEZONE));

    expect(gridFor($this->class)['today'])->toBeNull();
});

it('cardurile claselor respectă perimetrul: profesorul își vede DOAR clasele lui', function () {
    // Două clase; profesorul predă doar la una.
    $foreign = SchoolClass::factory()->for($this->year)->create(['grade_level' => 9]);

    Lesson::factory()->create([
        'school_class_id' => $foreign->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => $this->subject->id,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 1,
    ]);

    $teacherUser = User::factory()->create();
    $teacherUser->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);
    actingAs($teacherUser);

    $cardIds = collect(Livewire::test(ListLessons::class)->instance()->classCards())->pluck('id');

    // Prins la verificarea live: profesorul vedea cardurile TUTUROR claselor (cu numărul de
    // lecții), dar la click primea perimetrul gol — un rând de uși care nu se deschid.
    expect($cardIds)->toContain($this->class->id)
        ->and($cardIds)->not->toContain($foreign->id);

    // Administrația vede în continuare tot.
    gridUser(UserRole::AdministratorOperational);

    expect(collect(Livewire::test(ListLessons::class)->instance()->classCards())->pluck('id'))
        ->toContain($this->class->id, $foreign->id);
});

it('orarul NEPUBLICAT nu furnizează ore — grila nu arată familiilor altceva decât s-a publicat', function () {
    gridUser(UserRole::AdministratorOperational);

    Schedule::factory()->create([
        'type' => ScheduleType::Lessons,
        'is_public' => false,
        'school_class_id' => $this->class->id,
        'headers' => ['', 'Luni'],
        'rows' => [['Lecția 1 08.15 – 09.00', 'Matematică']],
    ]);

    expect(gridFor($this->class)['times'])->toBe([]);
});

it('formularul de creare preia ziua și lecția din celula aleasă, dar refuză valori din afara intervalului', function () {
    gridUser(UserRole::AdministratorOperational);

    Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'zi' => '3', 'lectie' => '5'])
        ->test(CreateLesson::class)
        ->assertSchemaStateSet([
            // Selectul cu opțiuni-enum ține starea ca enum, nu ca int brut.
            'day_of_week' => Weekday::Wednesday,
            'lesson_number' => 5,
        ]);

    // Valori fabricate (zi=9, lectie=0) → ignorate, nu preluate orbește.
    Livewire::withQueryParams(['clasa' => (string) $this->class->id, 'zi' => '9', 'lectie' => '0'])
        ->test(CreateLesson::class)
        ->assertSchemaStateSet([
            'day_of_week' => null,
            'lesson_number' => null,
        ]);
});

it('previzualizarea unui orar publicabil randează exact tabelul văzut de familii', function () {
    gridUser(UserRole::AdministratorOperational);

    $schedule = Schedule::factory()->create([
        'type' => ScheduleType::Bells,
        'is_public' => true,
        'label' => 'Orarul sunetelor',
        'headers' => ['Lecția', 'Interval'],
        'rows' => [['Lecția 1', '08.15 – 09.00']],
    ]);

    // Acțiunea există și se montează pe rând (conținutul modalului se randează în ciclul următor
    // al Livewire, deci nu se poate afirma din același răspuns — se testează separat, direct).
    Livewire::withQueryParams(['tip' => ScheduleType::Bells->value])
        ->test(ListSchedules::class)
        ->mountTableAction('preview', $schedule)
        // TestAction cu înregistrarea: varianta pe nume simplu compară contextul fără recordKey
        // și pică fals pe acțiunile de rând.
        ->assertActionMounted(TestAction::make('preview')->table($schedule));

    // Conținutul: exact headers + rows, fără coloane interne.
    $html = view('filament.catalog.schedule-preview', ['schedule' => $schedule])->render();

    expect($html)->toContain('Lecția')
        ->and($html)->toContain('Interval')
        ->and($html)->toContain('08.15 – 09.00');

    // Tabelul gol nu randează un tabel fantomă, ci mesajul de gol.
    $empty = Schedule::factory()->create([
        'type' => ScheduleType::Bells,
        'is_public' => false,
        'headers' => [],
        'rows' => [],
    ]);

    expect(view('filament.catalog.schedule-preview', ['schedule' => $empty])->render())
        ->toContain(__('panel.forms.schedule.preview_empty'));
});
