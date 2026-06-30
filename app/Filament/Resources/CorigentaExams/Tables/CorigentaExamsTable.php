<?php

namespace App\Filament\Resources\CorigentaExams\Tables;

use App\Enums\CorigentaSeason;
use App\Support\ContentTranslator;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CorigentaExamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('student.full_name')
                    ->label(__('panel.fields.student'))
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('subject.name')->label(__('panel.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? (string) __('panel.common.dash') : ContentTranslator::subject($state)),
                TextColumn::make('season')->label(__('panel.forms.corigenta_session.season'))->badge(),
                TextColumn::make('scheduled_on')->label(__('panel.forms.corigenta_exam.scheduled_on'))->date('d.m.Y')->placeholder(__('panel.forms.corigenta_exam.unscheduled')),
                TextColumn::make('commission.name')->label(__('panel.forms.corigenta_exam.commission'))->placeholder(__('panel.common.dash'))->toggleable(),
                IconColumn::make('passed')->label(__('panel.forms.corigenta_exam.result'))->boolean()->placeholder(__('panel.common.dash')),
            ])
            ->filters([
                SelectFilter::make('season')->label(__('panel.forms.corigenta_session.season'))->options(CorigentaSeason::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
