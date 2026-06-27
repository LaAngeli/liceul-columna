<?php

namespace App\Filament\Resources\Lessons\Tables;

use App\Enums\Weekday;
use App\Models\Lesson;
use App\Support\ContentTranslator;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LessonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('day_of_week')
            ->columns([
                TextColumn::make('schoolClass.name')
                    ->label('Clasa')
                    ->formatStateUsing(fn (Lesson $record): string => trim(($record->schoolClass->name ?? '').' '.($record->schoolClass->section ?? '')))
                    ->sortable(),
                TextColumn::make('day_of_week')
                    ->label('Ziua')
                    ->badge()
                    ->sortable(),
                TextColumn::make('lesson_number')
                    ->label('Lecția')
                    ->sortable(),
                TextColumn::make('subject.name')
                    ->label('Disciplina')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '' : ContentTranslator::subject($state))
                    ->searchable(),
                TextColumn::make('teacher.full_name')
                    ->label('Profesor')
                    ->placeholder('—'),
                TextColumn::make('room')
                    ->label('Sala')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label('Clasa')
                    ->relationship('schoolClass', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('day_of_week')
                    ->label('Ziua')
                    ->options(Weekday::options()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
