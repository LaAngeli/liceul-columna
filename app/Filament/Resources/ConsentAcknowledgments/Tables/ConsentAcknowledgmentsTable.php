<?php

namespace App\Filament\Resources\ConsentAcknowledgments\Tables;

use App\Filament\Resources\ConsentAcknowledgments\Pages\ListConsentAcknowledgments;
use App\Models\ConsentAcknowledgment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tabelul DOVEZILOR de luare la cunoștință — trăiește în contextul segmentului din navigator.
 * Versiunea confirmată e un SEMNAL, nu un text: cea curentă (verde) vs. una anterioară (gri) —
 * o dovadă veche nu acoperă versiunea în vigoare.
 */
class ConsentAcknowledgmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query, $livewire): Builder => $livewire instanceof ListConsentAcknowledgments
                ? $livewire->applyConsentContext($query)
                : $query)
            ->defaultSort('acknowledged_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('panel.forms.consent.user'))
                    ->weight('bold')
                    ->description(fn (ConsentAcknowledgment $record): ?string => $record->user?->username)
                    ->searchable(),
                TextColumn::make('document_version')
                    ->label(__('panel.forms.consent.version'))
                    ->badge()
                    ->color(fn (string $state): string => $state === (string) config('privacy.notice_version') ? 'success' : 'gray')
                    ->description(fn (ConsentAcknowledgment $record): ?string => $record->document_version === (string) config('privacy.notice_version')
                        ? null
                        : (string) __('panel.consent_nav.superseded')),
                TextColumn::make('acknowledged_at')
                    ->label(__('panel.forms.consent.accepted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('ip_address')
                    ->label(__('panel.forms.consent.ip'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('current_version')
                    ->label(__('panel.consent_nav.filter_current'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('document_version', (string) config('privacy.notice_version')),
                        false: fn (Builder $query): Builder => $query->where('document_version', '!=', (string) config('privacy.notice_version')),
                    ),
            ]);
    }
}
