<?php

/**
 * FOAIA MATRICOLĂ e UN SINGUR document, citit din trei locuri: panoul școlii, cabinetul familiei
 * și PDF-ul oficial (care se generează din aceeași metodă ca al cabinetului).
 *
 * Testele de aici apără promisiunea că cele trei arată LA FEL. Divergențele au fost reale, găsite
 * pe 05.08.2026 abia după ce elevii demo au căpătat foi matricole — până atunci secțiunea era
 * goală pe conturile de demonstrație, deci nimic nu se putea compara:
 *  • ordinea disciplinelor: panoul pe `report_order`+nume, cabinetul în ordinea id-urilor din DB;
 *  • formatul mediei: panoul „9,30", cabinetul (și PDF-ul oficial) „9.3" — separator englezesc și
 *    o zecimală pierdută, deși mediile se calculează la sutimi (§2.4);
 *  • eticheta treptei: panoul „Clasa a XI-a · Liceu", cabinetul „Clasa 11".
 */

use App\Enums\AcademicRecordPeriod;
use App\Enums\UserRole;
use App\Filament\Resources\AcademicRecords\Pages\ListAcademicRecords;
use App\Http\Controllers\CabinetController;
use App\Models\AcademicRecord;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\ActiveRole;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 11]);

    $this->student = Student::factory()->create(['last_name' => 'Vulpe', 'first_name' => 'Elev']);
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create([
        'enrolled_on' => Carbon::today()->subMonths(3),
        'left_on' => null,
    ]);

    // Trei discipline cu `report_order` DELIBERAT invers față de alfabet: dacă vreo parte sortează
    // pe nume în loc de ordinea oficială, testul o prinde.
    $this->fizica = Subject::factory()->create(['name' => 'Fizică', 'report_order' => 1]);
    $this->biologie = Subject::factory()->create(['name' => 'Biologie', 'report_order' => 2]);
    $this->algebra = Subject::factory()->create(['name' => 'Algebră', 'report_order' => 3]);

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
    $this->director = $this->director->fresh();
});

/** Cele trei perioade ale unei (disciplină × treaptă), cu valorile date. */
function transcriptRow(object $ctx, Subject $subject, float $sem1, float $sem2, float $annual): void
{
    foreach ([
        [AcademicRecordPeriod::SemesterI, $sem1],
        [AcademicRecordPeriod::SemesterII, $sem2],
        [AcademicRecordPeriod::Annual, $annual],
    ] as [$period, $value]) {
        AcademicRecord::factory()->create([
            'student_id' => $ctx->student->id,
            'subject_id' => $subject->id,
            'grade_level' => 11,
            'period' => $period,
            'value' => $value,
            'calificativ' => null,
        ]);
    }
}

/** Foaia așa cum o vede CABINETUL (aceeași metodă alimentează PDF-ul oficial). */
function cabinetTranscript(Student $student): array
{
    $student->load('academicRecords.subject');

    $method = new ReflectionMethod(CabinetController::class, 'transcript');
    $method->setAccessible(true);

    return $method->invoke(app(CabinetController::class), $student);
}

it('cabinetul și panoul dau ACEEAȘI foaie: ordine, format și etichetă de treaptă', function () {
    transcriptRow($this, $this->fizica, 9.39, 9.21, 9.30);
    transcriptRow($this, $this->biologie, 8.00, 7.50, 7.75);
    transcriptRow($this, $this->algebra, 10.00, 9.00, 9.50);

    actingAs($this->director);

    $panou = Livewire::test(ListAcademicRecords::class)
        ->set('classParam', (string) $this->class->id)
        ->set('studentParam', (string) $this->student->id)
        ->instance()
        ->transcriptLevels();

    $cabinet = cabinetTranscript($this->student->fresh());

    expect($panou)->toHaveCount(1)->and($cabinet)->toHaveCount(1);

    // Eticheta treptei: cifră romană + ciclu, la fel în ambele.
    expect($cabinet[0]['roman'])->toBe($panou[0]['roman'])->toBe('XI')
        ->and($cabinet[0]['cycle'])->toBe($panou[0]['cycle'])
        ->and($cabinet[0]['average'])->toBe($panou[0]['average']);

    // ORDINEA oficială (report_order), nu alfabetul: Fizică → Biologie → Algebră.
    $ordinePanou = array_column($panou[0]['rows'], 'subject');
    $ordineCabinet = array_column($cabinet[0]['subjects'], 'subject');

    expect($ordinePanou)->toBe(['Fizică', 'Biologie', 'Algebră'])
        ->and($ordineCabinet)->toBe($ordinePanou);

    // FORMATUL mediei: sutimi, virgulă zecimală — identic în ambele.
    expect($cabinet[0]['subjects'][0]['annual'])->toBe($panou[0]['rows'][0]['annual'])->toBe('9,30')
        ->and($cabinet[0]['subjects'][0]['sem1'])->toBe('9,39');
});

it('media pe treaptă e a mediilor ANUALE, trunchiată la sutimi', function () {
    transcriptRow($this, $this->fizica, 9.39, 9.21, 9.30);
    transcriptRow($this, $this->biologie, 8.00, 7.50, 7.75);

    // (9,30 + 7,75) / 2 = 8,525 → trunchiat 8,52 (NU rotunjit la 8,53).
    expect(cabinetTranscript($this->student->fresh())[0]['average'])->toBe('8,52');
});

it('o treaptă doar pe calificative nu raportează medie numerică', function () {
    foreach ([AcademicRecordPeriod::SemesterI, AcademicRecordPeriod::SemesterII, AcademicRecordPeriod::Annual] as $period) {
        AcademicRecord::factory()->create([
            'student_id' => $this->student->id,
            'subject_id' => $this->fizica->id,
            'grade_level' => 11,
            'period' => $period,
            'value' => null,
            'calificativ' => 'FB',
        ]);
    }

    $cabinet = cabinetTranscript($this->student->fresh());

    expect($cabinet[0]['average'])->toBeNull()
        ->and($cabinet[0]['subjects'][0]['annual'])->toBe('FB');
});

it('lista de clase urmează CONTEXTUL activ, nu perimetrul unit', function () {
    // Un diriginte-și-profesor: o clasă unde predă, alta doar de dirigenție.
    $user = User::factory()->create();
    $user->assignRole(UserRole::Diriginte->value);
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);

    $predata = SchoolClass::factory()->for($this->year)->create(['grade_level' => 9]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => $predata->id,
        'subject_id' => $this->fizica->id,
    ]);

    $doarDirigentie = SchoolClass::factory()->for($this->year)->create([
        'grade_level' => 10,
        'homeroom_teacher_id' => $teacher->id,
    ]);

    actingAs($user->fresh());
    session()->put(ActiveRole::SESSION_KEY, UserRole::Profesor->value);

    $clase = collect(Livewire::test(ListAcademicRecords::class)->instance()->classCards())->pluck('id');

    // În capacitatea de PROFESOR, clasa de dirigenție-fără-predare NU se oferă: foile ei nu sunt
    // vizibile în acest context, iar un card care duce la „fără înregistrări" e o promisiune falsă.
    expect($clase)->toContain($predata->id)
        ->and($clase)->not->toContain($doarDirigentie->id);
});
