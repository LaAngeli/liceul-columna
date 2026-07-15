<?php

/**
 * Foaia matricolă — navigator cu carduri (2026-07-16): clase → elevii clasei → foaia matricolă a
 * elevului ca DOCUMENT (trepte romane desc, disciplină × Sem. I / Sem. II / Media anuală,
 * calificative, media anuală a disciplinelor afișate). Perimetrul rămâne al resursei: profesorul
 * vede doar disciplinele lui (LegacyArchivesScopingTest rămâne sursa scoping-ului); arhiva
 * căutabilă e doar a administrației.
 */

use App\Enums\AcademicRecordPeriod;
use App\Enums\UserRole;
use App\Filament\Resources\AcademicRecords\Pages\ListAcademicRecords;
use App\Models\AcademicRecord;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);

    $this->classA = SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'grade_level' => 7, 'section' => 'A']);
    $this->classB = SchoolClass::factory()->for($this->year)->create(['name' => 'VIII', 'grade_level' => 8, 'section' => 'B']);

    $this->ana = Student::factory()->create(['last_name' => 'TR-Anghel', 'first_name' => 'Ana', 'register_number' => 5]);
    Enrollment::factory()->for($this->ana)->for($this->classA)->for($this->year)->create();

    $this->foreignStudent = Student::factory()->create(['last_name' => 'TR-Bostan', 'first_name' => 'Vlad']);
    Enrollment::factory()->for($this->foreignStudent)->for($this->classB)->for($this->year)->create();

    // Disciplinele: ordinea din documente (report_order) + una pe calificativ.
    $this->math = Subject::factory()->create(['name' => 'TR-Matematică', 'report_order' => 1]);
    $this->chem = Subject::factory()->create(['name' => 'TR-Chimie', 'report_order' => 2]);
    $this->sport = Subject::factory()->create(['name' => 'TR-Sport', 'report_order' => null]);

    // Foaia Anei: treapta a VII-a (matematică Sem I/II/anuală + chimie anuală + sport calificativ)
    // și treapta a VI-a (matematică anuală) — două secțiuni, desc.
    foreach ([
        ['subject_id' => $this->math->id, 'grade_level' => 7, 'period' => AcademicRecordPeriod::SemesterI->value, 'value' => 9.00],
        ['subject_id' => $this->math->id, 'grade_level' => 7, 'period' => AcademicRecordPeriod::SemesterII->value, 'value' => 9.50],
        ['subject_id' => $this->math->id, 'grade_level' => 7, 'period' => AcademicRecordPeriod::Annual->value, 'value' => 9.25],
        ['subject_id' => $this->chem->id, 'grade_level' => 7, 'period' => AcademicRecordPeriod::Annual->value, 'value' => 8.00],
        ['subject_id' => $this->sport->id, 'grade_level' => 7, 'period' => AcademicRecordPeriod::Annual->value, 'value' => null, 'calificativ' => 'FB'],
        ['subject_id' => $this->math->id, 'grade_level' => 6, 'period' => AcademicRecordPeriod::Annual->value, 'value' => 9.75],
    ] as $attributes) {
        AcademicRecord::factory()->create($attributes + ['student_id' => $this->ana->id, 'calificativ' => $attributes['calificativ'] ?? null]);
    }

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
});

it('administrația navighează clase → elevi → foaia matricolă', function () {
    actingAs($this->director);

    $component = Livewire::test(ListAcademicRecords::class);
    $page = $component->instance();

    // Aterizare: clasele anului curent.
    expect(collect($page->classCards())->pluck('id')->all())->toBe([$this->classA->id, $this->classB->id]);

    // Clasa deschisă → cardurile elevilor, cu rezumatul foii (trepte + înregistrări).
    $component->call('openClass', $this->classA->id);
    $cards = $component->instance()->studentCards();

    expect(collect($cards)->pluck('id')->all())->toBe([$this->ana->id])
        ->and($cards[0]['stats'][0])->toContain('VI–VII')
        ->and($cards[0]['stats'][1])->toContain('6');

    // Elevul deschis → foaia lui.
    $component->call('openStudent', $this->ana->id);
    expect($component->instance()->activeStudent()?->id)->toBe($this->ana->id);
});

it('foaia matricolă e documentul complet: trepte desc, perioade, calificative, media afișată', function () {
    actingAs($this->director);

    $component = Livewire::test(ListAcademicRecords::class)->call('openStudent', $this->ana->id);
    $levels = $component->instance()->transcriptLevels();

    // Treptele în ordine inversă (a VII-a înaintea celei de-a VI-a), cu roman + ciclu.
    expect(collect($levels)->pluck('grade_level')->all())->toBe([7, 6])
        ->and($levels[0]['roman'])->toBe('VII')
        ->and($levels[0]['cycle'])->toBe('Gimnaziu');

    // Rândurile în ordinea documentelor (report_order; fără ordine → la coadă).
    $rows = collect($levels[0]['rows']);
    expect($rows->pluck('subject')->all())->toBe(['TR-Matematică', 'TR-Chimie', 'TR-Sport']);

    // Perioadele complete pe rând + calificativul unde nu există notă numerică.
    expect($rows[0]['sem1'])->toBe('9,00')
        ->and($rows[0]['sem2'])->toBe('9,50')
        ->and($rows[0]['annual'])->toBe('9,25')
        ->and($rows[1]['annual'])->toBe('8,00')
        ->and($rows[2]['annual'])->toBe('FB');

    // Media anuală a disciplinelor afișate: (9,25 + 8,00) / 2 = 8,62 (trunchiat, calificativul nu intră).
    expect($levels[0]['average'])->toBe('8,62')
        ->and($levels[1]['average'])->toBe('9,75');

    // Rezumatul de sub nume: clasa curentă + treptele + numărul de înregistrări.
    $summary = implode(' | ', $component->instance()->transcriptSummary());
    expect($summary)->toContain('VII A')->toContain('VI–VII');
});

it('profesorul vede în foaie DOAR disciplinele lui, iar elevii străini nu se deschid', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $this->classA->id, 'subject_id' => $this->chem->id,
    ]);

    actingAs($user);

    $component = Livewire::test(ListAcademicRecords::class)->call('openStudent', $this->ana->id);
    $levels = $component->instance()->transcriptLevels();

    // Doar chimia (disciplina predată); matematica și sportul nu apar — nici treapta a VI-a.
    expect(collect($levels)->pluck('grade_level')->all())->toBe([7])
        ->and(collect($levels[0]['rows'])->pluck('subject')->all())->toBe(['TR-Chimie']);

    // Elevul altei clase nu se deschide (perimetrul StudentResource).
    $component->call('openStudent', $this->foreignStudent->id);
    expect($component->instance()->activeStudent()?->id)->toBe($this->ana->id);

    $foreign = Livewire::withQueryParams(['elev' => (string) $this->foreignStudent->id])
        ->test(ListAcademicRecords::class);
    expect($foreign->instance()->activeStudent())->toBeNull();
});

it('arhiva căutabilă e doar a administrației și găsește elevii fără clasă curentă', function () {
    $departed = Student::factory()->create(['last_name' => 'TR-Zugrav', 'first_name' => 'Ion']);
    AcademicRecord::factory()->create([
        'student_id' => $departed->id, 'subject_id' => $this->math->id,
        'grade_level' => 9, 'period' => AcademicRecordPeriod::Annual->value, 'value' => 7.50,
    ]);

    actingAs($this->director);

    $component = Livewire::withQueryParams(['arhiva' => '1'])->test(ListAcademicRecords::class);

    expect($component->instance()->isArchiveMode())->toBeTrue();

    $component->set('archiveSearch', 'TR-Zugrav');
    $cards = $component->instance()->archiveStudentCards();

    expect(collect($cards)->pluck('id')->all())->toBe([$departed->id])
        ->and($cards[0]['stats'][0])->toContain('IX');

    // Profesorul nu are arhiva — parametrul din URL se ignoră.
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    expect(Livewire::withQueryParams(['arhiva' => '1'])->test(ListAcademicRecords::class)->instance()->isArchiveMode())
        ->toBeFalse();
});

it('o clasă din afara perimetrului profesorului nu se deschide', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $this->classA->id, 'subject_id' => $this->chem->id,
    ]);

    actingAs($user);

    $component = Livewire::withQueryParams(['clasa' => (string) $this->classB->id])
        ->test(ListAcademicRecords::class);

    expect($component->instance()->activeClass())->toBeNull()
        ->and(collect($component->instance()->classCards())->pluck('id')->all())->toBe([$this->classA->id]);
});
