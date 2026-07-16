<?php

namespace App\Filament\Resources\Schedules\Tables;

use App\Filament\Resources\Schedules\Pages\ListSchedules;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            // Contextul navigatorului de configurare (tipul activ) — vezi ListSchedules.
            ->modifyQueryUsing(fn (Builder $query, $livewire): Builder => $livewire instanceof ListSchedules
                ? $livewire->applyTypeContext($query)
                : $query)
            ->columns([
                TextColumn::make('label')
                    ->label(__('panel.forms.schedule.title'))
                    ->searchable(),
                TextColumn::make('position')
                    ->label(__('panel.forms.schedule.position'))
                    ->sortable(),
                ToggleColumn::make('is_public')
                    ->label(__('panel.forms.schedule.is_public_short')),
                TextColumn::make('updated_at')
                    ->label(__('panel.forms.schedule.updated_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_public')
                    ->label(__('panel.forms.schedule.is_public_filter')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
