<?php

namespace App\Filament\Resources\Students\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('last_name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('Prenume')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sex')
                    ->label('Sex')
                    ->badge(),
                TextColumn::make('register_number')
                    ->label('Nr. matricol')
                    ->searchable(),
                TextColumn::make('second_language')
                    ->label('Limba 2')
                    ->badge(),
                TextColumn::make('english_group')
                    ->label('Gr. engleză')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Cont')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
