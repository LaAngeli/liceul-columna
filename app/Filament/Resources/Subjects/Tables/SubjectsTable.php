<?php

namespace App\Filament\Resources\Subjects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SubjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.forms.subject.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('abbreviation')
                    ->label(__('panel.forms.subject.abbreviation'))
                    ->searchable(),
                TextColumn::make('grading_type')
                    ->label(__('panel.forms.subject.grading_type_short'))
                    ->badge(),
                // Intervalul de trepte, comasat („5–12") — două coloane numerice separate erau
                // criptice; duplicatele legitime (aceeași disciplină pe trepte diferite) devin
                // vizibile dintr-o privire.
                TextColumn::make('min_grade')
                    ->label(__('panel.forms.subject.grade_span'))
                    ->state(fn ($record): string => $record->min_grade !== null && $record->max_grade !== null
                        ? $record->min_grade.'–'.$record->max_grade
                        : (string) __('panel.common.dash'))
                    ->sortable(),
                TextColumn::make('report_order')
                    ->label(__('panel.forms.subject.report_order'))
                    ->numeric()
                    ->sortable()
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
