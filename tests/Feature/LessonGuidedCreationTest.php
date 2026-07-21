<?php

/**
 * Fluxul GHIDAT de creare a lecției (standardizarea 2026-07-21): anul școlar nu e o alegere —
 * selectul a DISPĂRUT (derivat din clasă prin observer); clasele doar din anii DESCHIȘI;
 * disciplinele grupate cu alocările didactice în frunte; profesorul COMPLETAT AUTOMAT din alocarea
 * (clasă, disciplină) când e una singură (la grupe rămâne alegerea omului); numărul lecției doar
 * dintre sloturile LIBERE; gărzile de model (interval 1–8, slot unic, an închis) prind orice cale.
 */

use App\Enums\UserRole;
use App\Enums\Weekday;
use App\Filament\Resources\Lessons\Pages\CreateLesson;
use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::AdministratorOperational->value);
    actingAs($this->admin);

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 7]);
});

it('selectul de an școlar a DISPĂRUT — anul e derivat din clasă, afișat doar informativ', function () {
    Livewire::test(CreateLesson::class)
        ->assertFormFieldDoesNotExist('academic_year_id');

    // Invariantul rămâne pe observer: lecția creată poartă anul clasei, nu ce s-a trimis.
    $subject = Subject::factory()->create(['min_grade' => 5, 'max_grade' => 9]);

    Livewire::test(CreateLesson::class)
        ->fillForm([
            'school_class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'day_of_week' => Weekday::Monday->value,
            'lesson_number' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Lesson::query()->firstOrFail()->academic_year_id)->toBe($this->year->id);
});

it('clasele se aleg doar din anii DESCHIȘI — clasa unui an închis nu apare la creare', function () {
    // Clasa se creează cât anul e deschis (garda Claselor ar bloca-o altfel), apoi anul se închide.
    $closedYear = AcademicYear::factory()->create();
    $closedClass = SchoolClass::factory()->for($closedYear)->create(['grade_level' => 5]);
    $closedYear->update(['closed_at' => now()]);

    Livewire::test(CreateLesson::class)
        ->assertFormFieldExists('school_class_id', function (Select $field) use ($closedClass): bool {
            // Aplatizare cu UNIUNE (+), nu flatMap: collapse/array_merge renumerotează cheile
            // numerice — id-urile de clasă ar deveni indecși 0,1,2.
            $flat = [];

            foreach ($field->getOptions() as $key => $value) {
                is_array($value) ? $flat += $value : $flat[$key] = $value;
            }

            $ids = array_keys($flat);

            return in_array($this->class->id, $ids, true)
                && ! in_array($closedClass->id, $ids, true);
        });
});

it('profesorul se COMPLETEAZĂ AUTOMAT din alocarea didactică unică a perechii (clasă, disciplină)', function () {
    $subject = Subject::factory()->create(['min_grade' => 5, 'max_grade' => 9]);
    $teacher = Teacher::factory()->create();

    TeachingAssignment::factory()->create([
        'school_class_id' => $this->class->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
    ]);

    $state = Livewire::test(CreateLesson::class)
        ->fillForm(['school_class_id' => $this->class->id])
        ->fillForm(['subject_id' => $subject->id])
        ->instance()->form->getRawState();

    // Comparație pe valoare numerică: SQLite plimbă id-urile ca string prin starea Livewire.
    expect((int) $state['teacher_id'])->toBe($teacher->id);
});

it('la predarea pe GRUPE (mai mulți profesori alocați) alegerea rămâne a omului — nu se auto-completează', function () {
    $subject = Subject::factory()->create(['min_grade' => 5, 'max_grade' => 9]);
    [$teacherA, $teacherB] = Teacher::factory()->count(2)->create();

    TeachingAssignment::factory()->create([
        'school_class_id' => $this->class->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacherA->id,
        'english_group' => 1,
    ]);
    TeachingAssignment::factory()->create([
        'school_class_id' => $this->class->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacherB->id,
        'english_group' => 2,
    ]);

    $state = Livewire::test(CreateLesson::class)
        ->fillForm(['school_class_id' => $this->class->id])
        ->fillForm(['subject_id' => $subject->id])
        ->instance()->form->getRawState();

    expect($state['teacher_id'])->toBeNull();
});

it('disciplinele cu alocare didactică apar GRUPATE în fruntea listei', function () {
    $assigned = Subject::factory()->create(['name' => 'Matematică', 'min_grade' => 5, 'max_grade' => 12]);
    $other = Subject::factory()->create(['name' => 'Chimie', 'min_grade' => 7, 'max_grade' => 12]);

    TeachingAssignment::factory()->create([
        'school_class_id' => $this->class->id,
        'subject_id' => $assigned->id,
        'teacher_id' => Teacher::factory()->create()->id,
    ]);

    Livewire::test(CreateLesson::class)
        ->fillForm(['school_class_id' => $this->class->id])
        ->assertFormFieldExists('subject_id', function (Select $field) use ($assigned, $other): bool {
            $options = $field->getOptions();
            $groups = array_keys($options);

            // Primul grup = alocările didactice; disciplina alocată e în el, cealaltă nu.
            $firstGroup = is_array($options[$groups[0]] ?? null) ? $options[$groups[0]] : [];

            return array_key_exists($assigned->id, $firstGroup)
                && ! array_key_exists($other->id, $firstGroup);
        });
});

it('sloturile OCUPATE nu se pot alege: lipsesc din opțiuni, iar POST-ul forjat e respins', function () {
    $subject = Subject::factory()->create(['min_grade' => 5, 'max_grade' => 9]);

    Lesson::factory()->create([
        'school_class_id' => $this->class->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => $subject->id,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 3,
    ]);

    $component = Livewire::test(CreateLesson::class)
        ->fillForm([
            'school_class_id' => $this->class->id,
            'day_of_week' => Weekday::Monday->value,
        ]);

    $component->assertFormFieldExists('lesson_number', function (Select $field): bool {
        $options = array_keys($field->getOptions());

        return ! in_array(3, $options, true) && in_array(1, $options, true);
    });

    // POST forjat direct pe slotul ocupat → mesaj, nu eroare de constrângere.
    $component
        ->fillForm(['subject_id' => $subject->id, 'lesson_number' => 3])
        ->call('create')
        ->assertHasFormErrors(['lesson_number']);
});

it('schimbarea zilei păstrează numărul doar dacă noul context îl arată liber', function () {
    $subject = Subject::factory()->create(['min_grade' => 5, 'max_grade' => 9]);

    Lesson::factory()->create([
        'school_class_id' => $this->class->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => $subject->id,
        'day_of_week' => Weekday::Tuesday,
        'lesson_number' => 2,
    ]);

    $state = Livewire::test(CreateLesson::class)
        ->fillForm([
            'school_class_id' => $this->class->id,
            'day_of_week' => Weekday::Monday->value,
            'lesson_number' => 2,
        ])
        // Marțea are deja lecția 2 → numărul ales nu mai e valabil și se golește.
        ->fillForm(['day_of_week' => Weekday::Tuesday->value])
        ->instance()->form->getRawState();

    expect($state['lesson_number'])->toBeNull();
});

it('POST-ul forjat cu disciplină din afara treptei clasei e respins pe server', function () {
    $primar = Subject::factory()->create(['name' => 'Abecedar', 'min_grade' => 1, 'max_grade' => 4]);

    Livewire::test(CreateLesson::class)
        ->fillForm([
            'school_class_id' => $this->class->id,
            'subject_id' => $primar->id,
            'day_of_week' => Weekday::Monday->value,
            'lesson_number' => 1,
        ])
        ->call('create')
        ->assertHasFormErrors(['subject_id']);

    expect(Lesson::query()->count())->toBe(0);
});

it('gărzile de MODEL prind orice cale: număr în afara plajei, slot dublat, an închis', function () {
    $subject = Subject::factory()->create();

    // Numărul lecției peste plaja zilei (1–8).
    expect(fn () => Lesson::factory()->create([
        'school_class_id' => $this->class->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => $subject->id,
        'lesson_number' => 9,
    ]))->toThrow(ValidationException::class);

    // Slot dublat, direct prin model (ocolind formularul).
    $slot = [
        'school_class_id' => $this->class->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => $subject->id,
        'day_of_week' => Weekday::Friday,
        'lesson_number' => 5,
    ];
    Lesson::factory()->create($slot);

    expect(fn () => Lesson::factory()->create($slot))->toThrow(ValidationException::class);

    // Orarul unui an ÎNCHIS e structură arhivată: nici scriere, nici ștergere.
    $closedYear = AcademicYear::factory()->create();
    $closedClass = SchoolClass::factory()->for($closedYear)->create(['grade_level' => 5]);
    $frozen = Lesson::factory()->create([
        'school_class_id' => $closedClass->id,
        'academic_year_id' => $closedYear->id,
        'subject_id' => $subject->id,
        'day_of_week' => Weekday::Monday,
        'lesson_number' => 1,
    ]);
    $closedYear->update(['closed_at' => now()]);

    expect(fn () => $frozen->update(['room' => '101']))->toThrow(ValidationException::class)
        ->and(fn () => $frozen->delete())->toThrow(ValidationException::class);
});

it('mesajele și etichetele există în toate cele trei limbi', function () {
    foreach (['ro', 'ru', 'en'] as $locale) {
        app()->setLocale($locale);

        foreach ([
            'panel.forms.lesson.section_context',
            'panel.forms.lesson.section_schedule',
            'panel.forms.lesson.section_details',
            'panel.forms.lesson.year_line',
            'panel.forms.lesson.year_pending',
            'panel.forms.lesson.weekly_pattern_info',
            'panel.forms.lesson.subjects_assigned',
            'panel.forms.lesson.subjects_other',
            'panel.forms.lesson.teacher_assigned',
            'panel.forms.lesson.teacher_groups',
            'panel.forms.lesson.teacher_unassigned',
            'panel.forms.lesson.slots_full',
            'panel.validation.lesson.number_out_of_range',
            'panel.validation.lesson.class_year_closed',
            'panel.validation.lesson.subject_outside_grade',
        ] as $key) {
            expect(__($key))->not->toBe($key, "Cheia {$key} lipsește pe {$locale}");
        }
    }

    app()->setLocale('ro');
});
