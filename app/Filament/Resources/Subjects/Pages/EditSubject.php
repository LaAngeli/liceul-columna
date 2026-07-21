<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\Subject;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSubject extends EditRecord
{
    use PlacesRecordActionsWithForm;

    protected static string $resource = SubjectResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getRecordActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * (1) Poziția în foaia matricolă se aplică prin singura cale de scriere
     * ({@see Subject::placeInReportOrder}) — mutarea pe o poziție ocupată împinge
     * restul tranzacțional, pozițiile rămân unice și contigue.
     * (2) Dicționarele RU/EN (`lang/{ru,en}/subjects.php`) sunt cheiate pe numele RO EXACT —
     * redenumirea disciplinei rupe traducerea TĂCUT (fallback la RO în cabinet/site).
     * Avertizăm configuratorul pe loc, cu pașii de urmat (CLAUDE.md §9).
     */
    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof Subject) {
            return;
        }

        $raw = $this->data['report_order'] ?? null;
        Subject::placeInReportOrder($record, is_numeric($raw) ? (int) $raw : null);

        if (! $record->wasChanged('name')) {
            return;
        }

        $newName = (string) $record->getAttribute('name');
        $missing = [];

        foreach (['ru', 'en'] as $locale) {
            $path = lang_path("{$locale}/subjects.php");
            /** @var array<string, string> $map */
            $map = is_file($path) ? require $path : [];

            if (! array_key_exists($newName, $map)) {
                $missing[] = strtoupper($locale);
            }
        }

        if ($missing !== []) {
            Notification::make()
                ->warning()
                ->title(__('panel.forms.subject.rename_translation_title'))
                ->body(__('panel.forms.subject.rename_translation_body', ['locales' => implode(', ', $missing)]))
                ->persistent()
                ->send();
        }
    }
}
