<?php

namespace App\Filament\Resources\CalendarEvents\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
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
                    // Mobil: esența = titlul + data; tipul intră de la sm în sus.
                    ->visibleFrom('sm')
                    ->label(__('panel.fields.type'))
                    ->badge(),

                // Mobile-first: pe telefon rămân titlul, tipul și data.
                TextColumn::make('visibility_scope')
                    ->label(__('panel.forms.calendar_event.audience'))
                    ->badge()
                    ->color('gray')
                    ->visibleFrom('md'),

                TextColumn::make('starts_on')
                    ->label(__('panel.forms.calendar_event.starts'))
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('creator.name')
                    ->label(__('panel.fields.author'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable()
                    ->visibleFrom('lg'),
            ])
            ->defaultSort('starts_on', 'desc')
            // Fără filtru, evenimentele șterse intrau în limb: dirigintele poate șterge (policy
            // delete = canModify), dar restore = doar conducerea (canPublishContent) — care nu
            // avea NICIO suprafață care să listeze ștersele. Soft delete devenea hard delete.
            ->filters([
                TrashedFilter::make()
                    ->visible(fn (): bool => ($user = auth('web')->user()) instanceof User && $user->canPublishContent()),
            ])
            ->recordActions([
                EditAction::make(),
                // Se auto-ascunde prin CalendarEventPolicy::restore (doar canPublishContent).
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
