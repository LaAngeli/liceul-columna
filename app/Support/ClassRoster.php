<?php

namespace App\Support;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Facades\DB;

/**
 * Ce știe COMPONENȚA unei clase despre elevul care tocmai se adaugă: ce numere matricole sunt
 * deja folosite acolo, care e primul liber și în ce grupă de engleză ar trebui să intre.
 *
 * Sursă unică pentru onboarding (formularul de cont) și pentru adăugarea fișei fără cont — două
 * suprafețe care altfel ar fi ajuns cu reguli ușor diferite.
 *
 * ⚠️ NUMĂRUL MATRICOL, măsurat pe datele reale (2026-08-03): NU e un identificator unic pe școală,
 * ci ordinea elevului ÎN CLASĂ (555 de numere reale, maximul 30, iar „1" apare în 19 clase și „5"
 * în 21). De aceea sugestia și verificarea de duplicat lucrează pe CLASĂ, nu pe școală. Datele
 * moștenite au și duplicate în interiorul clasei (20 din 24 de clase) — de aceea aici doar se
 * PROPUNE următorul liber; curățarea istoricului e o decizie de secretariat.
 */
final class ClassRoster
{
    /**
     * Numerele matricole NUMERICE folosite în clasă (anul înmatriculării clasei).
     *
     * @return array<int, int>
     */
    public static function usedRegisterNumbers(int $classId): array
    {
        $numbers = Enrollment::query()
            ->where('school_class_id', $classId)
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->whereNull('students.deleted_at')
            ->pluck('students.register_number');

        $used = [];

        foreach ($numbers as $number) {
            $raw = trim((string) $number);

            if ($raw !== '' && ctype_digit($raw)) {
                $used[] = (int) $raw;
            }
        }

        $used = array_values(array_unique($used));
        sort($used);

        return $used;
    }

    /** Primul număr liber din clasă (1 într-o clasă goală) — propunere, nu regulă. */
    public static function nextRegisterNumber(int $classId): string
    {
        $used = self::usedRegisterNumbers($classId);

        $candidate = 1;

        while (in_array($candidate, $used, true)) {
            $candidate++;
        }

        return (string) $candidate;
    }

    /** Numărul e deja purtat de un elev al clasei? (Conflict sub ORICE convenție.) */
    public static function registerNumberTaken(int $classId, string $number, ?int $exceptStudentId = null): bool
    {
        $number = trim($number);

        if ($number === '') {
            return false;
        }

        return Enrollment::query()
            ->where('school_class_id', $classId)
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->whereNull('students.deleted_at')
            ->when($exceptStudentId !== null, fn ($query) => $query->where('students.id', '!=', $exceptStudentId))
            ->where('students.register_number', $number)
            ->exists();
    }

    /**
     * Clasa se împarte pe grupe la engleză? Semnalul e alocarea: dacă titularii au grupă, clasa
     * are grupe. Fără asta, „lipsește grupa" ar fi o alarmă falsă pentru toată școala primară.
     */
    public static function usesEnglishGroups(int $classId): bool
    {
        return DB::table('teaching_assignments')
            ->where('school_class_id', $classId)
            ->whereNull('deleted_at')
            ->whereNotNull('english_group')
            ->exists();
    }

    /**
     * Grupa PROPUSĂ pentru un elev nou: cea mai puțin populată din clasă (la egalitate, prima).
     * Null dacă acea clasă nu lucrează pe grupe.
     */
    public static function suggestEnglishGroup(int $classId): ?int
    {
        if (! self::usesEnglishGroups($classId)) {
            return null;
        }

        $counts = [1 => 0, 2 => 0];

        $groups = Student::query()
            ->whereHas('enrollments', fn ($query) => $query->where('school_class_id', $classId))
            ->whereNotNull('english_group')
            ->pluck('english_group');

        foreach ($groups as $group) {
            $value = (int) $group;

            if (isset($counts[$value])) {
                $counts[$value]++;
            }
        }

        return $counts[1] <= $counts[2] ? 1 : 2;
    }

    /**
     * Anul de ÎNMATRICULARE, strict: cel al semestrului curent. Fără semestru curent nu se
     * ghicește un an (fallback-ul „cel mai nou id" putea oferi clasele unui an-fantomă drept
     * destinație) — interfața spune ce lipsește și oprește operațiunea.
     */
    public static function enrollmentYearId(): ?int
    {
        $id = Term::query()->where('is_current', true)->value('academic_year_id');

        return $id === null ? null : (int) $id;
    }
}
