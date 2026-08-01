<?php

namespace App\Console\Commands;

use App\Actions\PublishClassTimetable;
use App\Enums\ScheduleType;
use App\Enums\Weekday;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\Teacher;
use App\Support\WeeklySchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

/**
 * MIGRAREA orarelor scrise ca text în orarul STRUCTURAT (Lesson) — pasul unic prin care lanțul se
 * inversează: de aici înainte sloturile sunt sursa, iar tabelul publicat pe site se GENEREAZĂ din
 * ele ({@see PublishClassTimetable}).
 *
 * Pentru că orarul publicat nu mai e scris de om, importul nu mai are voie să piardă nimic din ce
 * se vedea pe site. De aceea celula se ia INTEGRAL, prin segmentarea deja folosită la afișare
 * ({@see WeeklySchedule::parseCell}), iar fiecare parte are unde să aterizeze:
 *   • grupele („gr.1"/„gr.2") → sloturi distincte pe aceeași oră (coloana `student_group`);
 *   • disciplina → `subject_id` dacă e în nomenclatorul TREPTEI, altfel `title` (orarele conțin
 *     și ore care nu sunt discipline: „Managementul clasei", „Consultații…");
 *   • profesorul → fișa reală dacă numele prescurtat („Buga A.") o identifică univoc, altfel
 *     `teacher_name` (trei persoane din orare nu au încă fișă — numele lor rămâne pe site);
 *   • sala → `room`.
 * Un singur caz rămâne nestructurabil și e tratat explicit: mai multe discipline în aceeași celulă
 * FĂRĂ grupe care să le separe. Acolo nu există structură de descoperit, deci celula intră ca UN
 * slot cu textul ei — fidel, chiar dacă nu analizabil. Nimic nu se sare în tăcere.
 *
 * Rândurile fără etichetă „Lecția N" (pauze, program prelungit) nu sunt sloturi. Clasele care AU
 * deja orar structurat se sar, ca să nu calce peste intrările manuale; `--force` le rescrie.
 */
class ImportLessonsFromSchedules extends Command
{
    protected $signature = 'app:import-lessons
        {--force : Rescrie și clasele care au deja orar structurat}
        {--only= : Doar clasele al căror nume începe cu acest prefix (ex. „[DEMO]")}';

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

    /** @var array<int, string> id disciplină → denumirea din nomenclator */
    private array $subjectNames = [];

    /** @var array<string, int> celulă (prescurtată) → de câte ori n-a putut fi rezolvată */
    private array $unresolved = [];

    /** @var array<string, list<array{id: int, first: string}>> nume de familie normalizat → fișe */
    private array $teachersByLastName = [];

    public function __construct(private readonly WeeklySchedule $weekly)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Segmentarea trece prin ContentTranslator (traduce disciplina în limba activă). La import
        // avem nevoie de forma din nomenclator, deci de română — indiferent de locale-ul din config.
        App::setLocale('ro');

        $only = (string) ($this->option('only') ?? '');

        $schedules = Schedule::query()
            ->where('type', ScheduleType::Lessons->value)
            ->where('is_public', true)
            ->whereNotNull('school_class_id')
            // `--force` fără filtru rescrie orarul structurat al TUTUROR claselor, inclusiv
            // eventualele intrări manuale. `--only` restrânge la un perimetru (ex. zona demo), ca o
            // populare de test să nu atingă clasele reale.
            ->when($only !== '', fn ($query) => $query->whereHas(
                'schoolClass',
                fn ($inner) => $inner->where('name', 'like', $only.'%'),
            ))
            ->with('schoolClass')
            ->get();

        if ($schedules->isEmpty()) {
            $this->warn('Niciun orar publicat legat de o clasă.');

            return self::SUCCESS;
        }

        $this->loadNomenclature();
        $this->loadTeachers();

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

            // Publicarea automată se SUSPENDĂ pe durata importului — nu din motive de viteză, ci de
            // corectitudine: sursa citită aici e chiar tabelul pe care publicarea l-ar rescrie.
            // Regenerarea rămâne un pas separat și deliberat (`app:publish-timetables`), rulat după
            // ce diferențele au fost verificate.
            [$imported, $unstructured] = PublishClassTimetable::withoutAutoPublish(
                fn (): array => $this->importSchedule($schedule),
            );
            $totalImported += $imported;

            $this->info(sprintf(
                '%s: %d sloturi, din care %d ca text nestructurat.',
                trim($class->name.' '.($class->section ?? '')),
                $imported,
                $unstructured,
            ));
        }

        $this->info("Total: {$totalImported} sloturi.");
        $this->reportUnresolved();
        $this->newLine();
        $this->comment('Orarele publicate NU au fost atinse. Regenerează-le cu `app:publish-timetables`.');

        return self::SUCCESS;
    }

    /**
     * `subjectsByGrade` — doar fișele valabile pe treapta clasei. Zece denumiri există în câte 2-3
     * fișe, una per ciclu („Matematică" [1-4] și [5-12]); o hartă globală nume→id păstrează una
     * singură și leagă tăcut lecțiile de a XII-a de fișa ciclului primar (starea găsită în
     * producție: 219 din 507 lecții). Pe o treaptă dată denumirile sunt unice.
     */
    private function loadNomenclature(): void
    {
        $subjects = Subject::query()->get(['id', 'name', 'min_grade', 'max_grade']);

        foreach (range(1, 12) as $grade) {
            $forGrade = $subjects
                ->filter(fn (Subject $subject): bool => ($subject->min_grade === null || $subject->min_grade <= $grade)
                    && ($subject->max_grade === null || $subject->max_grade >= $grade))
                ->sortByDesc(fn (Subject $subject): int => strlen($subject->name));

            $map = [];

            foreach ($forGrade as $subject) {
                $map[self::normalize($subject->name)] ??= $subject->id;
                $this->subjectNames[$subject->id] = $subject->name;
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
     * @return array{0: int, 1: int} [sloturi scrise, celule intrate ca text nestructurat]
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
        $unstructured = 0;

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

                $slots = $this->slotsFromCell($cell, $subjects);

                if (count($slots) === 1 && $slots[0]['subject_id'] === null) {
                    $unstructured++;
                    $key = mb_substr(preg_replace('/\s+/u', ' ', $cell) ?? $cell, 0, 60);
                    $this->unresolved[$key] = ($this->unresolved[$key] ?? 0) + 1;
                }

                foreach ($slots as $slot) {
                    Lesson::query()->updateOrCreate(
                        [
                            'school_class_id' => $class->id,
                            'academic_year_id' => $class->academic_year_id,
                            'day_of_week' => $weekday,
                            'lesson_number' => $lessonNumber,
                            'student_group' => $slot['student_group'],
                        ],
                        [
                            'subject_id' => $slot['subject_id'],
                            'title' => $slot['title'],
                            'teacher_id' => $slot['teacher_id'],
                            'teacher_name' => $slot['teacher_name'],
                            'room' => $slot['room'],
                        ],
                    );
                    $imported++;
                }
            }
        }

        return [$imported, $unstructured];
    }

    /**
     * O celulă → sloturile ei. Segmentarea e cea de la AFIȘARE (sursă unică de adevăr pentru „ce
     * scrie în celulă"), ca importul și cabinetul să nu poată diverge în interpretare.
     *
     * Mai multe segmente se pot păstra separate doar dacă grupele le disting — indexul unic e pe
     * (clasă, an, zi, oră, grupă). Două discipline în aceeași celulă fără grupe n-au cum să ocupe
     * ambele slotul, așa că celula intră ÎNTREAGĂ ca text: pierderea ar fi fost tăcută, textul nu e.
     *
     * @param  array<string, int>  $subjects  denumiri valabile pe treaptă (normalizate)
     * @return list<array{student_group: string, subject_id: int|null, title: string|null, teacher_id: int|null, teacher_name: string|null, room: string|null}>
     */
    private function slotsFromCell(string $cell, array $subjects): array
    {
        $parsed = $this->weekly->parseCell($cell);
        $segments = $parsed['segments'];

        $groups = array_map(
            static fn (array $segment): string => self::groupKey($segment['group']),
            $segments,
        );

        $distinctGroups = count(array_unique($groups)) === count($groups);

        // Segmentarea a eșuat dacă ce a rămas ca denumire mai poartă structură — o a doua grupă, o a
        // doua sală, un al doilea nume. Se întâmplă când celula conține o disciplină ABSENTĂ din
        // nomenclator („Robotică"), pe care parserul nu are cum s-o vadă ca graniță. Fără această
        // verificare, disciplina negăsită rămânea lipită de prima, iar generarea adăuga peste ea
        // încă o dată grupa, profesorul și sala.
        $residue = array_filter(
            $segments,
            static fn (array $segment): bool => preg_match('/\bgr\.|\(s\.|\p{Lu}[\p{L}\'’\-]+\s+\p{Lu}\p{Ll}{0,2}\./u', $segment['subject']) === 1,
        );

        if ((count($segments) > 1 && ! $distinctGroups) || $residue !== []) {
            // Celula intră ca text — dar dacă toate bucățile ei duc la ACEEAȘI disciplină, legătura
            // se păstrează: „Limba franceză … Limba germană" sunt cele două grupe ale disciplinei
            // „Limba străină 2", iar fără `subject_id` orele acelea ar dispărea din numitorul
            // riscului de amânare. Textul rămâne cel original, deci afișarea nu se schimbă.
            $ids = array_map(
                fn (array $segment): ?int => $this->matchSubject($segment['subject'], $subjects)[0],
                $segments,
            );
            $unique = array_values(array_unique(array_filter($ids, is_int(...))));

            return [[
                'student_group' => '',
                'subject_id' => count($unique) === 1 && count($ids) === count(array_filter($ids, is_int(...)))
                    ? $unique[0]
                    : null,
                // Sala NU se extrage separat aici: textul brut o poartă deja (uneori pe mai multe,
                // câte una per disciplină), iar adăugată din nou ar apărea de două ori pe site.
                'title' => $parsed['raw'],
                'teacher_id' => null,
                'teacher_name' => null,
                'room' => null,
            ]];
        }

        $slots = [];

        foreach ($segments as $index => $segment) {
            [$subjectId, $subjectName] = $this->matchSubject($segment['subject'], $subjects);
            $label = trim($segment['subject']);
            $teacher = trim((string) $segment['teacher']);

            $slots[] = [
                'student_group' => $groups[$index],
                'subject_id' => $subjectId,
                // Eticheta se păstrează doar când spune ALTCEVA decât fișa: „Limba germană" pentru
                // fișa „Limba străină 2", „Limba română" pentru „Limba și literatura română".
                // Identică cu fișa, ar fi doar o copie care se învechește la prima redenumire.
                'title' => $subjectName !== null && self::normalize($label) === self::normalize($subjectName)
                    ? null
                    : $label,
                'teacher_id' => $this->matchTeacher($segment['teacher']),
                'teacher_name' => $teacher !== '' ? $teacher : null,
                'room' => $parsed['room'],
            ];
        }

        return $slots;
    }

    /** „gr. 1" → „1"; fără grupă → șirul gol (santinela indexului unic). */
    private static function groupKey(?string $group): string
    {
        if ($group === null) {
            return '';
        }

        return preg_match('/(\d+|[IVX]+)/u', $group, $m) === 1 ? $m[1] : '';
    }

    /**
     * Denumirea din celulă → fișa de disciplină a TREPTEI. Fără fallback pe alte trepte: a lega
     * lecția de fișa altui ciclu „ca să nu se piardă" e bug-ul tăcut reparat în LOT 6.
     *
     * @param  array<string, int>  $subjects
     * @return array{0: int|null, 1: string|null} [id fișă, denumirea potrivită]
     */
    private function matchSubject(string $name, array $subjects): array
    {
        $normalized = self::normalize(trim($name));

        if ($normalized === '') {
            return [null, null];
        }

        // Potrivire exactă întâi; apoi prefix (celula poate purta un sufix: „Matematică opțional").
        // Se întoarce numele REAL al fișei, nu cheia potrivită: cheile includ aliasurile („limba
        // română" → fișa „Limba și literatura română"), iar comparând cu aliasul ar ieși că eticheta
        // coincide cu fișa și denumirea din orar s-ar pierde.
        if (isset($subjects[$normalized])) {
            return [$subjects[$normalized], $this->subjectNames[$subjects[$normalized]] ?? null];
        }

        foreach ($subjects as $candidate => $id) {
            if (str_starts_with($normalized, $candidate)) {
                return [$id, $this->subjectNames[$id] ?? null];
            }
        }

        return [null, null];
    }

    /**
     * Numele prescurtat din orar („Buga A.", „Bujor-Cobili C.") → fișa de profesor.
     *
     * Se acceptă DOAR potrivirea univocă: nume de familie identic (sau ultimul segment al unui nume
     * compus) ȘI prenumele care începe cu inițiala scrisă. Doi omonimi cu aceeași inițială → nu se
     * alege niciunul. Măsurat pe orarele reale: 601 din 712 apariții se rezolvă univoc, zero
     * ambigue; restul sunt persoane fără fișă în sistem — pe site rămân prin `teacher_name`.
     */
    private function matchTeacher(?string $label): ?int
    {
        $label = trim((string) $label);

        if ($label === '' || preg_match('/^(\p{L}[\p{L}\'’\-]*)\s+(\p{L})/u', $label, $m) !== 1) {
            return null;
        }

        $lastName = self::normalize($m[1]);
        $initial = self::normalize($m[2]);

        $matching = array_values(array_filter(
            $this->teachersByLastName[$lastName] ?? [],
            static fn (array $teacher): bool => str_starts_with($teacher['first'], $initial),
        ));

        return count($matching) === 1 ? $matching[0]['id'] : null;
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
     * Fișele de profesor indexate pe numele de familie — și pe ULTIMUL segment al numelor compuse:
     * orarele scriu „Cobili C." pentru fișa „Bujor-Cobili Carolina" (23 de apariții pe date reale).
     */
    private function loadTeachers(): void
    {
        foreach (Teacher::query()->get(['id', 'last_name', 'first_name']) as $teacher) {
            $entry = ['id' => $teacher->id, 'first' => self::normalize($teacher->first_name)];
            $last = self::normalize($teacher->last_name);

            $this->teachersByLastName[$last][] = $entry;

            $segments = preg_split('/[\s\-]+/u', $last) ?: [];

            if (count($segments) > 1) {
                $tail = (string) end($segments);

                if ($tail !== '' && $tail !== $last) {
                    $this->teachersByLastName[$tail][] = $entry;
                }
            }
        }
    }

    /** Diacriticele legacy cu sedilă (ş/ţ) → forma standard cu virgulă (ș/ț). */
    private static function normalize(string $text): string
    {
        // Minuscule: orarele scriu aceeași disciplină cu majuscule variate („În împărăția lui MATE"
        // vs fișa „În împărăția lui Mate") — 9 celule se pierdeau doar pe diferența de caz.
        return mb_strtolower(strtr($text, ['ş' => 'ș', 'ţ' => 'ț', 'Ş' => 'Ș', 'Ţ' => 'Ț']));
    }
}
