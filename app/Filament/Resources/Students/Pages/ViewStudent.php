<?php

namespace App\Filament\Resources\Students\Pages;

use App\Actions\LogStudentAccess;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * Fișa read-only a elevului. Ținta drill-down-ului „Corigenți" și a linkurilor inverse din
 * catalog (note/absențe/corecții) — accesibilă și diriginților, care nu pot edita fișa.
 */
class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    /**
     * Jurnalizarea accesului (L133 §7): panoul e calea PRINCIPALĂ a personalului către dosarul
     * elevului — fără această intrare, jurnalul acoperea doar cabinetul, iar consultările din
     * /admin rămâneau invizibile. Rulează DUPĂ parent::mount() (autorizarea paginii a trecut).
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        $student = $this->getRecord();

        if ($student instanceof Student) {
            app(LogStudentAccess::class)->record($student, 'viewed', 'Vizualizare fișă elev în panou');
        }
    }

    /**
     * @return array<int, EditAction>
     */
    protected function getHeaderActions(): array
    {
        // Editarea rămâne doar pentru configuratori — EditAction se ascunde automat prin
        // StudentResource::canEdit() ({@see \App\Filament\Concerns\ManagedByConfigurators}).
        return [
            EditAction::make(),
        ];
    }
}
