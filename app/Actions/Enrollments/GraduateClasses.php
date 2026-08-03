<?php

namespace App\Actions\Enrollments;

use App\Actions\ArchiveYearToTranscript;
use App\Console\Commands\PurgeExpiredStudents;
use App\Enums\DepartureReason;
use App\Enums\SchoolCycle;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\SchoolCalendar;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * ABSOLVIREA unei promoții: elevii activi ai claselor terminale ies din registru cu motivul
 * `absolvire`, la data încheierii anului.
 *
 * Era pasul care lipsea din tot ciclul de viață. {@see OpenAcademicYear} identifica deja clasele
 * de treaptă maximă și scria în previzualizare că „nu se preiau — promovarea lor e absolvirea", dar
 * nu se întâmpla nimic cu ele: elevii rămâneau înmatriculați ACTIV într-un an încheiat, la infinit.
 * Consecințele mergeau dincolo de cifre umflate — {@see PurgeExpiredStudents}
 * pornește ceasul de retenție (12 ani, L133 §7) de la `left_on`, deci fără el dosarele promoțiilor
 * nu deveneau niciodată eligibile de ștergere.
 *
 * Ce NU face, deliberat:
 *   • nu atinge conturile — absolventul păstrează acces read-only la propria arhivă (decizia
 *     beneficiarului, 2026-08-03); restrângerea se face pe ELEV, {@see Student::isAlumnus()};
 *   • nu atinge datele academice — foaia matricolă e construită separat, la închiderea anului
 *     ({@see ArchiveYearToTranscript});
 *   • nu marchează elevii deja plecați în cursul anului (transfer, retragere): au motivul lor.
 */
class GraduateClasses
{
    public function __construct(private readonly MarkDeparture $markDeparture) {}

    /**
     * Clasele TERMINALE ale unui an — cele de treaptă maximă, care nu au unde promova.
     *
     * @return Collection<int, SchoolClass>
     */
    public function terminalClasses(AcademicYear $year): Collection
    {
        return SchoolClass::query()
            ->where('academic_year_id', $year->getKey())
            ->where('grade_level', '>=', SchoolCycle::MAX_GRADE_LEVEL)
            ->orderBy('name')
            ->orderBy('section')
            ->get();
    }

    /**
     * Câți elevi ACTIVI mai are de absolvit anul dat. Zero înseamnă promoție deja încheiată —
     * acțiunea se poate relua fără efect (idempotentă).
     */
    public function pendingCount(AcademicYear $year): int
    {
        $classIds = $this->terminalClasses($year)->modelKeys();

        if ($classIds === []) {
            return 0;
        }

        return Enrollment::query()
            ->whereIn('school_class_id', $classIds)
            ->whereNull('left_on')
            ->count();
    }

    /**
     * Data implicită a absolvirii: ultima zi a anului școlar. E data de registru — nu „azi", care
     * ar consemna absolvirea în ziua în care operatorul s-a apucat de treabă, uneori luni mai
     * târziu, falsificând intervalul de înmatriculare.
     */
    public function defaultDate(AcademicYear $year): Carbon
    {
        [, $to] = SchoolCalendar::yearSpan($year);

        return $to->copy()->startOfDay();
    }

    /**
     * @return array{graduated: int, classes: int}
     */
    public function handle(AcademicYear $year, ?Carbon $date = null): array
    {
        $classes = $this->terminalClasses($year);

        if ($classes->isEmpty()) {
            return ['graduated' => 0, 'classes' => 0];
        }

        $enrollmentIds = Enrollment::query()
            ->whereIn('school_class_id', $classes->modelKeys())
            ->whereNull('left_on')
            ->pluck('id')
            ->map(intval(...))
            ->all();

        if ($enrollmentIds === []) {
            return ['graduated' => 0, 'classes' => 0];
        }

        $result = $this->markDeparture->handle(
            array_values($enrollmentIds),
            $date ?? $this->defaultDate($year),
            DepartureReason::Absolvire,
        );

        return ['graduated' => $result['marked'], 'classes' => $classes->count()];
    }
}
