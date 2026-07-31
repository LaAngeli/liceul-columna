<?php

namespace App\Console\Commands;

use App\Enums\ScheduleType;
use App\Enums\UserRole;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

/**
 * Orare demo pentru clasele „[DEMO]", create pe LANȚUL REAL, nu injectate în tabele.
 *
 * Cerința beneficiarului (01.08.2026): datele demo trebuie să parcurgă tot drumul — de la entitatea
 * RESPONSABILĂ de creare, prin creare și publicare, până la livrarea către elev și urmărirea de
 * către părinte. Comanda respectă exact acel drum:
 *
 *   1. ADMINISTRATORUL OPERAȚIONAL e autorul. Nu declarativ: comanda îi VERIFICĂ dreptul
 *      (`canManageSchedules()`, §3.2 „publică orarul") și refuză să scrie dacă nu-l are — deci
 *      demonstrația nu poate ocoli tăcut regula pe care o ilustrează.
 *   2. CREAREA trece prin model (nu query builder): observers + invalidarea cache-ului public +
 *      JURNALIZARE. Auditul e oprit implicit în consolă (`audit.console`), deci îl pornim pe durata
 *      rulării — altfel „cine a creat orarul" ar rămâne o afirmație nedovedită în jurnal.
 *   3. PUBLICAREA (`is_public`) + legarea de clasă e ce face orarul vizibil familiei.
 *   4. LIVRAREA: rulăm importatorul REAL ({@see ImportLessonsFromSchedules}) care derivă orarul
 *      STRUCTURAT (Lesson) din tabelul publicat. Nu scriem noi lecțiile: dacă parserul ar respinge
 *      ceva, vrem să se VADĂ aici, nu să fie mascat de o inserare directă. Lecțiile structurate sunt
 *      cele care dau numărul lecției la absențe și numitorul riscului de amânare (§2.1).
 *
 * Disciplinele fiecărei clase se aleg din nomenclator DUPĂ TREAPTA ei — singura variantă pe care
 * importatorul o acceptă, și singura corectă pedagogic (un orar de clasa I nu conține fizică).
 *
 * Marcaj „[DEMO]" în eticheta orarului → curățare curată cu `--remove` (sau prin
 * {@see PurgeDemoData}, care le prinde odată cu restul datelor demo). Idempotentă: re-rularea
 * rescrie aceleași orare, nu le multiplică.
 */
class SeedDemoSchedule extends Command
{
    private const MARKER = '[DEMO]';

    /** Contul care are, prin rol, obligația de a publica orarul (§3.2). */
    private const AUTHOR_EMAIL = 'operational@columna.test';

    /**
     * Orele lecțiilor — aceeași grilă pentru toate clasele demo, ca orarul sunetelor să aibă sens.
     *
     * @var list<string>
     */
    private const TIMES = [
        '08.00 – 08.45',
        '08.55 – 09.40',
        '09.50 – 10.35',
        '10.55 – 11.40',
        '11.50 – 12.35',
        '12.45 – 13.30',
    ];

    /** @var list<string> */
    private const DAYS = ['Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri'];

    /**
     * Disciplinele „de bază" — apar de mai multe ori pe săptămână, ca într-un orar real. Restul
     * completează o dată. Numele sunt cele din nomenclator; cele absente pe o treaptă se ignoră.
     *
     * @var list<string>
     */
    private const CORE = [
        'Limba și literatura română',
        'Matematică',
        'Limba străină 1 (engleza)',
    ];

    protected $signature = 'app:seed-demo-schedule
        {--remove : Șterge orarele demo (și lecțiile derivate din ele) în loc să le creeze}';

    protected $description = 'Creează (sau șterge cu --remove) orarele claselor [DEMO], pe lanțul real: administrator operațional → publicare → import → cabinet';

    public function handle(): int
    {
        $classes = SchoolClass::query()
            ->where('name', 'like', self::MARKER.'%')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        if ($classes->isEmpty()) {
            $this->warn('Nicio clasă '.self::MARKER.' — rulează întâi `php artisan app:seed-demo-zone`.');

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            return $this->remove($classes);
        }

        return $this->create($classes);
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     */
    private function create($classes): int
    {
        $author = User::query()->where('email', self::AUTHOR_EMAIL)->first();

        if ($author === null) {
            $this->warn('Contul `'.self::AUTHOR_EMAIL.'` lipsește — rulează `db:seed --class=DemoRoleAccountsSeeder`.');

            return self::FAILURE;
        }

        // Dreptul se VERIFICĂ, nu se presupune: dacă rolul care publică orarul și-ar pierde
        // capabilitatea, demonstrația ar continua să „meargă" pe o regulă care nu mai există.
        if (! $author->canManageSchedules()) {
            $this->error(sprintf(
                'Contul %s (%s) NU are dreptul de a publica orare — demonstrația s-ar abate de la §3.2.',
                self::AUTHOR_EMAIL,
                $author->roles->pluck('name')->implode(', ') ?: 'fără rol',
            ));

            return self::FAILURE;
        }

        // Jurnalizarea e ce transformă „a creat administratorul operațional" din afirmație în urmă
        // verificabilă (Administrare → Jurnal de audit). Implicit e oprită în consolă.
        config(['audit.console' => true]);
        Auth::login($author);

        $this->info(sprintf('Autor: %s — %s', $author->name, UserRole::AdministratorOperational->label()));
        $this->newLine();

        $created = 0;

        foreach ($classes as $class) {
            $subjects = $this->subjectsForGrade((int) $class->grade_level);

            if ($subjects === []) {
                $this->warn(sprintf('%s: nicio disciplină în nomenclator pe treapta %d — sărită.', $this->classLabel($class), $class->grade_level));

                continue;
            }

            $lessonsPerDay = $class->grade_level <= 4 ? 4 : 6;

            // updateOrCreate pe (tip, clasă): re-rularea rescrie ACELAȘI orar, nu adaugă altul.
            Schedule::updateOrCreate(
                ['type' => ScheduleType::Lessons, 'school_class_id' => $class->id],
                [
                    'label' => self::MARKER.' Clasa '.$this->classLabel($class),
                    'headers' => array_merge([''], self::DAYS),
                    'rows' => $this->buildRows($subjects, $lessonsPerDay, (int) $class->id, (int) $class->grade_level),
                    'position' => 100 + $created,
                    // Publicarea e pasul care îl face vizibil familiei.
                    'is_public' => true,
                ],
            );

            $created++;
        }

        Auth::logout();

        $this->info($created.' orare publicate.');
        $this->newLine();

        // Livrarea: orarul STRUCTURAT se derivă cu parserul REAL, ca demonstrația să treacă prin
        // exact codul care rulează în producție. `--force` rescrie ce am scris tot noi, iar `--only`
        // ține rescrierea ÎN zona demo: fără el, popularea de test ar regenera și orarul structurat
        // al claselor reale (inclusiv eventuale intrări manuale din panou).
        $this->line('Derivez orarul structurat (lecții) cu importatorul real…');
        Artisan::call('app:import-lessons', ['--force' => true, '--only' => self::MARKER], $this->getOutput());

        $this->newLine();
        $this->info('Gata. Orarul e vizibil în cabinet: „Orar" → Programul zilei / Orarul săptămânal.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     */
    private function remove($classes): int
    {
        $schedules = Schedule::query()
            ->where('type', ScheduleType::Lessons)
            ->where('label', 'like', self::MARKER.'%')
            ->get();

        // Lecțiile derivate NU poartă marcaj textual (n-au câmp de text) — se identifică prin clasa
        // demo din care provin, exact ca restul datelor zonei.
        $lessons = Lesson::query()->whereIn('school_class_id', $classes->pluck('id'))->count();
        Lesson::query()->whereIn('school_class_id', $classes->pluck('id'))->delete();

        foreach ($schedules as $schedule) {
            $schedule->delete();
        }

        $this->info(sprintf('%d orare demo și %d lecții derivate șterse.', $schedules->count(), $lessons));

        return self::SUCCESS;
    }

    private function classLabel(SchoolClass $class): string
    {
        return trim(str_replace(self::MARKER, '', $class->name).' '.($class->section ?? ''));
    }

    /**
     * Disciplinele nomenclatorului valabile pe treapta dată, cele „de bază" întâi.
     *
     * @return list<string>
     */
    private function subjectsForGrade(int $grade): array
    {
        $names = Subject::query()
            ->where(fn ($q) => $q->whereNull('min_grade')->orWhere('min_grade', '<=', $grade))
            ->where(fn ($q) => $q->whereNull('max_grade')->orWhere('max_grade', '>=', $grade))
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();

        // Opționalele n-au loc într-un orar demo: importatorul nu poate distinge „Limba engleză
        // (opț)" de ora obligatorie, iar o alegere greșită ar falsifica numărul de lecții.
        $names = array_values(array_filter($names, fn (string $name): bool => ! str_contains($name, '(opț)')));

        $core = array_values(array_intersect(self::CORE, $names));
        $rest = array_values(array_diff($names, $core));

        return [...$core, ...$rest];
    }

    /**
     * Săptămâna, ca tabel: un rând per lecție (eticheta pe care o caută importatorul: „Lecția N …")
     * + un rând final de program prelungit la primare — inclus DELIBERAT, ca demonstrația să arate
     * că importatorul sare rândurile care nu sunt lecții.
     *
     * Distribuția e determinISTĂ (rotație pornită din id-ul clasei), nu aleatoare: re-rularea
     * produce același orar, deci capturile și testele rămân valabile.
     *
     * @param  list<string>  $subjects
     * @return list<list<string>>
     */
    private function buildRows(array $subjects, int $lessonsPerDay, int $classId, int $grade): array
    {
        // Disciplinele de bază intră de două ori în „roată" → revin de ~2× pe săptămână.
        $wheel = [];

        foreach ($subjects as $index => $name) {
            $wheel[] = $name;

            if (in_array($name, self::CORE, true)) {
                $wheel[] = $name;
            }

            unset($index);
        }

        $size = count($wheel);

        // Construim pe ZILE, nu rând cu rând. Parcurgerea liniară a roții alinia disciplinele pe
        // orizontală (toate orele de educație ajungeau în lecția 1, disciplinele de bază în lecția
        // 2-3) — un orar cu benzi, care nu seamănă cu unul real. Fiecare zi pornește din alt punct
        // al roții și nu repetă o disciplină în aceeași zi.
        $byDay = [];

        foreach (array_keys(self::DAYS) as $dayIndex) {
            $picked = [];
            $position = ($classId * 3 + $dayIndex * 5) % $size;

            for ($step = 0; $step < $size && count($picked) < $lessonsPerDay; $step++) {
                $name = $wheel[($position + $step) % $size];

                if (! in_array($name, $picked, true)) {
                    $picked[] = $name;
                }
            }

            // Nomenclator mai sărac decât ziua (treaptă cu puține discipline): completăm ciclic,
            // acceptând repetiția — mai bine o oră repetată decât o zi cu goluri.
            while (count($picked) < $lessonsPerDay) {
                $picked[] = $wheel[count($picked) % $size];
            }

            $byDay[$dayIndex] = $picked;
        }

        $rows = [];

        for ($lesson = 1; $lesson <= $lessonsPerDay; $lesson++) {
            $row = ['Lecția '.$lesson.' '.self::TIMES[$lesson - 1]];

            foreach (array_keys(self::DAYS) as $dayIndex) {
                // Sala doar la unele ore, ca în orarele reale (importatorul o extrage din „(s. NN)").
                $room = ($lesson + $dayIndex) % 3 === 0 ? ' (s. '.(10 + (($classId + $dayIndex) % 15)).')' : '';

                $row[] = $byDay[$dayIndex][$lesson - 1].$room;
            }

            $rows[] = $row;
        }

        if ($grade <= 4) {
            $rows[] = array_merge(['13.30 – 14.30'], array_fill(0, count(self::DAYS), 'Plimbări, jocuri'));
        }

        return $rows;
    }
}
