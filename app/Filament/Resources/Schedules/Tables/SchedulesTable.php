<?php

namespace App\Filament\Resources\Schedules\Tables;

use App\Filament\Resources\Schedules\Pages\ListSchedules;
use App\Models\Schedule;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
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
                // Conținutul orarului nu se vedea NICĂIERI în panou: rândul arăta doar eticheta,
                // iar tabelul propriu-zis apărea abia pe site (sau descifrat din repeater-ul de
                // editare). Previzualizarea arată exact ce văd familiile — înainte de a publica.
                Action::make('preview')
                    ->label(__('panel.forms.schedule.preview'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->slideOver()
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalHeading(fn (Schedule $record): string => (string) $record->label)
                    ->modalDescription(fn (Schedule $record): string => $record->is_public
                        ? (string) __('panel.forms.schedule.preview_public')
                        : (string) __('panel.forms.schedule.preview_draft'))
                    ->modalContent(fn (Schedule $record) => view('filament.catalog.schedule-preview', ['schedule' => $record]))
                    ->modalSubmitAction(false),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
