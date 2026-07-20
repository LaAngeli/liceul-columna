<?php

namespace App\Filament\Resources\Audits\Tables;

use App\Filament\Resources\Audits\Pages\ListAudits;
use App\Models\Audit;
use App\Support\AuditCategories;
use App\Support\SchoolCalendar;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tabelul jurnalului — trăiește în contextul categoriei din navigator, deci filtrul de tip
 * oferă DOAR tipurile categoriei curente (etichetate complet, nu 5 tipuri hardcodate ca înainte).
 * Read-only: singura acțiune e vizualizarea detaliilor (valori vechi/noi).
 */
class AuditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query, $livewire): Builder => $livewire instanceof ListAudits
                ? $livewire->applyAuditContext($query)
                : $query)
            ->defaultSort('created_at', 'desc')
            ->columns([
                // Data compactă cu ora dedesubt — rândul jurnalului rămâne îngust pe mobil.
                TextColumn::make('created_at')
                    ->label(__('panel.fields.date'))
                    ->date('d.m.Y')
                    ->description(fn (Audit $record): ?string => SchoolCalendar::local($record->created_at)?->format('H:i'))
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('panel.fields.author'))
                    ->placeholder(__('panel.common.system'))
                    ->searchable()
                    // Numele lungi nu lățesc rândul — textul complet rămâne la survol.
                    ->limit(24)
                    ->tooltip(fn (Audit $record): ?string => mb_strlen((string) $record->user?->name) > 24 ? $record->user?->name : null),
                TextColumn::make('event')
                    ->label(__('panel.tables.audits.action'))
                    ->badge()
                    ->formatStateUsing(fn (Audit $record): string => $record->eventLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'viewed', 'exported' => 'info',
                        default => 'gray',
                    }),
                // Mobile-first: pe telefon rămân data, autorul și acțiunea; tipul/id-ul intră progresiv
                // (detaliile complete sunt în fișa intrării).
                TextColumn::make('auditable_type')
                    ->label(__('panel.tables.audits.data_type'))
                    ->formatStateUsing(fn (Audit $record): string => $record->auditableLabel())
                    ->visibleFrom('sm'),
                TextColumn::make('auditable_id')
                    ->label(__('panel.tables.audits.id'))
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('ip_address')
                    ->label(__('panel.forms.consent.ip'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label(__('panel.tables.audits.action'))
                    ->options([
                        'created' => __('panel.tables.audits.event_created'),
                        'updated' => __('panel.tables.audits.event_updated'),
                        'deleted' => __('panel.tables.audits.event_deleted'),
                        'restored' => __('panel.tables.audits.event_restored'),
                        'viewed' => __('panel.tables.audits.event_viewed'),
                        'exported' => __('panel.tables.audits.event_exported'),
                    ]),
                SelectFilter::make('auditable_type')
                    ->label(__('panel.tables.audits.data_type'))
                    ->options(fn ($livewire): array => self::typeOptions($livewire)),
            ])
            ->recordActions([
                // Icon-button: jurnalul e read-only, iar eticheta „Vizualizare" lățea fiecare rând.
                ViewAction::make()
                    ->iconButton(),
            ]);
    }

    /**
     * Opțiunile filtrului de tip: tipurile CATEGORIEI curente (sau toate cele mapate, în afara
     * navigatorului / în bucket-ul „Altele" — acolo tipurile sunt necunoscute dinainte).
     *
     * @return array<string, string>
     */
    private static function typeOptions(mixed $livewire): array
    {
        $types = null;

        if ($livewire instanceof ListAudits && ($category = $livewire->activeCategory()) !== null) {
            $types = AuditCategories::typesFor($category);
        }

        $options = [];

        foreach ($types ?? AuditCategories::allMapped() as $type) {
            $options[$type] = Audit::labelForType($type);
        }

        return $options;
    }
}
