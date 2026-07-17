<?php

namespace App\Filament\Resources\Lessons\Tables;

use App\Enums\Weekday;
use App\Filament\Resources\Lessons\Pages\ListLessons;
use App\Support\ContentTranslator;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LessonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('day_of_week')
            // Contextul navigatorului de configurare (clasa activă) — vezi ListLessons.
            ->modifyQueryUsing(fn (Builder $query, $livewire): Builder => $livewire instanceof ListLessons
                ? $livewire->applyClassContext($query)
                : $query)
            ->columns([
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
                // Mobile-first: pe telefon rămân ziua, ora și disciplina (esența orarului).
                TextColumn::make('teacher.full_name')
                    ->label(__('panel.forms.lesson.teacher_short'))
                    ->placeholder(__('panel.common.dash'))
                    ->visibleFrom('md'),
                TextColumn::make('room')
                    ->label(__('panel.forms.lesson.room'))
                    ->placeholder(__('panel.common.dash'))
                    ->visibleFrom('sm'),
            ])
            ->filters([
                SelectFilter::make('day_of_week')
                    ->label(__('panel.forms.lesson.weekday'))
                    ->options(Weekday::options()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
