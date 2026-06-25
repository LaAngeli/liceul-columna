<?php

namespace App\Filament\Resources\SchoolClasses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SchoolClassesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('grade_level')
            ->columns([
                TextColumn::make('name')
                    ->label('Clasa')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('section')
                    ->label('Litera/secția')
                    ->searchable(),
                TextColumn::make('grade_level')
                    ->label('Treapta')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('academicYear.name')
                    ->label('An școlar')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('homeroomTeacher.full_name')
                    ->label('Diriginte'),
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
