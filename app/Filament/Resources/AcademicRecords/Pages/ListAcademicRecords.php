<?php

namespace App\Filament\Resources\AcademicRecords\Pages;

use App\Enums\AcademicRecordPeriod;
use App\Enums\SchoolCycle;
use App\Filament\Resources\AcademicRecords\AcademicRecordResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\AcademicRecord;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Support\ContentTranslator;
use App\Support\GradeLevels;
use App\Support\Grades;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Secțiunea „Foaie matricolă" = navigator cu carduri (2026-07-16, cerința beneficiarului: același
 * principiu ca la Elevi/Discipline/Clase + informație COMPLETĂ la vizualizare). Lista plată de
 * înregistrări nu mai e interfața: clase → elevii clasei → FOAIA MATRICOLĂ a elevului, ca
 * document — pe trepte (cifre romane + ciclul), disciplină × Sem. I / Sem. II / Media anuală,
 * calificativul unde nu există notă numerică, plus media anuală a disciplinelor afișate.
 *
 * Perimetrul rămâne cel al resursei (LegacyArchivesScopingTest): profesorul vede doar disciplinele
 * pe care le predă, dirigintele foaia completă a clasei lui, administrația tot. Anul școlar al
 * treptelor legacy NU se afișează — arhiva importată nu are înmatriculări istorice din care anul
 * să fie derivat onest (doar anul curent există în DB).
 */
class ListAcademicRecords extends ListRecords
{
    protected static string $resource = AcademicRecordResource::class;

    protected string $view = 'filament.catalog.academic-records-navigator';

    /** Clasa deschisă (id „dorit" din URL, validat la citire prin clasele vizibile). */
    #[Url(as: 'clasa', except: null)]
    public ?string $classParam = null;

    /** Elevul deschis (validat prin perimetrul StudentResource — ca la Elevi). */
    #[Url(as: 'elev', except: null)]
    public ?string $studentParam = null;

    /** Arhiva (toți elevii cu foaie matricolă, căutabili) — doar administrația. */
    #[Url(as: 'arhiva', except: null)]
    public ?string $archiveMode = null;

    /** Căutarea din arhivă (după nume). */
    public string $archiveSearch = '';

    private SchoolClass|false|null $activeClassMemo = null;

    private Student|false|null $activeStudentMemo = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('archive')
                ->label(__('panel.catalog_nav.students_archive'))
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->visible(fn (): bool => $this->canUseArchive()
                    && ! $this->isArchiveMode()
                    && $this->activeStudent() === null
                    && $this->activeClass() === null)
                ->action(function (): void {
                    $this->archiveMode = '1';
                }),
        ];
    }

    // ── Stare + navigare ────────────────────────────────────────────────────────────────────

    public function isArchiveMode(): bool
    {
        return $this->archiveMode === '1' && $this->canUseArchive();
    }

    public function openClass(int|string $id): void
    {
        $id = (int) $id;

        if ($this->classAccessQuery()->whereKey($id)->exists()) {
            $this->classParam = (string) $id;
            $this->activeClassMemo = null;
        }
    }

    public function openStudent(int|string $id): void
    {
        $id = (int) $id;

        if ($this->studentAccessQuery()->whereKey($id)->exists()) {
            $this->studentParam = (string) $id;
            $this->activeStudentMemo = null;
        }
    }

    public function leaveStudent(): void
    {
        $this->studentParam = null;
        $this->activeStudentMemo = null;
    }

    public function leaveClass(): void
    {
        $this->classParam = null;
        $this->activeClassMemo = null;
    }

    public function leaveArchive(): void
    {
        $this->archiveMode = null;
        $this->archiveSearch = '';
    }

    public function activeClass(): ?SchoolClass
    {
        if ($this->activeClassMemo === null) {
            $this->activeClassMemo = ($this->classParam !== null && ctype_digit($this->classParam))
                ? ($this->classAccessQuery()->with('homeroomTeacher')->whereKey((int) $this->classParam)->first() ?? false)
                : false;
        }

        return $this->activeClassMemo === false ? null : $this->activeClassMemo;
    }

    public function activeStudent(): ?Student
    {
        if ($this->activeStudentMemo === null) {
            $this->activeStudentMemo = ($this->studentParam !== null && ctype_digit($this->studentParam))
                ? ($this->studentAccessQuery()->whereKey((int) $this->studentParam)->first() ?? false)
                : false;
        }

        return $this->activeStudentMemo === false ? null : $this->activeStudentMemo;
    }

    // ── Carduri: clase → elevi → foaia matricolă ────────────────────────────────────────────

    /**
     * Cardurile claselor: profesorul/dirigintele — clasele lui; administrația — anul curent
     * (arhiva de elevi fără clasă curentă are vederea ei).
     *
     * @return array<int, array{id: int, title: string, subtitle: string|null, stats: array<int, string>}>
     */
    public function classCards(): array
    {
        $query = $this->classAccessQuery()->with('homeroomTeacher');

        $user = auth('web')->user();

        if ($user?->isAdministrator() ?? false) {
            $currentYearId = Term::query()->where('is_current', true)->value('academic_year_id');

            if ($currentYearId !== null) {
                $query->where('academic_year_id', $currentYearId);
            }
        }

        $classes = $query
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section')
            ->get();

        $enrollments = Enrollment::query()
            ->toBase()
            ->selectRaw('school_class_id, COUNT(*) AS aggregate')
            ->whereIn('school_class_id', $classes->pluck('id')->all())
            ->groupBy('school_class_id')
            ->get()
            ->keyBy('school_class_id');

        $cards = [];

        foreach ($classes as $class) {
            $students = (int) ($enrollments->get($class->id)->aggregate ?? 0);

            $cards[] = [
                'id' => (int) $class->id,
                'title' => trim($class->name.' '.($class->section ?? '')),
                'subtitle' => $class->homeroomTeacher?->full_name,
                'stats' => [
                    (string) trans_choice('panel.catalog_nav.students', $students, ['count' => $students]),
                ],
            ];
        }

        return $cards;
    }

    /**
     * Cardurile elevilor clasei active, cu rezumatul foii VIZIBILE: treptele acoperite +
     * numărul de înregistrări (profesorul vede doar disciplinele lui — și numărătoarea la fel).
     *
     * @return array<int, array{id: int, title: string, subtitle: string|null, stats: array<int, string>}>
     */
    public function studentCards(): array
    {
        $class = $this->activeClass();

        if ($class === null) {
            return [];
        }

        $students = Student::query()
            ->whereHas('enrollments', fn (Builder $q) => $q->where('school_class_id', $class->getKey()))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return $this->studentCardsFor($students);
    }

    /**
     * Arhiva (doar administrația): TOȚI elevii cu înregistrări în foaia matricolă — inclusiv cei
     * fără clasă curentă (plecați) — căutabili după nume. Înlocuiește căutarea din vechiul tabel.
     *
     * @return array<int, array{id: int, title: string, subtitle: string|null, stats: array<int, string>}>
     */
    public function archiveStudentCards(): array
    {
        if (! $this->isArchiveMode()) {
            return [];
        }

        $search = trim($this->archiveSearch);

        $students = Student::query()
            ->whereHas('academicRecords')
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where(function (Builder $inner) use ($search) {
                    $inner->where('last_name', 'like', '%'.$search.'%')
                        ->orWhere('first_name', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(48)
            ->get();

        return $this->studentCardsFor($students);
    }

    /**
     * Foaia matricolă a elevului activ, ca document: trepte DESC (roman + ciclu), rânduri pe
     * disciplină (ordinea din documentele oficiale: report_order) cu Sem. I / Sem. II / Media
     * anuală — valoarea numerică sau calificativul — plus media anuală a disciplinelor afișate.
     *
     * @return array<int, array{grade_level: int, roman: string, cycle: string, average: string|null, rows: array<int, array{subject: string, sem1: string|null, sem2: string|null, annual: string|null}>}>
     */
    public function transcriptLevels(): array
    {
        $student = $this->activeStudent();

        if ($student === null) {
            return [];
        }

        $records = $this->scopedRecordQuery()
            ->where('student_id', $student->getKey())
            ->with('subject')
            ->get();

        $levels = [];

        foreach ($records->groupBy('grade_level') as $gradeLevel => $group) {
            $rows = [];

            // Pe subject_id (nu pe nume) — discipline omonime pe aceeași treaptă nu se contopesc.
            foreach ($group->groupBy('subject_id') as $items) {
                $byPeriod = [];

                foreach ($items as $record) {
                    $byPeriod[$record->period->value] = $record->value !== null
                        ? self::formatAverage((float) $record->value)
                        : ($record->calificativ ?: null);
                }

                // Relația subject e withTrashed pe FK obligatoriu — disciplina există întotdeauna,
                // chiar și arhivată (foaia matricolă nu rămâne cu părinți null).
                $subject = $items->first()->subject;

                $rows[] = [
                    'subject' => ContentTranslator::subject((string) $subject->name),
                    'order' => [$subject->report_order ?? PHP_INT_MAX, (string) $subject->name],
                    'sem1' => $byPeriod[AcademicRecordPeriod::SemesterI->value] ?? null,
                    'sem2' => $byPeriod[AcademicRecordPeriod::SemesterII->value] ?? null,
                    'annual' => $byPeriod[AcademicRecordPeriod::Annual->value] ?? null,
                ];
            }

            usort($rows, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

            $rows = array_map(function (array $row): array {
                unset($row['order']);

                return $row;
            }, $rows);

            // Media anuală a disciplinelor AFIȘATE — doar mediile anuale numerice (calificativele
            // nu intră); trunchiere la sutimi, fără rotunjire (convenția §2.4).
            $annuals = $group
                ->filter(fn ($record): bool => $record->period === AcademicRecordPeriod::Annual && $record->value !== null)
                ->map(fn ($record): float => (float) $record->value);

            $levels[] = [
                'grade_level' => (int) $gradeLevel,
                'roman' => GradeLevels::roman((int) $gradeLevel),
                'cycle' => SchoolCycle::fromGradeLevel((int) $gradeLevel)->label(),
                'average' => $annuals->isNotEmpty()
                    ? self::formatAverage(Grades::truncate2((float) $annuals->avg()))
                    : null,
                'rows' => $rows,
            ];
        }

        usort($levels, fn (array $a, array $b): int => $b['grade_level'] <=> $a['grade_level']);

        return $levels;
    }

    /**
     * Rezumatul de sub numele elevului: clasa curentă + treptele acoperite + nr. de înregistrări
     * (toate în perimetrul vizibil al privitorului).
     *
     * @return array<int, string>
     */
    public function transcriptSummary(): array
    {
        $student = $this->activeStudent();

        if ($student === null) {
            return [];
        }

        $meta = [];

        $class = $student->currentSchoolClass();

        if ($class !== null) {
            $meta[] = (string) __('panel.fields.class').': '.trim($class->name.' '.($class->section ?? ''));
        }

        $stats = AcademicRecordResource::getEloquentQuery()
            ->where('student_id', $student->getKey())
            ->toBase()
            ->selectRaw('COUNT(*) AS total, MIN(grade_level) AS mn, MAX(grade_level) AS mx')
            ->first();

        if ($stats !== null && (int) $stats->total > 0) {
            $meta[] = (string) __('panel.catalog_nav.transcript_span', [
                'span' => GradeLevels::span((int) $stats->mn, (int) $stats->mx),
            ]);
            $meta[] = (string) trans_choice('panel.catalog_nav.records', (int) $stats->total, ['count' => (int) $stats->total]);
        }

        return $meta;
    }

    public function recordsHint(): string
    {
        $hint = (string) __('panel.catalog_nav.records_hint');

        if (! (auth('web')->user()?->isAdministrator() ?? false)) {
            $hint .= ' '.__('panel.catalog_nav.records_scope_note');
        }

        return $hint;
    }

    // ── Interogări de acces (perimetrele rămân definite în resurse) ─────────────────────────

    /** @return Builder<SchoolClass> */
    private function classAccessQuery(): Builder
    {
        $query = SchoolClass::query();
        $user = auth('web')->user();

        if ($user && ! $user->isAdministrator()) {
            $query->whereKey($user->teacher?->visibleSchoolClassIds() ?? []);
        }

        return $query;
    }

    /** @return Builder<Student> perimetrul elevilor = cel din secțiunea Elevi (StudentResource) */
    private function studentAccessQuery(): Builder
    {
        return Student::query()->whereIn(
            'id',
            StudentResource::getEloquentQuery()->select('id'),
        );
    }

    /**
     * Interogarea CONCRETĂ (Builder<AcademicRecord>) restrânsă la perimetrul resursei — scoping-ul
     * pe rol rămâne definit o singură dată, în AcademicRecordResource::getEloquentQuery().
     *
     * @return Builder<AcademicRecord>
     */
    private function scopedRecordQuery(): Builder
    {
        return AcademicRecord::query()->whereIn(
            'id',
            AcademicRecordResource::getEloquentQuery()->select('id'),
        );
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return array<int, array{id: int, title: string, subtitle: string|null, stats: array<int, string>}>
     */
    private function studentCardsFor(Collection $students): array
    {
        if ($students->isEmpty()) {
            return [];
        }

        $recordStats = AcademicRecordResource::getEloquentQuery()
            ->whereIn('student_id', $students->pluck('id')->all())
            ->toBase()
            ->selectRaw('student_id, COUNT(*) AS total, MIN(grade_level) AS mn, MAX(grade_level) AS mx')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $cards = [];

        foreach ($students as $student) {
            $stats = $recordStats->get($student->id);

            $cards[] = [
                'id' => (int) $student->id,
                'title' => (string) $student->full_name,
                'subtitle' => $student->register_number !== null
                    ? (string) __('panel.fields.register_number').': '.$student->register_number
                    : null,
                'stats' => $stats !== null
                    ? [
                        (string) __('panel.catalog_nav.transcript_span', [
                            'span' => GradeLevels::span((int) $stats->mn, (int) $stats->mx),
                        ]),
                        (string) trans_choice('panel.catalog_nav.records', (int) $stats->total, ['count' => (int) $stats->total]),
                    ]
                    : [(string) __('panel.catalog_nav.no_records')],
            ];
        }

        return $cards;
    }

    private function canUseArchive(): bool
    {
        return auth('web')->user()?->isAdministrator() ?? false;
    }

    /** Afișare unitară a mediilor: două zecimale, cu virgulă (stilul documentelor școlare). */
    private static function formatAverage(float $value): string
    {
        return number_format($value, 2, ',', '');
    }
}
