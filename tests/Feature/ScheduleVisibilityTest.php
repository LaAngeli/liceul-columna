<?php

/**
 * ORARUL: citire vs scriere (LOT 5 al restructurării „Configurare").
 *
 * Cele 3 resurse de orar foloseau capabilitatea de SCRIERE și ca gate de CITIRE, deci secțiunea era
 * invizibilă în panou conducerii și personalului pedagogic — contrar §3.3. Efectul nu era doar de
 * comoditate: singura cale a directorului spre orarul unei clase trecea prin dosarul unui MINOR,
 * iar acel acces se jurnalizează (retenție 12 ani, L133) — o intrare de acces la PII pentru o dată
 * care nu e PII.
 */

use App\Enums\ScheduleType;
use App\Enums\UserRole;
use App\Filament\Resources\Holidays\HolidayResource;
use App\Filament\Resources\Lessons\LessonResource;
use App\Filament\Resources\Schedules\Pages\ListSchedules;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    $this->mine = SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'section' => 'A']);
    $this->theirs = SchoolClass::factory()->for($this->year)->create(['name' => 'IX', 'section' => 'B']);
});

it('conducerea și personalul pedagogic VĂD orarul structurat și zilele libere; tehnicul nu', function (string $role, bool $sees) {
    $user = User::factory()->create();
    $user->assignRole($role);
    actingAs($user);

    expect(LessonResource::canAccess())->toBe($sees)
        ->and(HolidayResource::canAccess())->toBe($sees);
})->with([
    UserRole::Director->value => [UserRole::Director->value, true],
    UserRole::PrimVicedirector->value => [UserRole::PrimVicedirector->value, true],
    UserRole::AdministratorOperational->value => [UserRole::AdministratorOperational->value, true],
    UserRole::Diriginte->value => [UserRole::Diriginte->value, true],
    UserRole::Profesor->value => [UserRole::Profesor->value, true],
    UserRole::AdministratorTehnic->value => [UserRole::AdministratorTehnic->value, false],
]);

it('profesorul vede DOAR orarul claselor lui; administrația îl vede pe tot', function () {
    $subject = Subject::factory()->create();

    $slotMine = Lesson::factory()->create([
        'school_class_id' => $this->mine->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => $subject->id,
    ]);
    $slotTheirs = Lesson::factory()->create([
        'school_class_id' => $this->theirs->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => $subject->id,
    ]);

    // Profesorul predă DOAR la clasa lui.
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => $this->mine->id,
        'subject_id' => $subject->id,
    ]);

    actingAs($teacherUser);
    $visible = LessonResource::getEloquentQuery()->pluck('id')->all();

    expect($visible)->toContain($slotMine->id)
        ->and($visible)->not->toContain($slotTheirs->id);

    // Administrația vede ambele.
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    actingAs($director);

    expect(LessonResource::getEloquentQuery()->pluck('id')->all())
        ->toContain($slotMine->id, $slotTheirs->id);
});

it('un cont pedagogic FĂRĂ fișă are perimetru gol, nu întreaga școală', function () {
    Lesson::factory()->create([
        'school_class_id' => $this->mine->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => Subject::factory()->create()->id,
    ]);

    $orphan = User::factory()->create();
    $orphan->assignRole(UserRole::Profesor->value);
    actingAs($orphan);

    expect(LessonResource::getEloquentQuery()->count())->toBe(0);
});

it('vederea nu aduce și dreptul de scriere: policies refuză editarea, restaurarea și ștergerea definitivă', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $schedule = Schedule::factory()->create();
    $lesson = Lesson::factory()->create([
        'school_class_id' => $this->mine->id,
        'academic_year_id' => $this->year->id,
        'subject_id' => Subject::factory()->create()->id,
    ]);

    foreach ([$schedule, $lesson] as $record) {
        expect($user->can('view', $record))->toBeTrue()
            ->and($user->can('update', $record))->toBeFalse()
            ->and($user->can('delete', $record))->toBeFalse()
            // Modele cu SoftDeletes: fără policy, astea cădeau pe „permis".
            ->and($user->can('restore', $record))->toBeFalse()
            ->and($user->can('forceDelete', $record))->toBeFalse();
    }
})->with([
    UserRole::Director->value,
    UserRole::PrimVicedirector->value,
    UserRole::Diriginte->value,
    UserRole::Profesor->value,
]);

it('administratorul operațional păstrează scrierea completă', function () {
    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);

    $schedule = Schedule::factory()->create();

    expect($ao->can('update', $schedule))->toBeTrue()
        ->and($ao->can('delete', $schedule))->toBeTrue()
        ->and($ao->can('forceDelete', $schedule))->toBeTrue();
});

// ─── UI-ul pe rol al secțiunii Orare (raportat 2026-08-01: profesorul putea comuta „Publicat") ──

function scheduleReader(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);

    return $user;
}

function scheduleManager(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::AdministratorOperational->value);

    return $user;
}

it('comutatorul de publicare e blocat PE SERVER pentru cititori; gestionarul comută', function () {
    $draft = Schedule::factory()->create(['is_public' => false]);

    // Coloanele editabile Filament NU trec prin policy la salvare — calea Livewire directă
    // (updateTableColumnState) trebuie să moară pe hidden/disabled, nu doar în interfață.
    actingAs(scheduleReader());
    Livewire::withQueryParams(['tip' => $draft->type->value])
        ->test(ListSchedules::class)
        ->call('updateTableColumnState', 'is_public', (string) $draft->getKey(), true);

    expect($draft->fresh()->is_public)->toBeFalse();

    actingAs(scheduleManager());
    Livewire::withQueryParams(['tip' => $draft->type->value])
        ->test(ListSchedules::class)
        ->call('updateTableColumnState', 'is_public', (string) $draft->getKey(), true);

    expect($draft->fresh()->is_public)->toBeTrue();
});

it('cititorul vede DOAR tabelele publicate, fără comutator/ordine/filtru; gestionarul vede tot', function () {
    $public = Schedule::factory()->create(['is_public' => true, 'label' => 'Clasa I C']);
    $draft = Schedule::factory()->create(['is_public' => false, 'label' => 'Ciornă nouă']);

    actingAs(scheduleReader());
    Livewire::withQueryParams(['tip' => ScheduleType::Lessons->value])
        ->test(ListSchedules::class)
        ->assertCanSeeTableRecords([$public])
        ->assertCanNotSeeTableRecords([$draft])
        ->assertTableColumnHidden('is_public')
        ->assertTableColumnHidden('position');

    actingAs(scheduleManager());
    Livewire::withQueryParams(['tip' => ScheduleType::Lessons->value])
        ->test(ListSchedules::class)
        ->assertCanSeeTableRecords([$public, $draft])
        ->assertTableColumnVisible('is_public')
        ->assertTableColumnVisible('position');
});

it('cardurile pe rol: gestionarul primește semnalul de lucru (roșu), cititorul starea publicată (gri)', function () {
    // Tipul „lecții" are DOAR o ciornă → gestionar: „fără date" (sarcină); cititor: nimic publicat.
    Schedule::factory()->create(['is_public' => false]);

    actingAs(scheduleManager());
    $managerCard = collect(Livewire::test(ListSchedules::class)->instance()->typeCards())
        ->firstWhere('id', ScheduleType::Lessons->value);

    expect($managerCard['badge'])->toBe(__('panel.config_nav.schedule_missing'))
        ->and($managerCard['badge_color'])->toBe('danger');

    actingAs(scheduleReader());
    $readerCard = collect(Livewire::test(ListSchedules::class)->instance()->typeCards())
        ->firstWhere('id', ScheduleType::Lessons->value);

    expect($readerCard['badge'])->toBe(__('panel.config_nav.schedule_none_public'))
        ->and($readerCard['badge_color'])->toBe('gray')
        ->and($readerCard['stats'])->toBe([trans_choice('panel.config_nav.schedule_public_tables', 0, ['count' => 0])]);
});

it('paginile de scriere rămân închise cititorilor (403), deschise gestionarului', function () {
    $schedule = Schedule::factory()->create();

    actingAs(scheduleReader());
    $this->get(ScheduleResource::getUrl('create'))->assertForbidden();
    $this->get(ScheduleResource::getUrl('edit', ['record' => $schedule]))->assertForbidden();

    actingAs(scheduleManager());
    $this->get(ScheduleResource::getUrl('create'))->assertOk();
    $this->get(ScheduleResource::getUrl('edit', ['record' => $schedule]))->assertOk();
});
