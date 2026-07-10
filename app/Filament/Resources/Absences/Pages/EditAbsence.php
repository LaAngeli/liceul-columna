<?php

namespace App\Filament\Resources\Absences\Pages;

use App\Filament\Concerns\EnforcesAbsenceScope;
use App\Filament\Resources\Absences\AbsenceResource;
use App\Models\Absence;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;

class EditAbsence extends EditRecord
{
    use EnforcesAbsenceScope;

    protected static string $resource = AbsenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    // După salvare, revenire la listă (nu rămâne pe formular).
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Trecem id-ul curent ca să se excludă din verificarea anti-duplicat.
        $data = $this->enforceAbsenceScope($data, (int) $this->getRecord()->getKey());

        return $this->syncMotivationWithDate($data);
    }

    /**
     * Dovada de motivare acoperă o PERIOADĂ, nu o absență anume. Dacă data absenței se mută, starea
     * `is_motivated` trebuie recalculată: altfel o absență mutată în afara perioadei rămâne motivată
     * pe baza unei dovezi care nu o mai acoperă (audit staff, finding #4 din raport-absente-staff.md).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncMotivationWithDate(array $data): array
    {
        $record = $this->getRecord();

        if (! $record instanceof Absence || ! isset($data['occurred_on'])) {
            return $data;
        }

        $newDate = Carbon::parse((string) $data['occurred_on']);

        if ($record->occurred_on->isSameDay($newDate)) {
            return $data;
        }

        $data['is_motivated'] = $record->hasApprovedMotivationOn($newDate);

        return $data;
    }
}
