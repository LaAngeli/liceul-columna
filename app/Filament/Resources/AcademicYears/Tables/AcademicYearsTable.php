<?php

namespace App\Filament\Resources\AcademicYears\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AcademicYearsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.forms.academic_year.name'))
                    ->searchable()
                    ->sortable(),
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
                TextColumn::make('created_at')
                    ->label(__('panel.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                // „Arhivează în matricolă" a devenit acțiune de PAGINĂ pe hub-ul de carduri
                // (ListAcademicYears::archiveYearAction) — pagina nu mai randează tabelul.
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
