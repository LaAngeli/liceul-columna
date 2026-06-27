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
                    ->label('Data')
                    ->dateTime('d.m.Y H:i:s'),
                TextEntry::make('user.name')
                    ->label('Autor')
                    ->placeholder('— sistem —'),
                TextEntry::make('event')
                    ->label('Acțiune')
                    ->badge()
                    ->formatStateUsing(fn (Audit $record): string => $record->eventLabel()),
                TextEntry::make('auditable_type')
                    ->label('Tip date')
                    ->formatStateUsing(fn (Audit $record): string => $record->auditableLabel()),
                TextEntry::make('auditable_id')
                    ->label('ID înregistrare'),
                TextEntry::make('url')
                    ->label('URL')
                    ->placeholder('—'),
                TextEntry::make('ip_address')
                    ->label('IP')
                    ->placeholder('—'),
                KeyValueEntry::make('old_values')
                    ->label('Valori vechi'),
                KeyValueEntry::make('new_values')
                    ->label('Valori noi'),
            ]);
    }
}
