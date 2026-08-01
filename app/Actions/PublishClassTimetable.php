<?php

namespace App\Actions;

use App\Enums\ScheduleType;
use App\Enums\Weekday;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Support\WeeklySchedule;
use Illuminate\Support\Collection;

/**
 * Orarul PUBLICAT al unei clase, generat din orarul STRUCTURAT (inversarea lanțului, 2026-08-01).
 *
 * Până acum tabelul de pe site era scris de om, iar sloturile se deduceau din el prin parsare —
 * două locuri de editare pentru același fapt, fără nimic care să semnaleze divergența. De aici
 * înainte sloturile sunt sursa: se introduc în „Orar structurat", iar tabelul se compune din ele.
 *
 * Se regenerează DOAR rândurile-lecție. Un orar publicat nu conține numai lecții: are și pauze,
 * dejunuri și program prelungit — rânduri care descriu programul zilei, nu conținutul predat, și
 * care nu au corespondent în sloturi (36 astfel de rânduri pe datele reale). Ele rămân neatinse,
 * la locul lor. Scheletul zilei ține deci de orarul publicat, conținutul lecțiilor de sloturi.
 *
 * Intervalele orare vin din ORARUL SUNETELOR — el e sursa oficială a orelor, deci nu se dublează
 * aici. Un rând-lecție nou primește intervalul ciclului clasei; dacă orarul sunetelor lipsește,
 * rândul se scrie fără interval (degradare vizibilă, nu oră inventată).
 */
class PublishClassTimetable
{
    /**
     * Republicarea automată e suspendată (import/seed în masă). Fără asta, o migrare de 1549 de
     * sloturi ar recompune tabelul clasei o dată la fiecare slot scris; suspendat, se publică o
     * singură dată per clasă, la final.
     */
    private static bool $suspended = false;

    /** @var array<string, array<int, string>> cheie ciclu → (nr. lecție → interval) */
    private array $bellTimes = [];

    /**
     * Rulează un bloc fără republicare automată. Cine îl folosește RĂSPUNDE de publicarea ulterioară
     * — altfel tabelul de pe site rămâne în urma sloturilor, exact divergența pe care o închidem.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutAutoPublish(callable $callback): mixed
    {
        self::$suspended = true;

        try {
            return $callback();
        } finally {
            self::$suspended = false;
        }
    }

    public static function isSuspended(): bool
    {
        return self::$suspended;
    }

    /**
     * Regenerează tabelul publicat al clasei. Întoarce null dacă nu are ce publica: fără sloturi și
     * fără tabel existent nu se creează un orar gol.
     */
    public function handle(SchoolClass $class): ?Schedule
    {
        $lessons = Lesson::query()
            ->where('school_class_id', $class->id)
            ->with(['subject', 'teacher'])
            ->orderBy('lesson_number')
            ->orderBy('student_group')
            ->get();

        // Aceeași ordonare ca la citirea publică ({@see Schedule::publicTablesFor}): nimic nu
        // împiedică o clasă să aibă două tabele de lecții, iar dacă generatorul l-ar alege pe
        // altul decât cel afișat, sloturile s-ar scrie într-un tabel pe care nu-l vede nimeni.
        $schedule = Schedule::query()
            ->where('type', ScheduleType::Lessons->value)
            ->where('school_class_id', $class->id)
            ->orderBy('position')
            ->orderBy('id')
            ->first();

        if ($lessons->isEmpty() && $schedule === null) {
            return null;
        }

        $headers = $schedule !== null && $schedule->headers !== []
            ? array_values($schedule->headers)
            : self::defaultHeaders();

        $dayColumns = self::dayColumns($headers);

        /** @var Collection<string, Collection<int, Lesson>> $bySlot */
        $bySlot = $lessons->groupBy(
            fn (Lesson $lesson): string => $lesson->lesson_number.'-'.$lesson->day_of_week->value,
        );

        $rows = $this->rebuildRows(
            $schedule !== null ? $schedule->rows : [],
            $headers,
            $dayColumns,
            $bySlot,
            array_values($lessons->pluck('lesson_number')->unique()->sort()->all()),
            $class,
        );

        $attributes = [
            'headers' => $headers,
            'rows' => $rows,
            'label' => $schedule !== null ? $schedule->label : self::classLabel($class),
        ];

        if ($schedule === null) {
            return Schedule::query()->create([
                'type' => ScheduleType::Lessons->value,
                'school_class_id' => $class->id,
                'is_public' => true,
                ...$attributes,
            ]);
        }

        $schedule->fill($attributes)->save();

        return $schedule;
    }

    /**
     * Rândurile noului tabel: cele existente în ordinea lor (lecțiile recompuse, restul intact),
     * plus rândurile pentru lecțiile care există în sloturi dar lipseau din tabel.
     *
     * @param  array<int, array<int, string>>  $existing
     * @param  list<string>  $headers
     * @param  array<int, Weekday>  $dayColumns
     * @param  Collection<string, Collection<int, Lesson>>  $bySlot
     * @param  list<int>  $lessonNumbers
     * @return list<list<string>>
     */
    private function rebuildRows(
        array $existing,
        array $headers,
        array $dayColumns,
        Collection $bySlot,
        array $lessonNumbers,
        SchoolClass $class,
    ): array {
        $rows = [];
        $covered = [];

        foreach ($existing as $row) {
            $row = array_values($row);
            $label = trim((string) ($row[0] ?? ''));

            if (preg_match('/^Lec[țţ]ia\s+(\d+)/iu', $label, $m) !== 1) {
                // Pauză, dejun, program prelungit: programul zilei, nu conținut predat.
                $rows[] = array_map(strval(...), $row);

                continue;
            }

            $number = (int) $m[1];
            $covered[$number] = true;
            $rows[] = $this->lessonRow($label, $number, $headers, $dayColumns, $bySlot);
        }

        foreach ($lessonNumbers as $number) {
            if (isset($covered[$number])) {
                continue;
            }

            $rows[] = $this->lessonRow(
                $this->lessonLabel($number, $class),
                $number,
                $headers,
                $dayColumns,
                $bySlot,
            );
        }

        return $rows;
    }

    /**
     * Un rând-lecție: eticheta lui (păstrată dacă exista — poartă intervalul real) + o celulă per zi.
     *
     * @param  list<string>  $headers
     * @param  array<int, Weekday>  $dayColumns
     * @param  Collection<string, Collection<int, Lesson>>  $bySlot
     * @return list<string>
     */
    private function lessonRow(string $label, int $number, array $headers, array $dayColumns, Collection $bySlot): array
    {
        $row = array_fill(0, count($headers), '');
        $row[0] = $label;

        foreach ($dayColumns as $column => $weekday) {
            $slots = $bySlot->get($number.'-'.$weekday->value);

            $row[$column] = $slots === null
                ? ''
                : $slots->map(self::cell(...))->filter()->implode(' ');
        }

        return array_values($row);
    }

    /**
     * Un slot → textul din celulă, în forma folosită de orarele reale: „Disciplină , gr.1, Nume I.
     * (s. 12)". Formatul nu e ales estetic — e cel pe care îl citește înapoi
     * {@see WeeklySchedule::parseCell} pentru cabinet, deci ce se generează aici
     * trebuie să se poată segmenta la loc.
     */
    private static function cell(Lesson $lesson): string
    {
        $text = trim($lesson->displayTitle());

        if ($text === '') {
            return '';
        }

        if ($lesson->student_group !== '') {
            $text .= ' , gr.'.$lesson->student_group;
        }

        $teacher = $lesson->displayTeacher();

        if ($teacher !== null) {
            $text .= ', '.$teacher;
        }

        $room = trim((string) ($lesson->room ?? ''));

        if ($room !== '') {
            $text .= ' (s. '.$room.')';
        }

        return $text;
    }

    /** Eticheta unui rând-lecție NOU: „Lecția N" + intervalul ciclului, dacă orarul sunetelor îl dă. */
    private function lessonLabel(int $number, SchoolClass $class): string
    {
        $times = $this->bellTimesFor($class);
        $interval = $times[$number] ?? null;

        return $interval !== null ? sprintf('Lecția %d %s', $number, $interval) : sprintf('Lecția %d', $number);
    }

    /**
     * Intervalele orare ale ciclului clasei, citite din orarul sunetelor („Lecția N" → „08.00 –
     * 08.45"). Primar = 1–4; restul = tabelul gimnazial/liceal. Cu un singur tabel publicat, el
     * servește toate clasele.
     *
     * @return array<int, string>
     */
    private function bellTimesFor(SchoolClass $class): array
    {
        $primary = (int) $class->grade_level <= 4;
        $key = $primary ? 'primar' : 'gimnazial';

        if (isset($this->bellTimes[$key])) {
            return $this->bellTimes[$key];
        }

        $tables = Schedule::query()
            ->where('type', ScheduleType::Bells->value)
            ->where('is_public', true)
            ->orderBy('position')
            ->get();

        $chosen = $tables->first(
            fn (Schedule $table): bool => str_contains(mb_strtolower($table->label), 'primar') === $primary,
        ) ?? $tables->first();

        $times = [];

        foreach ($chosen !== null ? $chosen->rows : [] as $row) {
            $row = array_values($row);

            if (preg_match('/^Lec[țţ]ia\s+(\d+)/iu', trim((string) ($row[0] ?? '')), $m) !== 1) {
                continue;
            }

            $interval = trim((string) ($row[1] ?? ''));

            if ($interval !== '') {
                $times[(int) $m[1]] = str_replace(' - ', ' – ', $interval);
            }
        }

        return $this->bellTimes[$key] = $times;
    }

    /**
     * Antetul implicit, în ROMÂNĂ literal — nu prin `Weekday::label()`. Tabelele se stochează în
     * limba de bază și se traduc la afișare (ContentTranslator); generate în limba activă a
     * sesiunii, un orar salvat de un utilizator cu interfața în rusă ar rămâne rusesc pentru toți.
     *
     * @return list<string>
     */
    private static function defaultHeaders(): array
    {
        return ['', 'Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri'];
    }

    /**
     * Coloanele-zi din antet: index → zi, pe NUMELE zilei (nu pozițional), ca un tabel cu alt
     * aranjament de coloane să nu primească lecțiile altei zile.
     *
     * @param  list<string>  $headers
     * @return array<int, Weekday>
     */
    private static function dayColumns(array $headers): array
    {
        $map = [
            'luni' => Weekday::Monday,
            'marți' => Weekday::Tuesday,
            'miercuri' => Weekday::Wednesday,
            'joi' => Weekday::Thursday,
            'vineri' => Weekday::Friday,
            'sâmbătă' => Weekday::Saturday,
        ];

        $columns = [];

        foreach ($headers as $index => $header) {
            $normalized = mb_strtolower(strtr(trim($header), ['ş' => 'ș', 'ţ' => 'ț']));

            if (isset($map[$normalized])) {
                $columns[$index] = $map[$normalized];
            }
        }

        return $columns;
    }

    private static function classLabel(SchoolClass $class): string
    {
        return trim('Clasa '.$class->name.' '.($class->section ?? ''));
    }
}
