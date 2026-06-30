<?php

namespace App\Filament\Resources\CalendarEvents\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CalendarEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('panel.forms.calendar_event.title'))
                    ->searchable()
                    ->weight('medium')
                    ->wrap(),

                TextColumn::make('type')
                    ->label(__('panel.fields.type'))
                    ->badge(),

                TextColumn::make('visibility_scope')
                    ->label(__('panel.forms.calendar_event.audience'))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('starts_on')
                    ->label(__('panel.forms.calendar_event.starts'))
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('creator.name')
                    ->label(__('panel.fields.author'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(),
            ])
            ->defaultSort('starts_on', 'desc')
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
