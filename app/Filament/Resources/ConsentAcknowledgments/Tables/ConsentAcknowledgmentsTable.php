<?php

namespace App\Filament\Resources\ConsentAcknowledgments\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConsentAcknowledgmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('acknowledged_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->label(__('panel.forms.consent.user'))->searchable(),
                TextColumn::make('document_version')->label(__('panel.forms.consent.version'))->badge(),
                TextColumn::make('acknowledged_at')->label(__('panel.forms.consent.accepted_at'))->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('ip_address')->label(__('panel.forms.consent.ip'))->placeholder(__('panel.common.dash'))->toggleable(),
            ]);
    }
}
