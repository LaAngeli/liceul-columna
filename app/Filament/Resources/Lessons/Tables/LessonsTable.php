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
                    ->label(__('panel.fields.class'))
                    ->formatStateUsing(fn (Lesson $record): string => trim(($record->schoolClass->name ?? '').' '.($record->schoolClass->section ?? '')))
                    ->sortable(),
                TextColumn::make('day_of_week')
                    ->label(__('panel.forms.lesson.weekday'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('lesson_number')
                    ->label(__('panel.forms.lesson.period'))
                    ->sortable(),
                TextColumn::make('subject.name')
                    ->label(__('panel.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? (string) __('panel.common.dash') : ContentTranslator::subject($state))
                    ->searchable(),
                TextColumn::make('teacher.full_name')
                    ->label(__('panel.forms.lesson.teacher_short'))
                    ->placeholder(__('panel.common.dash')),
                TextColumn::make('room')
                    ->label(__('panel.forms.lesson.room'))
                    ->placeholder(__('panel.common.dash')),
            ])
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->relationship('schoolClass', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('day_of_week')
                    ->label(__('panel.forms.lesson.weekday'))
                    ->options(Weekday::options()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
