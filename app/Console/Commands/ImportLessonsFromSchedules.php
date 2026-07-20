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
 *   • celulele care ÎNCEP cu o disciplină VALABILĂ PE TREAPTA CLASEI (normalizare ş/ţ→ș/ț și
 *     minuscule; cel mai lung prefix câștigă); sala se extrage din „(s. NN)";
 *   • celulele pe grupe se importă doar dacă TOATE grupele fac aceeași disciplină — un slot cu
 *     discipline diferite pe grupe nu se poate reprezenta în modelul actual și se sare.
 * Profesorul rămâne null (numele din celule sunt prescurtate — maparea ar fi nesigură).
 * Ce nu s-a putut rezolva se RAPORTEAZĂ la final, grupat: o celulă pierdută în tăcere e exact ce a
 * ascuns luni de zile legarea lecțiilor de fișa altui ciclu.
 * Clasele care AU deja orar structurat se sar (protejăm intrările manuale); `--force` le rescrie.
 */
class ImportLessonsFromSchedules extends Command
{
    protected $signature = 'app:import-lessons {--force : Rescrie și clasele care au deja orar structurat}';

    protected $description = 'Populează orarul structurat (Lesson) din orarele publicate ale claselor';

    /**
     * Vocabularul orarelor → nomenclator. Orarele scriu denumirile colocvial; nomenclatorul le are
     * pe cele oficiale. Fiecare intrare e CONFIRMATĂ pe date, nu presupusă:
     *   • „Limba română" apare în 9 din cele 25 de orare, „Limba și literatura română" în 15 — două
     *     scrieri ale aceleiași discipline (nomenclatorul n-are altă limbă română);
     *   • „Limba franceză"/„Limba germană" nu există ca fișe: sunt cele două grupe ale disciplinei
     *     „Limba străină 2". Confirmat pe alocări — Golban O. și Arhip S., singurii profesori din
     *     acele celule, predau EXCLUSIV „Limba străină 2" (14, respectiv 15 clase).
     *
     * NU se aliasează „Limba engleză": nomenclatorul are DOUĂ fișe („Limba străină 1 (engleza)" și
     * „Limba engleză (opț)"), iar orarele nu marchează niciodată opționalul — verificat, zero
     * apariții de „opț" în cele 25 de orare. A alege una ar umfla numărul de lecții programate al
     * uneia și l-ar anula pe al celeilalte, adică ar falsifica exact numitorul riscului de amânare.
     * Celulele rămân nerezolvate și RAPORTATE, până când sursa distinge cele două ore.
     *
     * @var array<string, string> denumire din orar (normalizată) → denumire din nomenclator
     */
    private const ALIASES = [
        'limba română' => 'limba și literatura română',
        'limba franceză' => 'limba străină 2',
        'limba germană' => 'limba străină 2',
    ];

    /** @var array<int, array<string, int>> treaptă → (nume normalizat → id disciplină) */
    private array $subjectsByGrade = [];

    /** @var list<string> nume din nomenclator + aliasuri, normalizate, cele mai lungi întâi */
    private array $allNames = [];

    /** @var array<string, string> nume → denumirea canonică (aliasul se rezolvă la ținta lui) */
    private array $canonicalNames = [];

    /** @var array<string, int> celulă (prescurtată) → de câte ori n-a putut fi rezolvată */
    private array $unresolved = [];

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

        $this->loadNomenclature();

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
                Lesson::query()->where('school_class_id', $class->id)->delete();
            }

            [$imported, $skipped] = $this->importSchedule($schedule);
            $totalImported += $imported;

            $this->info(sprintf(
                '%s: %d sloturi importate, %d celule sărite.',
                trim($class->name.' '.($class->section ?? '')),
                $imported,
                $skipped,
            ));
        }

        $this->info("Total: {$totalImported} sloturi.");
        $this->reportUnresolved();

        return self::SUCCESS;
    }

    /**
     * Nomenclatorul, în două forme cu roluri DIFERITE:
     *   • `subjectsByGrade` — pentru SELECȚIE: doar fișele valabile pe treapta clasei. Zece denumiri
     *     există în câte 2-3 fișe, una per ciclu („Matematică" [1-4] și [5-12]); o hartă globală
     *     nume→id păstrează una singură și leagă tăcut lecțiile de a XII-a de fișa ciclului primar
     *     (starea găsită în producție: 219 din 507 lecții). Pe o treaptă dată denumirile sunt unice.
     *   • `allNames` — pentru DETECȚIA grupelor mixte: numele întregului nomenclator, indiferent de
     *     treaptă. Garda trebuie să recunoască prezența unei a doua discipline chiar dacă aceasta nu
     *     e valabilă pe treapta clasei, altfel slotul mixt intră ca și cum ar fi fost simplu.
     */
    private function loadNomenclature(): void
    {
        $subjects = Subject::query()->get(['id', 'name', 'min_grade', 'max_grade']);

        $names = $subjects
            ->map(fn (Subject $subject): string => self::normalize($subject->name))
            ->unique()
            ->values()
            ->all();

        foreach ($names as $name) {
            $this->canonicalNames[$name] = $name;
        }

        foreach (self::ALIASES as $alias => $target) {
            $this->canonicalNames[$alias] = $target;
            $names[] = $alias;
        }

        // Cele mai LUNGI întâi: prefix-match corect („Limba engleză (opț)" înaintea lui „Limba
        // engleză"), iar la scanarea restului celulei numele scurt cuprins în altul nu-l fură.
        usort($names, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $this->allNames = $names;

        foreach (range(1, 12) as $grade) {
            $forGrade = $subjects
                ->filter(fn (Subject $subject): bool => ($subject->min_grade === null || $subject->min_grade <= $grade)
                    && ($subject->max_grade === null || $subject->max_grade >= $grade))
                ->sortByDesc(fn (Subject $subject): int => strlen($subject->name));

            $map = [];

            foreach ($forGrade as $subject) {
                $map[self::normalize($subject->name)] ??= $subject->id;
            }

            // Aliasul intră doar dacă ținta lui e valabilă pe treaptă — și doar dacă nu calcă peste
            // o denumire reală, care rămâne mereu prioritară.
            foreach (self::ALIASES as $alias => $target) {
                if (isset($map[$target]) && ! isset($map[$alias])) {
                    $map[$alias] = $map[$target];
                }
            }

            uksort($map, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

            $this->subjectsByGrade[$grade] = $map;
        }
    }

    /**
     * Celulele care n-au putut fi legate de o disciplină — tipărite la final, grupate. Tăcerea de
     * aici e exact ce a ascuns bug-ul de mai sus: o celulă nerezolvată trebuie să se VADĂ, ca să
     * ajungă fie aliasul lipsă, fie fișa lipsă din nomenclator.
     */
    private function reportUnresolved(): void
    {
        if ($this->unresolved === []) {
            return;
        }

        arsort($this->unresolved);

        $this->newLine();
        $this->warn('Celule nerezolvate (disciplină negăsită pe treapta clasei sau grupe cu discipline diferite):');

        foreach (array_slice($this->unresolved, 0, 25, true) as $cell => $count) {
            $this->line(sprintf('  %2d× %s', $count, $cell));
        }

        if (count($this->unresolved) > 25) {
            $this->line(sprintf('  … și încă %d variante.', count($this->unresolved) - 25));
        }
    }

    /**
     * @return array{0: int, 1: int} [importate, sărite]
     */
    private function importSchedule(Schedule $schedule): array
    {
        $class = $schedule->schoolClass;

        if ($class === null) {
            return [0, 0];
        }

        // Disciplinele valabile pe treapta ACESTEI clase. O treaptă în afara 1-12 (dată coruptă) nu
        // primește nicio candidată — se raportează, nu se ghicește.
        $subjects = $this->subjectsByGrade[$class->grade_level] ?? [];

        $days = $this->dayColumns(array_values($schedule->headers));

        $imported = 0;
        $skipped = 0;

        foreach ($schedule->rows as $row) {
            $row = array_values($row);
            $label = self::normalize(trim((string) ($row[0] ?? '')));

            // Doar rândurile-lecție („Lecția N …"); pauzele/programul prelungit nu sunt sloturi.
            if (preg_match('/^lecția\s+(\d+)/u', $label, $m) !== 1) {
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
                    $key = mb_substr(preg_replace('/\s+/u', ' ', $cell) ?? $cell, 0, 60);
                    $this->unresolved[$key] = ($this->unresolved[$key] ?? 0) + 1;

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
        // Chei în minuscule: `normalize()` coboară cazul, iar un antet scris „LUNI" trebuie să
        // producă aceeași zi.
        $map = [
            'luni' => Weekday::Monday,
            'marți' => Weekday::Tuesday,
            'miercuri' => Weekday::Wednesday,
            'joi' => Weekday::Thursday,
            'vineri' => Weekday::Friday,
            'sâmbătă' => Weekday::Saturday,
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
     * O celulă → slot, doar dacă e CERTĂ: începe cu o disciplină valabilă PE TREAPTA CLASEI, iar
     * dacă are grupe, toate grupele fac ACEEAȘI disciplină. Sala = primul „(s. NN)".
     *
     * Fără fallback pe nume când nimic nu se potrivește pe treaptă: a lega celula de fișa altui
     * ciclu „ca să nu se piardă slotul" e exact bug-ul tăcut reparat aici. Celula se sare și se
     * raportează.
     *
     * @param  array<string, int>  $subjects  numele valabile pe treaptă (normalizate), lungi întâi
     * @return array{subject_id: int, room: string|null}|null
     */
    private function parseCell(string $cell, array $subjects): ?array
    {
        $normalized = self::normalize($cell);

        $matchedId = null;
        $matchedName = null;

        foreach ($subjects as $name => $id) {
            if (str_starts_with($normalized, $name)) {
                $matchedId = $id;
                $matchedName = $name;

                break; // numele sunt sortate descrescător — primul match e cel mai lung
            }
        }

        if ($matchedId === null || $matchedName === null) {
            return null;
        }

        if ($this->hasOtherSubject(substr($normalized, strlen($matchedName)), $matchedName)) {
            return null;
        }

        preg_match('/\(s\.\s*([^)]+)\)/u', $cell, $room);

        return [
            'subject_id' => $matchedId,
            'room' => isset($room[1]) ? trim($room[1]) : null,
        ];
    }

    /**
     * Restul celulei conține o disciplină DIFERITĂ de cea deja identificată?
     *
     * Scanare stânga→dreapta care consumă, la fiecare poziție, CEL MAI LUNG nume care se potrivește
     * — nu `str_contains` peste tot restul. Două denumiri din nomenclator se cuprind una pe alta
     * („Fizică" ⊂ „Educație fizică", „Geografie" ⊂ „Geografie aplicată"), iar căutarea naivă le
     * confunda în ambele sensuri: „Educație fizică gr.1 / Educație fizică gr.2" era respinsă ca slot
     * mixt (găsea „Fizică" în a doua apariție a ACELEIAȘI discipline), iar un slot pornit cu
     * „Fizică" și continuat cu „Educație fizică" trecea drept simplu. Consumând cel mai lung nume,
     * a doua apariție a disciplinei proprii se sare întreagă, iar una străină se vede întreagă.
     *
     * @param  string  $rest  restul celulei, normalizat
     * @param  string  $matched  numele deja identificat, normalizat
     */
    private function hasOtherSubject(string $rest, string $matched): bool
    {
        $length = strlen($rest);
        $position = 0;

        while ($position < $length) {
            $found = null;

            foreach ($this->allNames as $name) {
                if (str_starts_with(substr($rest, $position), $name)) {
                    $found = $name;

                    break; // sortate descrescător după lungime — primul e cel mai lung
                }
            }

            if ($found === null) {
                $position++;

                continue;
            }

            // Comparație pe denumirea CANONICĂ: „Limba franceză" și „Limba germană" sunt cele două
            // grupe ale aceleiași discipline („Limba străină 2"), nu un slot mixt.
            if (($this->canonicalNames[$found] ?? $found) !== ($this->canonicalNames[$matched] ?? $matched)) {
                return true;
            }

            $position += strlen($found);
        }

        return false;
    }

    /** Diacriticele legacy cu sedilă (ş/ţ) → forma standard cu virgulă (ș/ț). */
    private static function normalize(string $text): string
    {
        // Minuscule: orarele scriu aceeași disciplină cu majuscule variate („În împărăția lui MATE"
        // vs fișa „În împărăția lui Mate") — 9 celule se pierdeau doar pe diferența de caz.
        return mb_strtolower(strtr($text, ['ş' => 'ș', 'ţ' => 'ț', 'Ş' => 'Ș', 'Ţ' => 'Ț']));
    }
}
