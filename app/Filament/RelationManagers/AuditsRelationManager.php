<?php

namespace App\Filament\RelationManagers;

use App\Models\Audit;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Jurnal de audit CONTEXTUAL pentru o înregistrare auditabilă (notă/absență/elev).
 *
 * Folosește relația morfică `audits()` adăugată de traitul owen-it pe modelele auditabile.
 * Strict read-only ({@see isReadOnly()}) — jurnalul nu se editează niciodată (spec §7).
 * Vizibilitatea respectă matricea §3.3 prin {@see User::canViewAuditLog()}.
 */
class AuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'audits';

    protected static string|BackedEnum|null $icon = 'heroicon-o-shield-check';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.resources.audits.label');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth('web')->user()?->canViewAuditLog() ?? false;
    }

    /**
     * Jurnalul de audit nu se creează manual și nu se editează niciodată din UI;
     * `isReadOnly` ascunde acțiunile implicite de create/edit/delete.
     */
    public function isReadOnly(): bool
    {
        return true;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')
                ->label(__('panel.fields.date'))
                ->dateTime('d.m.Y H:i:s'),
            TextEntry::make('user.name')
                ->label(__('panel.fields.author'))
                ->placeholder(__('panel.common.system')),
            TextEntry::make('event')
                ->label(__('panel.tables.audits.action'))
                ->badge()
                ->formatStateUsing(fn (Audit $record): string => $record->eventLabel()),
            TextEntry::make('url')
                ->label(__('panel.tables.audits.url'))
                ->placeholder(__('panel.common.dash')),
            TextEntry::make('ip_address')
                ->label(__('panel.forms.consent.ip'))
                ->placeholder(__('panel.common.dash')),
            KeyValueEntry::make('old_values')
                ->label(__('panel.forms.audit.old_values')),
            KeyValueEntry::make('new_values')
                ->label(__('panel.forms.audit.new_values')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('panel.fields.date'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('panel.fields.author'))
                    ->placeholder(__('panel.common.system'))
                    ->searchable(),
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
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
