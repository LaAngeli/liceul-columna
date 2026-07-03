<?php

namespace App\Filament\Content\Resources\Gallery\Pages;

use App\Filament\Content\Resources\Gallery\GalleryAlbumResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;

class CreateGalleryAlbum extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = GalleryAlbumResource::class;

    /** Fluxul e creare → editare (pentru adăugarea imaginilor), deci „creează încă unul" n-are sens. */
    protected static bool $canCreateAnother = false;

    /**
     * Pașii wizardului la creare (RO → RU → EN). Editarea folosește Tabs din Resource.
     *
     * @return array<int, Step>
     */
    protected function getSteps(): array
    {
        return GalleryAlbumResource::wizardSteps();
    }

    /**
     * Publicare automată la creare. Slug-ul e ales de utilizator în wizard; dacă totuși vine gol
     * (nu ar trebui, e required), îl derivăm din titlu prin {@see GalleryAlbumResource::uniqueSlug()}.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! isset($data['slug']) || $data['slug'] === '') {
            $data['slug'] = GalleryAlbumResource::uniqueSlug((string) ($data['title'] ?? ''));
        }
        $data['published_at'] = now()->startOfDay();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
