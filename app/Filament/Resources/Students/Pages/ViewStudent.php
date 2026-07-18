<?php

namespace App\Filament\Resources\Students\Pages;

use App\Actions\LogStudentAccess;
use App\Enums\GeneratedDocumentType;
use App\Filament\Resources\Students\StudentResource;
use App\Http\Controllers\CabinetController;
use App\Models\Student;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
     * @return array<int, Action|ActionGroup|EditAction>
     */
    protected function getHeaderActions(): array
    {
        // Editarea rămâne doar pentru configuratori — EditAction se ascunde automat prin
        // StudentResource::canEdit() ({@see \App\Filament\Concerns\ManagedByConfigurators}).
        return [
            $this->studentDocumentsAction(),
            EditAction::make(),
        ];
    }

    /**
     * Documentele GENERATE ale elevului (foaie matricolă / situația școlară / dosarul), descărcabile
     * direct din fișă — până acum doar cabinetul familiei le lista, deși serverul permitea deja
     * administrației și dirigintelui. Descărcarea trece prin ruta gardată + jurnalizată L133
     * ({@see CabinetController::downloadGeneratedDocument}); vizibilitatea de
     * aici OGLINDEȘTE gardul serverului, ca profesorul de disciplină să nu vadă butoane care dau 403.
     */
    private function studentDocumentsAction(): ActionGroup
    {
        // $this->getRecord() în loc de parametrul de closure: acțiunile de PAGINĂ se evaluează
        // și în contexte fără record injectat, unde un parametru tipat `Student $record` ar pica.
        return ActionGroup::make(array_map(
            fn (GeneratedDocumentType $type): Action => Action::make('doc-'.$type->value)
                ->label($type->getLabel())
                ->icon($type->icon())
                ->url(fn (): string => route('cabinet.document.generate', [
                    'student' => $this->getRecord()->getKey(),
                    'type' => $type->value,
                ]))
                ->openUrlInNewTab(),
            GeneratedDocumentType::cases(),
        ))
            ->label(__('panel.tables.students.generated_documents'))
            ->icon('heroicon-o-document-arrow-down')
            ->button()
            ->color('gray')
            ->visible(function (): bool {
                $user = auth('web')->user();
                $student = $this->getRecord();

                return $user instanceof User
                    && $student instanceof Student
                    && ($user->isAdministrator() || ($student->homeroomUser()?->is($user) ?? false));
            });
    }
}
