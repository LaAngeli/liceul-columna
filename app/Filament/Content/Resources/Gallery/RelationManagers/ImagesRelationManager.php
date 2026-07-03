<?php

namespace App\Filament\Content\Resources\Gallery\RelationManagers;

use App\Filament\Content\Support\GalleryImageUpload;
use App\Models\GalleryAlbum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;

/**
 * Grila de imagini a unui album (pe pagina de editare). Afișare modernă (contentGrid), cu
 * reordonare prin drag și ștergere per imagine; adăugarea se face prin acțiunea „Adaugă imagini"
 * (upload multiplu), NU printr-un formular per imagine.
 */
class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Imagini';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Stack::make([
                    ImageColumn::make('path')
                        ->label('Imagine')
                        ->disk((string) config('cms.media.disk', 'public'))
                        ->extraImgAttributes([
                            'class' => 'rounded-xl object-cover',
                            'style' => 'width:100%;height:auto;aspect-ratio:4/3;',
                        ]),
                ]),
            ])
            ->contentGrid(['sm' => 2, 'md' => 3, 'xl' => 4])
            ->headerActions([
                Action::make('addImages')
                    ->label('Adaugă imagini')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([GalleryImageUpload::field()])
                    ->action(function (array $data): void {
                        /** @var GalleryAlbum $album */
                        $album = $this->getOwnerRecord();

                        /** @var list<string> $paths */
                        $paths = $data['images'] ?? [];
                        $count = GalleryImageUpload::store($album, $paths);

                        Notification::make()
                            ->success()
                            ->title($count.' imagini adăugate.')
                            ->send();
                    }),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Încă nu sunt imagini')
            ->emptyStateDescription('Folosește „Adaugă imagini" pentru a încărca una sau mai multe deodată.')
            ->emptyStateIcon(Heroicon::OutlinedPhoto);
    }
}
