<?php

namespace App\Filament\Resources\Audits\Tables;

use App\Filament\Resources\Audits\AuditResource;
use App\Filament\Resources\Audits\Pages\ListAudits;
use App\Models\Audit;
use App\Support\AuditCategories;
use App\Support\SchoolCalendar;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tabelul jurnalului — instrument de INVESTIGARE, nu meniu: rândul deschide FIȘA intrării
 * (tot contextul pe loc), nu duce nicăieri altundeva. Filtre de investigare: utilizator,
 * acțiune, tip de date, severitate, interval de timp; căutare pe autor și IP. Read-only.
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
            ->recordUrl(fn (Audit $record): string => AuditResource::getUrl('view', ['record' => $record]))
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
                        'deleted', 'forceDeleted' => 'danger',
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
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Investigare pe ACTOR: „ce a făcut X?" — searchable, nu preîncărcat (594 de conturi).
                SelectFilter::make('user_id')
                    ->label(__('panel.fields.author'))
                    ->relationship('user', 'name')
                    ->searchable(),
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
                // Severitatea DERIVATĂ (aceeași hartă ca badge-ul fișei — Audit::severityMap).
                SelectFilter::make('severity')
                    ->label(__('panel.audit_view.severity_label'))
                    ->options([
                        'danger' => __('panel.audit_view.severity.danger'),
                        'warning' => __('panel.audit_view.severity.warning'),
                        'info' => __('panel.audit_view.severity.info'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return is_string($value) && $value !== ''
                            ? $query->whereIn('event', Audit::severityMap()[$value] ?? [])
                            : $query;
                    }),
                // Investigare pe INTERVAL: „ce s-a întâmplat între X și Y?".
                Filter::make('perioada')
                    ->label(__('panel.audit_view.period'))
                    ->schema([
                        DatePicker::make('de_la')->label(__('panel.audit_view.from')),
                        DatePicker::make('pana_la')->label(__('panel.audit_view.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['de_la'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['pana_la'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
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
