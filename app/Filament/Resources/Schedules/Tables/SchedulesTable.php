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
            // Contextul navigatorului de configurare (tipul activ) + perimetrul de vizibilitate
            // pe rol (cititorii văd DOAR tabelele publicate) — vezi ListSchedules.
            ->modifyQueryUsing(fn (Builder $query, $livewire): Builder => $livewire instanceof ListSchedules
                ? $livewire->applyTypeContext($query)
                : $query)
            ->columns([
                TextColumn::make('label')
                    ->label(__('panel.forms.schedule.title'))
                    ->searchable(),
                // Ordinea de afișare pe site = detaliu de gestiune; cititorul consultă conținutul.
                TextColumn::make('position')
                    ->label(__('panel.forms.schedule.position'))
                    ->sortable()
                    ->visible(fn (): bool => self::canManage()),
                // Comutatorul de PUBLICARE = doar cine gestionează orarul (AO + super-admin, pe
                // rolul ACTIV). Coloanele editabile Filament NU trec prin policy la salvare —
                // verifică pe server DOAR hidden/disabled (vendor CanUpdateState) — deci garda
                // reală e exact aici: ascuns pentru cititori + dezactivat ca a doua plasă.
                // Înainte, orice profesor cu drept de CITIRE putea răsturna publicarea din listă.
                ToggleColumn::make('is_public')
                    ->label(__('panel.forms.schedule.is_public_short'))
                    ->visible(fn (): bool => self::canManage())
                    ->disabled(fn (): bool => ! self::canManage()),
                TextColumn::make('updated_at')
                    ->label(__('panel.forms.schedule.updated_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                // Cititorii văd oricum doar publicatele — filtrul ar fi un comutator mort.
                TernaryFilter::make('is_public')
                    ->label(__('panel.forms.schedule.is_public_filter'))
                    ->visible(fn (): bool => self::canManage()),
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

    /** Gestiunea orarului (scriere/publicare): AO + super-admin, pe rolul ACTIV. */
    private static function canManage(): bool
    {
        return auth('web')->user()?->canManageSchedules() ?? false;
    }
}
