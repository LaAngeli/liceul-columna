<?php

namespace App\Filament\Resources\Audits\Schemas;

use App\Models\Audit;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AuditInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                TextEntry::make('auditable_type')
                    ->label(__('panel.tables.audits.data_type'))
                    ->formatStateUsing(fn (Audit $record): string => $record->auditableLabel()),
                TextEntry::make('auditable_id')
                    ->label(__('panel.forms.audit.record_id')),
                TextEntry::make('url')
                    ->label('URL')
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
}
