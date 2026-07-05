<?php

namespace App\Filament\Resources\Absences\Pages;

use App\Enums\RequestStatus;
use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Concerns\EnforcesAbsenceScope;
use App\Filament\Resources\Absences\AbsenceResource;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use Filament\Resources\Pages\CreateRecord;

class CreateAbsence extends CreateRecord
{
    use DisablesCreateAnother;
    use EnforcesAbsenceScope;

    protected static string $resource = AbsenceResource::class;

    /** Motivul + dovada, când se motivează CHIAR la creare (câmpurile `motivate_now`). */
    private ?string $motivationReason = null;

    private ?string $motivationDocumentPath = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Extrage motivarea inline (nu sunt coloane pe absences) și scoate-le din payload-ul absenței.
        if (! empty($data['motivate_now']) && ! empty($data['motivation_document'])) {
            $this->motivationReason = (string) ($data['motivation_reason'] ?? '');
            $this->motivationDocumentPath = (string) $data['motivation_document'];
        }
        unset($data['motivate_now'], $data['motivation_reason'], $data['motivation_document']);

        return $this->enforceAbsenceScope($data);
    }

    /**
     * Dacă s-a bifat „Motivează acum" cu dovadă, creează un AbsenceMotivation aprobat pe ziua absenței
     * (sursă unică pentru is_motivated) — aceeași cale ca acțiunea „Motivează cu dovadă" din listă.
     */
    protected function afterCreate(): void
    {
        if ($this->motivationDocumentPath === null) {
            return;
        }

        $absence = $this->getRecord();

        if (! $absence instanceof Absence) {
            return;
        }

        $userId = (int) auth('web')->id();

        $motivation = AbsenceMotivation::create([
            'student_id' => $absence->student_id,
            'requested_by_user_id' => $userId,
            'reason' => $this->motivationReason ?? '',
            'period_start' => $absence->occurred_on,
            'period_end' => $absence->occurred_on,
            'document_path' => $this->motivationDocumentPath,
            'status' => RequestStatus::Pending,
            'is_exception' => false,
        ]);

        $motivation->approve($userId);
    }
}
