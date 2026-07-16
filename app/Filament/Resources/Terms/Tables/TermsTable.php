<?php

namespace App\Filament\Resources\Terms\Tables;

use App\Filament\Resources\Terms\Pages\ListTerms;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TermsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            // Contextul navigatorului de configurare (anul activ) — vezi ListTerms.
            ->modifyQueryUsing(fn (Builder $query, $livewire): Builder => $livewire instanceof ListTerms
                ? $livewire->applyYearContext($query)
                : $query)
            ->columns([
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
