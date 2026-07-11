<?php

/**
 * Închiderea anului școlar (task #29, spec §2.4/§2.5): arhivarea mediilor semestriale în foaia
 * matricolă — până acum matricola creștea doar din importul legacy și din corigențe, deci anii
 * noi nu ajungeau în arhiva oficială, iar tabul Istoric „pierdea" anul închis.
 *
 * Formula ANUALEI vine din Regulamentul intern (spec §2.4, identică pe cicluri): media aritmetică
 * a celor două medii semestriale, sutimi FĂRĂ rotunjire. Corigența CU NOTĂ = rezultatul oficial
 * anual (§2.5) — arhivarea nu o rescrie cu media picată.
 */

use App\Actions\ArchiveYearToTranscript;
use App\Enums\AcademicRecordPeriod;
use App\Enums\CorigentaSeason;
use App\Enums\UserRole;
use App\Filament\Resources\AcademicYears\Pages\ListAcademicYears;
use App\Models\AcademicRecord;
use App\Models\AcademicYear;
use App\Models\CorigentaExam;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermAverage;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create(['name' => '2097–2098']);
    $this->sem1 = Term::factory()->for($this->year)->create(['number' => 1, 'starts_on' => '2097-09-01', 'ends_on' => '2097-12-31']);
    $this->sem2 = Term::factory()->for($this->year)->create(['number' => 2, 'starts_on' => '2098-01-05', 'ends_on' => '2098-06-30']);
    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 7]);
    $this->subject = Subject::factory()->create();
    $this->student = Student::factory()->create();
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create();
});

/** Media semestrială a elevului de bază, pe semestrul dat. */
function averageIn(Term $term, float $value, $test): void
{
    TermAverage::factory()->create([
        'student_id' => $test->student->id, 'subject_id' => $test->subject->id,
        'school_class_id' => $test->class->id, 'term_id' => $term->id, 'value' => $value,
    ]);
}

it('arhivează Sem I, Sem II și ANUALA (media aritmetică trunchiată, spec §2.4)', function () {
    averageIn($this->sem1, 8.50, $this);
    averageIn($this->sem2, 7.24, $this); // (8.50+7.24)/2 = 7.87 exact... folosim 7.25 → 7.875 → trunchiat 7.87

    TermAverage::query()->where('term_id', $this->sem2->id)->update(['value' => 7.25]);

    $result = app(ArchiveYearToTranscript::class)->run($this->year);

    $rows = AcademicRecord::query()
        ->where('student_id', $this->student->id)
        ->where('subject_id', $this->subject->id)
        ->where('grade_level', 7)
        ->get()
        ->keyBy(fn (AcademicRecord $r) => $r->period->value);

    expect($result['records'])->toBe(3)
        ->and($result['students'])->toBe(1)
        ->and((float) $rows[AcademicRecordPeriod::SemesterI->value]->value)->toBe(8.50)
        ->and((float) $rows[AcademicRecordPeriod::SemesterII->value]->value)->toBe(7.25)
        // (8.50 + 7.25) / 2 = 7.875 → sutimi FĂRĂ rotunjire = 7.87.
        ->and((float) $rows[AcademicRecordPeriod::Annual->value]->value)->toBe(7.87);
});

it('corigența CU NOTĂ e rezultatul oficial anual — arhivarea nu o rescrie cu media picată', function () {
    averageIn($this->sem1, 4.00, $this);
    averageIn($this->sem2, 4.50, $this); // media ar fi 4.25 — dar corigența a fost promovată cu 6.

    CorigentaExam::create([
        'student_id' => $this->student->id, 'subject_id' => $this->subject->id,
        'term_id' => $this->sem2->id, 'season' => CorigentaSeason::Vara, 'mark' => 6.00,
    ]);

    app(ArchiveYearToTranscript::class)->run($this->year);

    $annual = AcademicRecord::query()
        ->where('student_id', $this->student->id)
        ->where('subject_id', $this->subject->id)
        ->where('period', AcademicRecordPeriod::Annual)
        ->firstOrFail();

    expect((float) $annual->value)->toBe(6.00);
});

it('cu un singur semestru, anuala LIPSEȘTE (situație nedefinitivată), semestrul se arhivează', function () {
    averageIn($this->sem1, 9.00, $this);

    $result = app(ArchiveYearToTranscript::class)->run($this->year);

    expect($result['records'])->toBe(1)
        ->and(AcademicRecord::query()->where('period', AcademicRecordPeriod::Annual)->count())->toBe(0)
        ->and(AcademicRecord::query()->where('period', AcademicRecordPeriod::SemesterI)->count())->toBe(1);
});

it('e idempotentă: re-rularea nu produce duplicate, ci reîmprospătează', function () {
    averageIn($this->sem1, 8.00, $this);
    averageIn($this->sem2, 9.00, $this);

    app(ArchiveYearToTranscript::class)->run($this->year);
    app(ArchiveYearToTranscript::class)->run($this->year);

    expect(AcademicRecord::query()->count())->toBe(3);

    // O corecție ulterioară + re-rulare → arhiva reflectă noua medie.
    TermAverage::query()->where('term_id', $this->sem2->id)->update(['value' => 7.00]);
    app(ArchiveYearToTranscript::class)->run($this->year);

    expect((float) AcademicRecord::query()->where('period', AcademicRecordPeriod::Annual)->value('value'))->toBe(7.50);
});

it('elevul fără înmatriculare în anul arhivat e sărit și numărat', function () {
    $orphan = Student::factory()->create(); // fără enrollment
    TermAverage::factory()->create([
        'student_id' => $orphan->id, 'subject_id' => $this->subject->id,
        'school_class_id' => $this->class->id, 'term_id' => $this->sem1->id, 'value' => 8.00,
    ]);

    $result = app(ArchiveYearToTranscript::class)->run($this->year);

    expect($result['skipped'])->toBe(1)
        ->and(AcademicRecord::query()->where('student_id', $orphan->id)->count())->toBe(0);
});

it('comanda app:archive-year funcționează cu numele anului', function () {
    averageIn($this->sem1, 8.00, $this);
    averageIn($this->sem2, 9.00, $this);

    artisan('app:archive-year', ['year' => '2097–2098'])->assertSuccessful();

    expect(AcademicRecord::query()->count())->toBe(3);
});

it('acțiunea „Arhivează în matricolă" din panou rulează pentru configuratori', function () {
    averageIn($this->sem1, 8.00, $this);
    averageIn($this->sem2, 9.00, $this);

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    actingAs($director);

    Livewire::test(ListAcademicYears::class)
        ->callTableAction('archiveYear', $this->year)
        ->assertHasNoTableActionErrors();

    expect(AcademicRecord::query()->count())->toBe(3);
});
