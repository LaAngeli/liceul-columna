<?php

namespace App\Filament\Resources\Grades\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class GradesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('graded_on', 'desc')
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Elev'),
                TextColumn::make('schoolClass.name')
                    ->label('Clasa')
                    ->sortable(),
                TextColumn::make('subject.name')
                    ->label('Disciplina')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('value')
                    ->label('Nota')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('calificativ')
                    ->label('Calif.'),
                TextColumn::make('term.number')
                    ->label('Sem.'),
                TextColumn::make('graded_on')
                    ->label('Data')
                    ->date()
                    ->sortable(),
                TextColumn::make('teacher.full_name')
                    ->label('Autor')
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
