<?php

namespace App\Filament\Resources\Terms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TermsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('academicYear.name')
                    ->label('An școlar')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('number')
                    ->label('Nr.')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Denumire')
                    ->searchable(),
                TextColumn::make('starts_on')
                    ->label('Începe la')
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label('Se termină la')
                    ->date()
                    ->sortable(),
                IconColumn::make('is_current')
                    ->label('Curent')
                    ->boolean(),
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
