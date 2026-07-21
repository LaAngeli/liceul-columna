<?php

/**
 * Integritatea orarului structurat (LOT 6 al restructurării „Configurare").
 *
 * Orarul structurat e numitorul riscului de amânare (spec §2.1): „câte lecții au fost programate la
 * disciplina X". Dacă slotul e legat de fișa altui ciclu, sau dispare fiindcă anul lui nu mai
 * corespunde clasei, calculul nu dă eroare — dă un rezultat greșit, în tăcere. Testele de aici
 * ancorează exact căile prin care se producea asta.
 */

use App\Console\Commands\ImportLessonsFromSchedules;
use App\Enums\ScheduleType;
use App\Enums\UserRole;
use App\Enums\Weekday;
use App\Filament\Resources\Lessons\Pages\CreateLesson;
use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Filament\Forms\Components\Select;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
});

/** Un orar publicat, legat de o clasă, cu un singur rând-lecție și celulele date. */
function publishedTimetable(SchoolClass $class, array $cells): Schedule
{
    return Schedule::factory()->create([
        'type' => ScheduleType::Lessons,
        'is_public' => true,
        'school_class_id' => $class->id,
        'headers' => ['Ora', 'Luni', 'Marți'],
        'rows' => [array_merge(['Lecția 1'], $cells)],
    ]);
}

it('leagă disciplina de fișa TREPTEI clasei, nu de omonima altui ciclu', function () {
    // Aceeași denumire, două fișe — situația reală pentru zece discipline din nomenclator.
    $primar = Subject::factory()->create(['name' => 'Matematică', 'min_grade' => 1, 'max_grade' => 4]);
    $gimnaziu = Subject::factory()->create(['name' => 'Matematică', 'min_grade' => 5, 'max_grade' => 12]);

    $clasaMare = SchoolClass::factory()->for($this->year)->create(['grade_level' => 8]);
    publishedTimetable($clasaMare, ['Matematică , Damian Iu. (s. 20)']);

    $this->artisan(ImportLessonsFromSchedules::class, ['--force' => true])->assertSuccessful();

    // Fișa gimnaziului, nu cea a ciclului primar: harta globală nume→id păstra una singură și
    // lega tăcut lecțiile de a VIII-a de disciplina claselor 1-4 (219 din 507 lecții în producție).
    expect(Lesson::query()->where('school_class_id', $clasaMare->id)->value('subject_id'))
        ->toBe($gimnaziu->id)
        ->not->toBe($primar->id);
});

it('nu inventează o disciplină când nimic nu se potrivește pe treaptă — sare celula și o raportează', function () {
    // „Chimie" există DOAR pentru treptele mari; clasa e a treia.
    Subject::factory()->create(['name' => 'Chimie', 'min_grade' => 7, 'max_grade' => 12]);

    $clasaMica = SchoolClass::factory()->for($this->year)->create(['grade_level' => 3]);
    publishedTimetable($clasaMica, ['Chimie , Cociurca N.']);

    $this->artisan(ImportLessonsFromSchedules::class, ['--force' => true])
        ->expectsOutputToContain('Celule nerezolvate')
        ->assertSuccessful();

    // Un fallback pe nume „ca să nu se piardă slotul" ar reintroduce exact bug-ul tăcut.
    expect(Lesson::query()->where('school_class_id', $clasaMica->id)->count())->toBe(0);
});

it('recunoaște denumirea colocvială din orar prin alias, dar nu peste una reală', function () {
    $romana = Subject::factory()->create(['name' => 'Limba și literatura română', 'min_grade' => 5, 'max_grade' => 12]);
    $straina2 = Subject::factory()->create(['name' => 'Limba străină 2', 'min_grade' => 5, 'max_grade' => 12]);

    $class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 9]);
    publishedTimetable($class, ['Limba română , Russu T.', 'Limba franceză , Golban O. (s. 28)']);

    $this->artisan(ImportLessonsFromSchedules::class, ['--force' => true])->assertSuccessful();

    $byDay = Lesson::query()->where('school_class_id', $class->id)->pluck('subject_id', 'day_of_week');

    expect($byDay[Weekday::Monday->value])->toBe($romana->id)
        // Franceza și germana nu sunt fișe: sunt grupele disciplinei „Limba străină 2".
        ->and($byDay[Weekday::Tuesday->value])->toBe($straina2->id);
});

it('două grupe cu limbi diferite sunt ACEEAȘI disciplină, nu un slot mixt', function () {
    $straina2 = Subject::factory()->create(['name' => 'Limba străină 2', 'min_grade' => 5, 'max_grade' => 12]);
    Subject::factory()->create(['name' => 'Informatică', 'min_grade' => 5, 'max_grade' => 12]);

    $class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 10]);
    publishedTimetable($class, [
        'Limba franceză , Golban O. (s. 28) Limba germană , Arhip S.',
        // Aici sunt însă DOUĂ discipline reale — nereprezentabil într-un singur slot.
        'Limba străină 2 , gr.1 Informatică , gr.2, Iurco M.',
    ]);

    $this->artisan(ImportLessonsFromSchedules::class, ['--force' => true])->assertSuccessful();

    $lessons = Lesson::query()->where('school_class_id', $class->id)->get();

    expect($lessons)->toHaveCount(1)
        ->and($lessons->first()->subject_id)->toBe($straina2->id)
        ->and($lessons->first()->day_of_week)->toBe(Weekday::Monday);
});

it('o disciplină al cărei nume e cuprins în alta nu strică detecția grupelor', function () {
    // „Fizică" ⊂ „Educație fizică": căutarea naivă respingea slotul pe grupe al educației fizice,
    // găsind „Fizică" în a doua apariție a ACELEIAȘI discipline.
    $educatieFizica = Subject::factory()->create(['name' => 'Educație fizică', 'min_grade' => 5, 'max_grade' => 12]);
    Subject::factory()->create(['name' => 'Fizică', 'min_grade' => 6, 'max_grade' => 12]);

    $class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 7]);
    publishedTimetable($class, [
        'Educație fizică , gr.1, Dumitraș V. (s. 1) Educație fizică , gr.2, Dumitraș V.',
        // Invers: pornit cu disciplina scurtă, continuat cu cea lungă — chiar e slot mixt.
        'Fizică , gr.1 Educație fizică , gr.2, Dumitraș V.',
    ]);

    $this->artisan(ImportLessonsFromSchedules::class, ['--force' => true])->assertSuccessful();

    $lessons = Lesson::query()->where('school_class_id', $class->id)->get();

    expect($lessons)->toHaveCount(1)
        ->and($lessons->first()->subject_id)->toBe($educatieFizica->id);
});

it('majusculele din orar nu pierd disciplina', function () {
    $mate = Subject::factory()->create(['name' => 'În împărăția lui Mate', 'min_grade' => 1, 'max_grade' => 4]);

    $class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 2]);
    publishedTimetable($class, ['ÎN ÎMPĂRĂȚIA LUI MATE']);

    $this->artisan(ImportLessonsFromSchedules::class, ['--force' => true])->assertSuccessful();

    expect(Lesson::query()->where('school_class_id', $class->id)->value('subject_id'))->toBe($mate->id);
});

it('anul lecției se ia din clasă, oricine ar încerca să-l scrie altfel', function () {
    $altAn = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 6]);
    $subject = Subject::factory()->create();

    $lesson = Lesson::factory()->create([
        'school_class_id' => $class->id,
        // Anul altei ani-școlar: slotul ar dispărea din calculele pe anul clasei, fără semnal.
        'academic_year_id' => $altAn->id,
        'subject_id' => $subject->id,
    ]);

    expect($lesson->refresh()->academic_year_id)->toBe($this->year->id);
});

it('slotul șters nu mai blochează pentru totdeauna poziția din orar', function () {
    $class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 6]);
    $subject = Subject::factory()->create();

    $slot = [
        'school_class_id' => $class->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => $subject->id,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 3,
    ];

    Lesson::factory()->create($slot)->delete();

    // Cu soft-delete, rândul rămânea în tabel, indexul unic (care NU cuprinde `deleted_at`) îl
    // vedea în continuare, iar recrearea cădea în eroare de constrângere — fără cale de ieșire
    // din interfață, care n-a avut niciodată acțiune de restaurare.
    $recreated = Lesson::factory()->create($slot);

    expect($recreated->exists)->toBeTrue()
        ->and(Lesson::query()->where('school_class_id', $class->id)->count())->toBe(1);
});

it('formularul oferă doar disciplinele treptei clasei, iar suprapunerea de slot e mesaj, nu eroare', function () {
    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);
    actingAs($ao);

    $primar = Subject::factory()->create(['name' => 'Matematică', 'min_grade' => 1, 'max_grade' => 4]);
    $gimnaziu = Subject::factory()->create(['name' => 'Matematică', 'min_grade' => 5, 'max_grade' => 12]);

    $class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 8]);

    Lesson::factory()->create([
        'school_class_id' => $class->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => $gimnaziu->id,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 2,
    ]);

    $component = Livewire\Livewire::test(CreateLesson::class)
        ->fillForm([
            'school_class_id' => $class->id,
            'subject_id' => $gimnaziu->id,
            'day_of_week' => Weekday::Monday->value,
            'lesson_number' => 2,
        ])
        ->call('create');

    $component->assertHasFormErrors(['lesson_number']);

    // Doar fișa treptei apare în listă — altfel greșeala reparată la import se reintroduce manual.
    // Opțiunile pot fi GRUPATE (alocările didactice în frunte) — se aplatizează cu UNIUNE (+),
    // nu cu flatMap/array_merge, care ar renumerota cheile numerice (id-urile).
    $component->assertFormFieldExists('subject_id', function (Select $field) use ($gimnaziu, $primar): bool {
        $flat = [];

        foreach ($field->getOptions() as $key => $value) {
            is_array($value) ? $flat += $value : $flat[$key] = $value;
        }

        $options = array_keys($flat);

        // Și calea de CĂUTARE nu are voie să scoată la iveală o fișă din afara treptei: cu
        // `relationship()` pe un câmp searchable, Filament rezolvă opțiunile din relație și
        // filtrul rămâne fără efect — lista arăta tot nomenclatorul, deși `getOptions()`
        // întorcea corect (scăpare prinsă abia la verificarea live, nu de test).
        $searched = array_keys($field->getSearchResults('Matematică'));

        return in_array($gimnaziu->id, $options, true)
            && ! in_array($primar->id, $options, true)
            && ! in_array($primar->id, $searched, true);
    });
});
