<?php

namespace App\Filament\Content\Support;

use App\Models\Post;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    // Căutare pe titlul RO ȘI pe titlurile traduse (RU/EN din post_translations),
                    // grupate într-un singur OR ca să nu „scape" din grupul de căutare Filament.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        fn (Builder $q) => $q
                            ->where('title', 'like', "%{$search}%")
                            ->orWhereHas('translations', fn (Builder $t) => $t->where('title', 'like', "%{$search}%")),
                    ))
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('published_at')
                    ->label('Publicat')
                    ->date('d.m.Y')
                    ->placeholder('Ciornă')
                    ->badge()
                    // Trei stări distincte: ciornă (gri) / programat în viitor (chihlimbar) / publicat (verde).
                    // Fără starea „programat", un articol cu dată viitoare arăta identic cu unul live.
                    ->color(fn (Post $record): string => match (true) {
                        $record->published_at === null => 'gray',
                        $record->published_at->isFuture() => 'warning',
                        default => 'success',
                    })
                    ->tooltip(fn (Post $record): ?string => $record->published_at?->isFuture()
                        ? 'Programat — va deveni public la această dată'
                        : null)
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
