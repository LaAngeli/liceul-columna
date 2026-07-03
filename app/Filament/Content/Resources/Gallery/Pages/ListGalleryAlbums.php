<?php

namespace App\Filament\Content\Resources\Gallery\Pages;

use App\Filament\Content\Resources\Gallery\GalleryAlbumResource;
use App\Filament\Content\Support\GalleryImageUpload;
use App\Models\GalleryAlbum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListGalleryAlbums extends ListRecords
{
    protected static string $resource = GalleryAlbumResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // „Adăugare imagine" ÎNAINTEA „Adăugare album": adaugă una sau mai multe imagini deodată,
            // alegând albumul din listă — flux separat de crearea albumului.
            Action::make('addImage')
                ->label('Adăugare imagine')
                ->icon(Heroicon::OutlinedPhoto)
                ->color('gray')
                ->schema([
                    Select::make('gallery_album_id')
                        ->label('Album')
                        ->options(fn (): array => GalleryAlbum::query()->orderBy('sort_order')->pluck('title', 'id')->all())
                        ->required()
                        ->native(false)
                        ->searchable()
                        ->placeholder('Alege albumul...'),
                    GalleryImageUpload::field(),
                ])
                ->action(function (array $data): void {
                    $album = GalleryAlbum::query()->whereKey($data['gallery_album_id'])->firstOrFail();

                    /** @var list<string> $paths */
                    $paths = $data['images'] ?? [];
                    $count = GalleryImageUpload::store($album, $paths);

                    Notification::make()
                        ->success()
                        ->title($count.' imagini adăugate în „'.$album->title.'".')
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
