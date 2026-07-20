<?php

namespace App\Filament\Resources\ExamCommissions\Pages;

use App\Filament\Concerns\HasYearPillsTable;
use App\Filament\Resources\ExamCommissions\ExamCommissionResource;
use App\Models\CorigentaExam;
use App\Models\ExamCommission;
use App\Models\Teacher;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Comisiile de examen ca NAVIGATOR DE ACOPERIRE, nu listă plată: secțiunea răspunde întrebării
 * operaționale „pentru corigențele anului, avem comisii complete pe toate disciplinele?".
 * Pastile pe ani → barometrul acoperirii (discipline cu examene vs. acoperite) → coada
 * „de acoperit" (disciplină cu examene, fără comisie — cu creare pre-completată) → cardurile
 * comisiilor cu componența NOMINALĂ și stările care cer atenție (fără președinte, sub 3 membri).
 *
 * Regulamentar, o comisie funcțională = președinte + cel puțin 2 membri (3 persoane).
 */
class ListExamCommissions extends ListRecords
{
    use HasYearPillsTable;

    /** Pragul de componență: președinte + 2 membri. */
    public const MIN_PERSONS = 3;

    protected static string $resource = ExamCommissionResource::class;

    protected string $view = 'filament.configuration.exam-commissions-navigator';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->url(fn (): string => $this->createUrl(null)),
        ];
    }

    public function configHint(): string
    {
        return (string) __('panel.config_nav.commissions_hint');
    }

    protected function yearRecordCounts(): Collection
    {
        return ExamCommission::query()
            ->toBase()
            ->selectRaw('academic_year_id, COUNT(*) AS aggregate')
            ->groupBy('academic_year_id')
            ->pluck('aggregate', 'academic_year_id')
            ->map(fn ($count): int => (int) $count);
    }

    protected function constrainToYear(Builder $query, int $yearId): void
    {
        $query->where('academic_year_id', $yearId);
    }

    /**
     * Barometrul + coada de acoperit, pe anul activ: disciplinele care AU examene de corigență
     * (prin semestrele anului), față în față cu disciplinele care au comisie.
     *
     * @return array{subjects_with_exams: int, covered: int, uncovered: list<array{id: int, name: string, exams: int, create_url: string}>, unassigned_exams: int}
     */
    public function coverage(): array
    {
        $yearId = $this->activeYearId();

        if ($yearId === null) {
            return ['subjects_with_exams' => 0, 'covered' => 0, 'uncovered' => [], 'unassigned_exams' => 0];
        }

        $examsBySubject = CorigentaExam::query()
            ->toBase()
            ->join('subjects', 'subjects.id', '=', 'corigenta_exams.subject_id')
            ->join('terms', 'terms.id', '=', 'corigenta_exams.term_id')
            ->where('terms.academic_year_id', $yearId)
            ->groupBy('corigenta_exams.subject_id', 'subjects.name')
            ->selectRaw('corigenta_exams.subject_id, subjects.name, COUNT(*) as exams')
            ->orderBy('subjects.name')
            ->get();

        $coveredSubjectIds = ExamCommission::query()
            ->where('academic_year_id', $yearId)
            ->pluck('subject_id')
            ->map(fn ($id): int => (int) $id);

        $uncovered = array_values($examsBySubject
            ->reject(fn (object $row): bool => $coveredSubjectIds->contains((int) $row->subject_id))
            ->map(fn (object $row): array => [
                'id' => (int) $row->subject_id,
                'name' => (string) $row->name,
                'exams' => (int) $row->exams,
                'create_url' => $this->createUrl((int) $row->subject_id),
            ])
            ->all());

        $unassigned = CorigentaExam::query()
            ->whereNull('exam_commission_id')
            ->whereHas('term', fn (Builder $query) => $query->where('academic_year_id', $yearId))
            ->count();

        return [
            'subjects_with_exams' => $examsBySubject->count(),
            'covered' => $examsBySubject->count() - count($uncovered),
            'uncovered' => $uncovered,
            'unassigned_exams' => $unassigned,
        ];
    }

    /**
     * Cardurile comisiilor anului activ: componența nominală + stările care cer atenție.
     *
     * @return list<array{id: int, name: string, subject: string, president: string|null, members: list<string>, persons: int, complete: bool, exams: int, edit_url: string}>
     */
    public function commissionCards(): array
    {
        $yearId = $this->activeYearId();

        if ($yearId === null) {
            return [];
        }

        $cards = ExamCommission::query()
            ->with(['subject', 'president', 'members'])
            ->withCount('members')
            ->where('academic_year_id', $yearId)
            ->get()
            ->map(function (ExamCommission $commission): array {
                // Persoane DISTINCTE: un președinte trecut din greșeală și la membri nu se numără
                // de două ori — pragul de 3 e despre oameni, nu despre rânduri.
                $persons = $commission->members
                    ->pluck('id')
                    ->push($commission->president_teacher_id)
                    ->filter()
                    ->unique()
                    ->count();

                return [
                    'id' => (int) $commission->id,
                    'name' => $commission->name,
                    'subject' => (string) ($commission->subject->name ?? '—'),
                    'president' => $commission->president?->full_name,
                    'members' => array_values($commission->members
                        ->map(fn (Teacher $teacher): string => (string) $teacher->full_name)
                        ->sort()
                        ->all()),
                    'persons' => $persons,
                    'complete' => $commission->president_teacher_id !== null && $persons >= self::MIN_PERSONS,
                    'exams' => CorigentaExam::query()->where('exam_commission_id', $commission->id)->count(),
                    'edit_url' => ExamCommissionResource::getUrl('edit', ['record' => $commission]),
                ];
            })
            ->sortBy([['complete', 'asc'], ['subject', 'asc']])
            ->values()
            ->all();

        return array_values($cards);
    }

    /** Creare pre-completată cu anul activ (+ disciplina, când vine din coada „de acoperit"). */
    public function createUrl(?int $subjectId): string
    {
        return ExamCommissionResource::getUrl('create', array_filter([
            'an' => $this->activeYearId(),
            'disciplina' => $subjectId,
        ]));
    }
}
