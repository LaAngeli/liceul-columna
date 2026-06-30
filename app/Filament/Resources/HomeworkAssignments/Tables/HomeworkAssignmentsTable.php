<?php

namespace App\Filament\Resources\HomeworkAssignments\Tables;

use App\Support\ContentTranslator;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class HomeworkAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('panel.empty.homework.heading'))
            ->emptyStateDescription(__('panel.empty.homework.description'))
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->defaultSort('assigned_on', 'desc')
            ->columns([
                TextColumn::make('assigned_on')
                    ->label(__('panel.fields.date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('subject_name')
                    ->label(__('panel.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? (string) __('panel.common.dash') : ContentTranslator::subject($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('class_label')
                    ->label(__('panel.fields.class'))
                    ->state(fn ($record): string => trim($record->grade_level.' '.($record->section ?? ''))),
                TextColumn::make('topic')
                    ->label(__('panel.forms.homework.topic_column'))
                    ->wrap()
                    ->limit(60),
                TextColumn::make('author_name')
                    ->label(__('panel.fields.author'))
                    ->placeholder(__('panel.common.dash'))
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('grade_level')
                    ->label(__('panel.fields.class'))
                    ->options(array_combine(range(1, 12), array_map(fn (int $n): string => (string) $n, range(1, 12)))),
                SelectFilter::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload(),
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
