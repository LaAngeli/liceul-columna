<?php

namespace App\Filament\Resources\SummativeDesignations\Tables;

use App\Filament\Resources\SummativeDesignations\Pages\ListSummativeDesignations;
use App\Models\SummativeDesignation;
use App\Support\ContentTranslator;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SummativeDesignationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, $livewire): Builder {
                $query->with(['schoolClass', 'subject']);

                // Contextul navigatorului de configurare (anul activ) — vezi ListSummativeDesignations.
                return $livewire instanceof ListSummativeDesignations
                    ? $livewire->applyYearContext($query)
                    : $query;
            })
            ->columns([
                TextColumn::make('schoolClass.name')
                    ->label(__('grading.designation.fields.class'))
                    ->formatStateUsing(fn (SummativeDesignation $record): string => trim($record->schoolClass->name.' '.($record->schoolClass->section ?? '')))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject.name')
                    ->label(__('grading.designation.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? (string) __('panel.common.dash') : ContentTranslator::subject($state))
                    ->searchable()
                    ->sortable(),
                // Tipul sumativei, derivat din ciclul clasei (gimnaziu → ESS, liceu → teză).
                TextColumn::make('summative_type')
                    ->label(__('grading.designation.fields.summative_type'))
                    ->badge()
                    ->state(fn (SummativeDesignation $record): string => $record->summativeLabel()),
                TextColumn::make('order_reference')
                    ->label(__('grading.designation.fields.order_reference'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable()
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label(__('grading.designation.fields.class'))
                    ->relationship('schoolClass', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('school_class_id')
            ->emptyStateHeading(__('grading.designation.empty'))
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
