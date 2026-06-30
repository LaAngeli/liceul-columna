<?php

namespace App\Filament\Resources\Schedules\Tables;

use App\Enums\ScheduleType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultGroup('type')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('type')
                    ->label(__('panel.fields.type'))
                    ->badge(),
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
                SelectFilter::make('type')
                    ->label(__('panel.fields.type'))
                    ->options(ScheduleType::options()),
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
