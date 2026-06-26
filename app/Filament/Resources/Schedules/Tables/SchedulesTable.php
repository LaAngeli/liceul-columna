<?php

namespace App\Filament\Resources\Schedules\Tables;

use App\Enums\ScheduleType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultGroup('type')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('type')
                    ->label('Tip')
                    ->badge(),
                TextColumn::make('label')
                    ->label('Titlu')
                    ->searchable(),
                TextColumn::make('position')
                    ->label('Ordine')
                    ->sortable(),
                ToggleColumn::make('is_public')
                    ->label('Publicat'),
                TextColumn::make('updated_at')
                    ->label('Actualizat')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tip')
                    ->options(ScheduleType::options()),
                TernaryFilter::make('is_public')
                    ->label('Publicat pe site'),
            ])
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
