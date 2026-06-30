<?php

namespace App\Filament\Resources\Terms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TermsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('academicYear.name')
                    ->label(__('panel.fields.academic_year'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('number')
                    ->label(__('panel.forms.term.number_short'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('panel.forms.term.name'))
                    ->searchable(),
                TextColumn::make('starts_on')
                    ->label(__('panel.fields.starts_on'))
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label(__('panel.fields.ends_on'))
                    ->date()
                    ->sortable(),
                IconColumn::make('is_current')
                    ->label(__('panel.fields.is_current'))
                    ->boolean(),
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
