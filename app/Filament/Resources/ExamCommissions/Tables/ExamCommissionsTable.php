<?php

namespace App\Filament\Resources\ExamCommissions\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExamCommissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Comisie')
                    ->searchable(),
                TextColumn::make('subject.name')
                    ->label('Disciplina')
                    ->sortable(),
                TextColumn::make('president.last_name')
                    ->label('Președinte')
                    ->placeholder('—'),
                TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Membri'),
                TextColumn::make('academicYear.name')
                    ->label('An')
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
