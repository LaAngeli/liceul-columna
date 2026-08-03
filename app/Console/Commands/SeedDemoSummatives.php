<?php

namespace App\Console\Commands;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SummativeDesignation;
use App\Models\Term;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Date demo pentru „Discipline cu sumativă" — secțiunea era goală, deci nu se putea aprecia nici
 * afișarea, nici comportamentul filtrelor (cerința beneficiarului, 2026-08-03).
 *
 * Designările urmează practica reală, nu un umplut aleatoriu: româna și matematica peste tot,
 * istoria în plus la clasa a IX-a (an de examen), iar la liceu disciplina de PROFIL — secția „R"
 * (real) primește fizică/chimie, secția „U" (uman) istorie. Primarul nu apare deloc: acolo nu
 * există notă sumativă, iar formularul nici nu oferă clasele I–IV.
 *
 * Câteva designări rămân DELIBERAT fără referință de ordin: coloana „Referință ordin" trebuie
 * văzută și în starea ei goală (placeholder), altfel UX-ul se evaluează doar pe cazul fericit.
 *
 * REVERSIBIL exact: id-urile create intră într-un manifest (`storage/app/demo/summatives.json`),
 * iar `--remove` șterge fix acele rânduri — designările introduse între timp de școală rămân.
 */
class SeedDemoSummatives extends Command
{
    protected $signature = 'app:seed-demo-summatives
        {--remove : Șterge designările create de această comandă}';

    protected $description = 'Populează „Discipline cu sumativă" cu designări demo realiste';

    private const MANIFEST = 'demo/summatives.json';

    private const ORDER_GIMNAZIU = 'Ordin nr. 12 din 01.09.2025';

    private const ORDER_LICEU = 'Ordin nr. 13 din 01.09.2025';

    public function handle(): int
    {
        if ($this->option('remove')) {
            return $this->remove();
        }

        $yearId = Term::query()->where('is_current', true)->value('academic_year_id');

        if ($yearId === null) {
            $this->components->error('Nu există un semestru curent — anul de lucru nu poate fi stabilit.');

            return self::FAILURE;
        }

        $subjects = $this->subjects();

        if ($subjects === []) {
            $this->components->error('Nomenclatorul nu are disciplinele așteptate (română, matematică, istorie, fizică, chimie).');

            return self::FAILURE;
        }

        $classes = SchoolClass::query()
            ->where('academic_year_id', $yearId)
            ->where('grade_level', '>=', 5)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section')
            ->get();

        if ($classes->isEmpty()) {
            $this->components->warn('Anul curent nu are clase de gimnaziu sau liceu.');

            return self::SUCCESS;
        }

        $created = [];
        $skipped = 0;

        DB::transaction(function () use ($classes, $subjects, &$created, &$skipped): void {
            foreach ($classes as $class) {
                foreach ($this->designationsFor($class, $subjects) as [$subjectId, $order]) {
                    // Unic pe (disciplină × clasă) — aceeași regulă ca în formular. O designare
                    // existentă (a școlii sau dintr-o rulare anterioară) nu se atinge.
                    $exists = SummativeDesignation::query()
                        ->where('school_class_id', $class->getKey())
                        ->where('subject_id', $subjectId)
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    $designation = SummativeDesignation::query()->create([
                        'school_class_id' => $class->getKey(),
                        'subject_id' => $subjectId,
                        'order_reference' => $order,
                    ]);

                    $created[] = (int) $designation->getKey();
                }
            }
        });

        $this->rememberIds($created);

        $this->components->info(count($created).' designări create'.($skipped > 0 ? ", {$skipped} existau deja" : '').'.');
        $this->table(
            ['Ciclu', 'Clase', 'Discipline designate'],
            [
                ['Gimnaziu V–VIII', (string) $classes->whereBetween('grade_level', [5, 8])->count(), 'Română, Matematică'],
                ['Gimnaziu IX', (string) $classes->where('grade_level', 9)->count(), 'Română, Matematică, Istoria'],
                ['Liceu X–XII (R)', (string) $classes->where('grade_level', '>=', 10)->filter(fn (SchoolClass $c): bool => $this->isReal($c))->count(), 'Română, Matematică, Fizică/Chimie'],
                ['Liceu X–XII (U)', (string) $classes->where('grade_level', '>=', 10)->reject(fn (SchoolClass $c): bool => $this->isReal($c))->count(), 'Română, Matematică, Istoria'],
            ],
        );

        $this->line('  <fg=gray>Ștergere: php artisan app:seed-demo-summatives --remove</>');

        return self::SUCCESS;
    }

    /**
     * Ce se designează pentru o clasă: baza (română + matematică), istoria la clasa de examen și
     * disciplina de profil la liceu. Referința de ordin lipsește la unele — starea goală a coloanei
     * face parte din ce trebuie văzut.
     *
     * @param  array<string, int>  $subjects
     * @return array<int, array{0: int, 1: string|null}>
     */
    private function designationsFor(SchoolClass $class, array $subjects): array
    {
        $grade = (int) $class->grade_level;
        $liceu = $grade >= 10;
        $order = $liceu ? self::ORDER_LICEU : self::ORDER_GIMNAZIU;

        // Clasele „fără ordin la dosar": două treptre alese, ca placeholder-ul să apară în listă.
        $withoutOrder = in_array($grade, [7, 11], true);

        $rows = [
            [$subjects['romana'], $withoutOrder ? null : $order],
            [$subjects['matematica'], $withoutOrder ? null : $order],
        ];

        if ($grade === 9) {
            $rows[] = [$subjects['istorie'], $order];
        }

        if ($liceu) {
            // Profilul: real → fizică (a XII-a chimie, cum se practică la profilul real), uman → istorie.
            $rows[] = $this->isReal($class)
                ? [$grade === 12 ? $subjects['chimie'] : $subjects['fizica'], $order]
                : [$subjects['istorie'], $order];
        }

        return $rows;
    }

    /**
     * Profilul REAL, după litera secției: „R" la clasele reale ale școlii, iar la clasele demo
     * (litere A/B/C) alternăm, ca lista să nu fie uniformă.
     */
    private function isReal(SchoolClass $class): bool
    {
        $section = mb_strtoupper(trim((string) $class->section));

        return match ($section) {
            'R' => true,
            'U' => false,
            default => in_array($section, ['A', 'C'], true),
        };
    }

    /**
     * Disciplinele designabile, luate din nomenclator după numele canonic ȘI după treaptă: există
     * câte o fișă per ciclu pentru aceleași denumiri, iar cea de primar n-are ce căuta aici.
     *
     * @return array<string, int>
     */
    private function subjects(): array
    {
        $find = function (string $name): ?int {
            $id = Subject::query()
                ->where('name', $name)
                ->where(fn ($query) => $query->whereNull('max_grade')->orWhere('max_grade', '>=', 9))
                ->orderBy('min_grade')
                ->value('id');

            return $id === null ? null : (int) $id;
        };

        $subjects = array_filter([
            'romana' => $find('Limba și literatura română'),
            'matematica' => $find('Matematică'),
            'istorie' => $find('Istoria românilor și universală'),
            'fizica' => $find('Fizică'),
            'chimie' => $find('Chimie'),
        ], fn (?int $id): bool => $id !== null);

        return count($subjects) === 5 ? $subjects : [];
    }

    /** @param  array<int, int>  $ids */
    private function rememberIds(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        File::ensureDirectoryExists(storage_path('app/demo'));

        $known = $this->manifestIds();

        File::put(
            storage_path('app/'.self::MANIFEST),
            json_encode(['designations' => array_values(array_unique([...$known, ...$ids]))], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<int, int> */
    private function manifestIds(): array
    {
        $path = storage_path('app/'.self::MANIFEST);

        if (! File::exists($path)) {
            return [];
        }

        /** @var array{designations?: array<int, int>} $data */
        $data = json_decode((string) File::get($path), true) ?: [];

        return array_map(intval(...), $data['designations'] ?? []);
    }

    private function remove(): int
    {
        $ids = $this->manifestIds();

        if ($ids === []) {
            $this->components->warn('Nu există manifest de designări demo — nimic de șters.');

            return self::SUCCESS;
        }

        $deleted = SummativeDesignation::query()->whereKey($ids)->delete();

        File::delete(storage_path('app/'.self::MANIFEST));

        $this->components->info($deleted.' designări demo șterse.');

        return self::SUCCESS;
    }
}
