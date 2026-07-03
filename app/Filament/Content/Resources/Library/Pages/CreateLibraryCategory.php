<?php

namespace App\Filament\Content\Resources\Library\Pages;

use App\Filament\Content\Resources\Library\LibraryCategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;

class CreateLibraryCategory extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = LibraryCategoryResource::class;

    /** Fluxul e creare → editare (pentru adăugarea materialelor), deci „creează încă unul" n-are sens. */
    protected static bool $canCreateAnother = false;

    /**
     * Pașii wizardului la creare (Setări generale → RO → RU → EN). Ultimul pas expune „Creare";
     * până atunci utilizatorul are „Următorul". Editarea folosește formularul din Resource.
     *
     * @return array<int, Step>
     */
    protected function getSteps(): array
    {
        return LibraryCategoryResource::wizardSteps();
    }

    /**
     * Publicare automată la creare — la Bibliotecă nu se cere niciodată alegerea explicită a datei.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['published_at'] = now()->startOfDay();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
