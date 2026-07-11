<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Filament\Resources\Subjects\SubjectResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSubject extends EditRecord
{
    protected static string $resource = SubjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Dicționarele RU/EN (`lang/{ru,en}/subjects.php`) sunt cheiate pe numele RO EXACT —
     * redenumirea disciplinei rupe traducerea TĂCUT (fallback la RO în cabinet/site).
     * Avertizăm configuratorul pe loc, cu pașii de urmat (CLAUDE.md §9).
     */
    protected function afterSave(): void
    {
        $record = $this->getRecord();

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
