<?php

namespace App\Filament\Resources\AcademicRecords\Tables;

use App\Enums\AcademicRecordPeriod;
use App\Support\ContentTranslator;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AcademicRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('grade_level')
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Elev')
                    ->searchable(['last_name', 'first_name'])
                    ->sortable(),
                TextColumn::make('subject.name')
                    ->label('Disciplina')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '' : ContentTranslator::subject($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('grade_level')
                    ->label('Clasa')
                    ->sortable(),
                TextColumn::make('period')
                    ->label('Perioada')
                    ->badge(),
                TextColumn::make('value')
                    ->label('Media')
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('calificativ')
                    ->label('Calificativ')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('grade_level')
                    ->label('Clasa')
                    ->options(array_combine(range(1, 12), array_map(fn (int $n): string => (string) $n, range(1, 12)))),
                SelectFilter::make('period')
                    ->label('Perioada')
                    ->options(AcademicRecordPeriod::options()),
                SelectFilter::make('subject_id')
                    ->label('Disciplina')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
