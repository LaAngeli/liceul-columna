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
            ->emptyStateHeading(__('panel.empty.academic_records.heading'))
            ->emptyStateDescription(__('panel.empty.academic_records.description'))
            ->emptyStateIcon('heroicon-o-rectangle-stack')
            ->defaultSort('grade_level')
            ->columns([
                TextColumn::make('student.full_name')
                    ->label(__('panel.fields.student'))
                    ->searchable(['last_name', 'first_name'])
                    ->sortable(),
                TextColumn::make('subject.name')
                    ->label(__('panel.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? (string) __('panel.common.dash') : ContentTranslator::subject($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('grade_level')
                    ->label(__('panel.fields.class'))
                    ->sortable(),
                TextColumn::make('period')
                    ->label(__('panel.fields.period'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('value')
                    ->label(__('panel.forms.academic_record.value'))
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('calificativ')
                    ->label(__('panel.forms.academic_record.calificativ'))
                    ->placeholder(__('panel.common.dash'))
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('grade_level')
                    ->label(__('panel.fields.class'))
                    ->options(array_combine(range(1, 12), array_map(fn (int $n): string => (string) $n, range(1, 12)))),
                SelectFilter::make('period')
                    ->label(__('panel.fields.period'))
                    ->options(AcademicRecordPeriod::options()),
                SelectFilter::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
