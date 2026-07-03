<?php

namespace App\Filament\Content\Support;

use App\Models\Post;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Tabel partajat de articole (Blog + Actualități). Fiecare resursă e deja scoped pe categorie,
 * deci nu afișăm coloană de categorie.
 */
class ArticleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                ImageColumn::make('image')
                    ->label('Imagine')
                    ->disk((string) config('cms.media.disk', 'public')),
                TextColumn::make('title')
                    ->label('Titlu')
                    ->searchable()
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('published_at')
                    ->label('Publicat')
                    ->date('d.m.Y')
                    ->placeholder('Ciornă')
                    ->badge()
                    ->color(fn (Post $record): string => $record->published_at === null ? 'gray' : 'success')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
