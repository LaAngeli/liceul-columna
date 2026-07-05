<?php

namespace App\Filament\Resources\Grades\Pages;

use App\Filament\Concerns\EnforcesGradeScope;
use App\Filament\Resources\Grades\GradeResource;
use App\Jobs\RecomputeTermAverage;
use App\Models\Grade;
use Filament\Resources\Pages\EditRecord;

class EditGrade extends EditRecord
{
    use EnforcesGradeScope;

    protected static string $resource = GradeResource::class;

    /** Semestrul notei ÎNAINTE de salvare — ca să recalculăm media termenului părăsit dacă se schimbă. */
    private ?int $previousTermId = null;

    // Notele nu se șterg (§1) — se anulează cu motiv din listă. Fără DeleteAction.
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        if ($record instanceof Grade) {
            $this->previousTermId = (int) $record->term_id;
        }

        return $this->enforceGradeScope($data);
    }

    /**
     * Dacă data notei a fost mutată peste granița de semestru, term_id derivat se schimbă →
     * GradeObserver recalculează termenul NOU, dar termenul VECHI (din care a plecat nota) rămâne
     * stale. Îl recalculăm explicit aici.
     */
    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof Grade) {
            return;
        }

        $newTermId = (int) $record->term_id;

        if ($this->previousTermId !== null && $this->previousTermId !== $newTermId) {
            RecomputeTermAverage::dispatch(
                (int) $record->student_id,
                (int) $record->subject_id,
                $this->previousTermId,
            );
        }
    }
}
