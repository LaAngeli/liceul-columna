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
                TextColumn::make('user.name')->label('Utilizator')->searchable(),
                TextColumn::make('document_version')->label('Versiune notă')->badge(),
                TextColumn::make('acknowledged_at')->label('Confirmat la')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('ip_address')->label('IP')->placeholder('—')->toggleable(),
            ]);
    }
}
