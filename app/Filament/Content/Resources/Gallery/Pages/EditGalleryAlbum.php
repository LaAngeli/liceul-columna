<?php

namespace App\Filament\Content\Resources\Gallery\Pages;

use App\Filament\Content\Resources\Gallery\GalleryAlbumResource;
use App\Models\GalleryAlbum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditGalleryAlbum extends EditRecord
{
    protected static string $resource = GalleryAlbumResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        /** @var GalleryAlbum $record */
        $record = $this->getRecord();

        return [
            Action::make('viewOnSite')
                ->label('Vezi pe site')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('gray')
                ->url(url('/galerie'), shouldOpenInNewTab: true)
                ->visible($record->published_at !== null),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
