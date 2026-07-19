<?php

namespace App\Console\Commands;

use App\Enums\ScheduleType;
use App\Enums\Weekday;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\Subject;
use Illuminate\Console\Command;

/**
 * Populează orarul STRUCTURAT (Lesson) din orarele PUBLICATE ale claselor (best-effort, calendar
 * v3): fără el, riscul de amânare (spec §2.1 — lecții programate/săptămână) nu se poate calcula.
 * Se parsează doar ce e cert:
 *   • rândurile cu eticheta „Lecția N" (rândurile de program prelungit/pauze se sar natural);
 *   • coloanele ale căror antete sunt zile de școală (Luni–Sâmbătă);
 *   • celulele care ÎNCEP cu un nume de disciplină din nomenclator (normalizare ş/ţ→ș/ț;
 *     cel mai lung prefix câștigă); sala se extrage din „(s. NN)";
 *   • celulele pe grupe se importă doar dacă TOATE grupele fac aceeași disciplină — un slot cu
 *     discipline diferite pe grupe nu se poate reprezenta în modelul actual și se sare.
 * Profesorul rămâne null (numele din celule sunt prescurtate — maparea ar fi nesigură).
 * Clasele care AU deja orar structurat se sar (protejăm intrările manuale); `--force` le rescrie.
 */
class ImportLessonsFromSchedules extends Command
{
    protected $signature = 'app:import-lessons {--force : Rescrie și clasele care au deja orar structurat}';

    protected $description = 'Populează orarul structurat (Lesson) din orarele publicate ale claselor';

    public function handle(): int
    {
        $schedules = Schedule::query()
            ->where('type', ScheduleType::Lessons->value)
            ->where('is_public', true)
            ->whereNotNull('school_class_id')
            ->with('schoolClass')
            ->get();

        if ($schedules->isEmpty()) {
            $this->warn('Niciun orar publicat legat de o clasă.');

            return self::SUCCESS;
        }

        // Numele disciplinelor din nomenclator, cele mai LUNGI întâi (prefix-match corect).
        $subjects = Subject::query()->pluck('id', 'name')
            ->sortKeysUsing(fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $totalImported = 0;

        foreach ($schedules as $schedule) {
            $class = $schedule->schoolClass;

            if ($class === null) {
                continue;
            }

            $existing = Lesson::query()->where('school_class_id', $class->id)->exists();

            if ($existing && ! $this->option('force')) {
                $this->line(sprintf('%s: are deja orar structurat — sărită (folosește --force).', trim($class->name.' '.($class->section ?? ''))));

                continue;
            }

            if ($existing) {
                Lesson::query()->where('school_class_id', $class->id)->forceDelete();
            }

            [$imported, $skipped] = $this->importSchedule($schedule, $subjects->all());
            $totalImported += $imported;

            $this->info(sprintf(
                '%s: %d sloturi importate, %d celule sărite.',
                trim($class->name.' '.($class->section ?? '')),
                $imported,
                $skipped,
            ));
        }

        $this->info("Total: {$totalImported} sloturi.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $subjects  nume RO → id, sortate descrescător după lungime
     * @return array{0: int, 1: int} [importate, sărite]
     */
    private function importSchedule(Schedule $schedule, array $subjects): array
    {
        $class = $schedule->schoolClass;

        if ($class === null) {
            return [0, 0];
        }

        $days = $this->dayColumns(array_values($schedule->headers));

        $imported = 0;
        $skipped = 0;

        foreach ($schedule->rows as $row) {
            $row = array_values($row);
            $label = self::normalize(trim((string) ($row[0] ?? '')));

            // Doar rândurile-lecție („Lecția N …"); pauzele/programul prelungit nu sunt sloturi.
            if (preg_match('/^Lecția\s+(\d+)/u', $label, $m) !== 1) {
                continue;
            }

            $lessonNumber = (int) $m[1];

            foreach ($days as $column => $weekday) {
                $cell = trim((string) ($row[$column] ?? ''));

                if ($cell === '') {
                    continue;
                }

                $slot = $this->parseCell($cell, $subjects);

                if ($slot === null) {
                    $skipped++;

                    continue;
                }

                Lesson::query()->updateOrCreate(
                    [
                        'school_class_id' => $class->id,
                        'academic_year_id' => $class->academic_year_id,
                        'day_of_week' => $weekday,
                        'lesson_number' => $lessonNumber,
                    ],
                    [
                        'subject_id' => $slot['subject_id'],
                        'teacher_id' => null,
                        'room' => $slot['room'],
                    ],
                );
                $imported++;
            }
        }

        return [$imported, $skipped];
    }

    /**
     * Coloanele-zi din antet: index → Weekday (match pe NUMELE zilei, nu pozițional — un orar
     * cu alt aranjament nu produce sloturi greșite).
     *
     * @param  list<string>  $headers
     * @return array<int, Weekday>
     */
    private function dayColumns(array $headers): array
    {
        $map = [
            'Luni' => Weekday::Monday,
            'Marți' => Weekday::Tuesday,
            'Miercuri' => Weekday::Wednesday,
            'Joi' => Weekday::Thursday,
            'Vineri' => Weekday::Friday,
            'Sâmbătă' => Weekday::Saturday,
        ];

        $days = [];

        foreach ($headers as $index => $header) {
            $normalized = self::normalize(trim($header));

            if (isset($map[$normalized])) {
                $days[$index] = $map[$normalized];
            }
        }

        return $days;
    }

    /**
     * O celulă → slot, doar dacă e CERTĂ: începe cu un nume de disciplină din nomenclator, iar
     * dacă are grupe, toate grupele fac ACEEAȘI disciplină. Sala = primul „(s. NN)".
     *
     * @param  array<string, int>  $subjects
     * @return array{subject_id: int, room: string|null}|null
     */
    private function parseCell(string $cell, array $subjects): ?array
    {
        $normalized = self::normalize($cell);

        $matchedId = null;
        $matchedName = null;

        foreach ($subjects as $name => $id) {
            if (str_starts_with($normalized, self::normalize($name))) {
                $matchedId = $id;
                $matchedName = self::normalize($name);

                break; // numele sunt sortate descrescător — primul match e cel mai lung
            }
        }

        if ($matchedId === null || $matchedName === null) {
            return null;
        }

        // Grupe cu discipline DIFERITE în același slot → nereprezentabil, se sare. O a doua
        // apariție a ACELEIAȘI discipline (gr.1/gr.2) e în regulă.
        $rest = substr($normalized, strlen($matchedName));

        foreach ($subjects as $name => $id) {
            if ($id === $matchedId) {
                continue;
            }

            if (str_contains($rest, self::normalize($name))) {
                return null;
            }
        }

        preg_match('/\(s\.\s*([^)]+)\)/u', $cell, $room);

        return [
            'subject_id' => $matchedId,
            'room' => isset($room[1]) ? trim($room[1]) : null,
        ];
    }

    /** Diacriticele legacy cu sedilă (ş/ţ) → forma standard cu virgulă (ș/ț). */
    private static function normalize(string $text): string
    {
        return strtr($text, ['ş' => 'ș', 'ţ' => 'ț', 'Ş' => 'Ș', 'Ţ' => 'Ț']);
    }
}
