<?php

namespace App\Filament\Resources\CalendarEvents\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CalendarEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titlu')
                    ->searchable()
                    ->weight('medium')
                    ->wrap(),

                TextColumn::make('type')
                    ->label('Tip')
                    ->badge(),

                TextColumn::make('visibility_scope')
                    ->label('Audiență')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('starts_on')
                    ->label('Începe')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('creator.name')
                    ->label('Autor')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('starts_on', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
