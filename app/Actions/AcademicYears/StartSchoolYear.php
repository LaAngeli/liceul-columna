<?php

namespace App\Actions\AcademicYears;

use App\Actions\Enrollments\GraduateClasses;
use App\Actions\Enrollments\PromoteYear;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * TRECEREA ÎN ANUL NOU, ca o singură operațiune (cerința beneficiarului, 2026-08-03: „prea mulți
 * pași, împrăștiați — se pot omite").
 *
 * Pașii existau toți, dar în patru secțiuni diferite și cu o ordine care conta: anul → semestrele
 * → structura (clase + alocări) → absolvirea promoției → promovarea elevilor. Cine sărea unul nu
 * primea nicio eroare, ci o școală pe jumătate configurată, descoperită săptămâni mai târziu.
 *
 * Aici doar se COMPUN acțiunile care fac treaba — nu se rescrie niciuna: {@see OpenAcademicYear}
 * (structura), {@see GraduateClasses} (promoția care termină), {@see PromoteYear} (elevii). Tot ce
 * adaugă acest orchestrator e ORDINEA corectă, o singură tranzacție și un raport unitar.
 *
 * ORDINEA, deliberată: absolvirea ÎNAINTE de promovare (promoția iese, apoi structura urcă), iar
 * elevii DUPĂ ce clasele-țintă există. Totul e idempotent: ce există deja se sare, deci reluarea
 * după o întrerupere nu dublează nimic.
 */
class StartSchoolYear
{
    public function __construct(
        private readonly OpenAcademicYear $openYear,
        private readonly GraduateClasses $graduate,
        private readonly PromoteYear $promoteYear,
    ) {}

    /**
     * Ce se va întâmpla, fără să se scrie nimic — sursa previzualizării din ecran.
     *
     * @param  array{source_year_id?: int|null, target_year_id?: int|null, terms?: array<int, array<string, mixed>>, with_assignments?: bool, graduate?: bool, promote?: bool}  $input
     * @return array{classes: int, assignments: int, dropped: int, existing: int, terms: int, graduates: int, students: int, unmapped: array<int, string>, blocked: string|null}
     */
    public function plan(array $input): array
    {
        $source = $this->year($input['source_year_id'] ?? null);
        $target = $this->year($input['target_year_id'] ?? null);

        $empty = [
            'classes' => 0, 'assignments' => 0, 'dropped' => 0, 'existing' => 0,
            'terms' => count($input['terms'] ?? []), 'graduates' => 0, 'students' => 0,
            'unmapped' => [], 'blocked' => null,
        ];

        if ($source === null) {
            return [...$empty, 'blocked' => 'no_source'];
        }

        // Anul-țintă poate să nu existe încă (se creează la execuție): atunci structura se
        // previzualizează „ca și cum ar fi gol" — nimic nu e ocupat acolo.
        if ($target === null) {
            // Un an NESALVAT = „an gol": planul iese ca și cum nimic n-ar fi ocupat acolo,
            // ceea ce e exact adevărat pentru anul care abia urmează să fie creat.
            $structure = $this->openYear->plan(new AcademicYear, $source);
        } else {
            $structure = $this->openYear->plan($target, $source);

            if ($structure['blocked'] !== null) {
                return [...$empty, 'blocked' => $structure['blocked']];
            }
        }

        $promoted = $structure['promoted'];

        $students = 0;

        if (($input['promote'] ?? true) && $target !== null) {
            foreach ($this->promoteYear->plan((int) $source->getKey(), (int) $target->getKey()) as $row) {
                if ($row['target'] !== null) {
                    $students += $row['students'];
                }
            }
        } elseif ($input['promote'] ?? true) {
            // Fără anul-țintă încă, elevii care vor urca sunt cei activi ai claselor care se
            // promovează — clasele-țintă se nasc în aceeași operațiune.
            $students = $this->activeStudentsOf($promoted);
        }

        return [
            'classes' => count($promoted),
            'assignments' => ($input['with_assignments'] ?? true)
                ? array_sum(array_map(fn (array $row): int => $row['assignments'], $promoted))
                : 0,
            'dropped' => array_sum(array_map(fn (array $row): int => $row['dropped'], $promoted)),
            'existing' => count($structure['existing']),
            'terms' => count($input['terms'] ?? []),
            'graduates' => ($input['graduate'] ?? true) ? $this->graduate->pendingCount($source) : 0,
            'students' => $students,
            'unmapped' => array_map(
                fn (SchoolClass $class): string => trim($class->name.' '.($class->section ?? '')),
                $structure['graduating'],
            ),
            'blocked' => null,
        ];
    }

    /**
     * Execută toată trecerea. O singură tranzacție: ori anul e deschis complet, ori nu se schimbă
     * nimic — starea intermediară („anul există, dar fără clase") e exact ce producea uitarea unui pas.
     *
     * @param  array{source_year_id?: int|null, target_year_id?: int|null, year?: array<string, mixed>, terms?: array<int, array<string, mixed>>, with_assignments?: bool, graduate?: bool, promote?: bool}  $input
     * @return array{year: AcademicYear|null, terms: int, classes: int, assignments: int, graduates: int, students: int, skipped_students: int, existing: int, blocked: string|null}
     */
    public function handle(array $input): array
    {
        $source = $this->year($input['source_year_id'] ?? null);

        $result = [
            'year' => null, 'terms' => 0, 'classes' => 0, 'assignments' => 0,
            'graduates' => 0, 'students' => 0, 'skipped_students' => 0, 'existing' => 0,
            'blocked' => $source === null ? 'no_source' : null,
        ];

        if ($source === null) {
            return $result;
        }

        return DB::transaction(function () use ($input, $source, $result): array {
            $target = $this->resolveTargetYear($input);

            if ($target === null) {
                return [...$result, 'blocked' => 'no_target'];
            }

            $result['year'] = $target;
            $result['terms'] = $this->createTerms($target, $input['terms'] ?? []);

            // 1. Promoția iese din registru (înainte ca structura nouă să existe).
            if ($input['graduate'] ?? true) {
                $result['graduates'] = $this->graduate->handle($source)['graduated'];
            }

            // 2. Structura urcă o treaptă.
            $structure = $this->openYear->handle($target, $source, $input['with_assignments'] ?? true);

            if ($structure['blocked'] !== null) {
                return [...$result, 'blocked' => $structure['blocked']];
            }

            $result['classes'] = $structure['classes'];
            $result['assignments'] = $structure['assignments'];
            $result['existing'] = $structure['existing'];

            // 3. Elevii intră în clasele care tocmai s-au născut.
            if ($input['promote'] ?? true) {
                $promotion = $this->promoteYear->handle((int) $source->getKey(), (int) $target->getKey());
                $result['students'] = $promotion['enrolled'];
                $result['skipped_students'] = $promotion['skipped'];
            }

            return $result;
        });
    }

    /**
     * Anul-țintă: cel ales, altfel unul NOU din datele formularului.
     *
     * @param  array<string, mixed>  $input
     */
    private function resolveTargetYear(array $input): ?AcademicYear
    {
        $existing = $this->year($input['target_year_id'] ?? null);

        if ($existing !== null) {
            return $existing;
        }

        /** @var array<string, mixed> $year */
        $year = $input['year'] ?? [];

        if (blank($year['name'] ?? null)) {
            return null;
        }

        return AcademicYear::query()->create([
            'name' => trim((string) $year['name']),
            'starts_on' => $year['starts_on'] ?? null,
            'ends_on' => $year['ends_on'] ?? null,
        ]);
    }

    /**
     * Semestrele anului: se creează doar cele care lipsesc (după număr), ca reluarea să nu dubleze.
     *
     * @param  array<int, array<string, mixed>>  $terms
     */
    private function createTerms(AcademicYear $year, array $terms): int
    {
        $existing = Term::query()
            ->where('academic_year_id', $year->getKey())
            ->pluck('number')
            ->map(fn ($number): int => (int) $number)
            ->all();

        $created = 0;

        foreach ($terms as $index => $term) {
            $number = (int) ($term['number'] ?? $index + 1);

            if (in_array($number, $existing, true) || blank($term['name'] ?? null)) {
                continue;
            }

            Term::query()->create([
                'academic_year_id' => $year->getKey(),
                'number' => $number,
                'name' => trim((string) $term['name']),
                'starts_on' => $term['starts_on'] ?? null,
                'ends_on' => $term['ends_on'] ?? null,
                // Flag-ul „curent" NU se pune de aici: îl stabilesc datele, prin
                // SyncCurrentTermFlag (comanda zilnică + acțiunea din Semestre).
                'is_current' => false,
            ]);

            $existing[] = $number;
            $created++;
        }

        return $created;
    }

    /**
     * Semestrele PROPUSE pentru un an: aceeași structură ca a anului precedent, mutată cu un an.
     * Fără un an precedent cu semestre, o schemă simplă 1 sept – 31 dec / 15 ian – 31 mai.
     *
     * @return array<int, array{number: int, name: string, starts_on: string, ends_on: string}>
     */
    public function suggestTerms(?AcademicYear $previous, ?string $startsOn): array
    {
        $start = $startsOn !== null ? Carbon::parse($startsOn) : Carbon::parse(Carbon::now()->year.'-09-01');

        if ($previous !== null) {
            $terms = Term::query()
                ->where('academic_year_id', $previous->getKey())
                ->orderBy('number')
                ->get();

            if ($terms->isNotEmpty()) {
                return $terms->map(fn (Term $term): array => [
                    'number' => (int) $term->number,
                    'name' => (string) $term->name,
                    'starts_on' => $term->starts_on?->copy()->addYear()->toDateString() ?? $start->toDateString(),
                    'ends_on' => $term->ends_on?->copy()->addYear()->toDateString() ?? $start->copy()->addMonths(4)->toDateString(),
                ])->all();
            }
        }

        return [
            [
                'number' => 1,
                'name' => (string) __('panel.pages.year_transition.term_one'),
                'starts_on' => $start->toDateString(),
                'ends_on' => $start->copy()->setMonth(12)->setDay(31)->toDateString(),
            ],
            [
                'number' => 2,
                'name' => (string) __('panel.pages.year_transition.term_two'),
                'starts_on' => $start->copy()->addYear()->setMonth(1)->setDay(15)->toDateString(),
                'ends_on' => $start->copy()->addYear()->setMonth(5)->setDay(31)->toDateString(),
            ],
        ];
    }

    /**
     * Numele PROPUS al anului următor: „2026–2027" din „2025–2026". Dacă numele precedentului nu
     * are forma asta, nu se ghicește nimic.
     */
    public function suggestName(?AcademicYear $previous): ?string
    {
        if ($previous === null || ! preg_match('/(\d{4})\D+(\d{4})/', (string) $previous->name, $matches)) {
            return null;
        }

        return ((int) $matches[1] + 1).'–'.((int) $matches[2] + 1);
    }

    /** @param  array<int, array{source: SchoolClass, grade: int, assignments: int, dropped: int}>  $promoted */
    private function activeStudentsOf(array $promoted): int
    {
        $classIds = array_map(fn (array $row): int => (int) $row['source']->getKey(), $promoted);

        if ($classIds === []) {
            return 0;
        }

        return Enrollment::query()
            ->whereIn('school_class_id', $classIds)
            ->whereNull('left_on')
            ->count();
    }

    private function year(int|string|null $id): ?AcademicYear
    {
        return blank($id) ? null : AcademicYear::query()->find((int) $id);
    }
}
