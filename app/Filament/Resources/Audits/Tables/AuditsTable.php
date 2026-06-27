<?php

namespace App\Filament\Resources\Audits\Tables;

use App\Models\Absence;
use App\Models\AcademicRecord;
use App\Models\Audit;
use App\Models\Grade;
use App\Models\Student;
use App\Models\TermAverage;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Autor')
                    ->placeholder('— sistem —')
                    ->searchable(),
                TextColumn::make('event')
                    ->label('Acțiune')
                    ->badge()
                    ->formatStateUsing(fn (Audit $record): string => $record->eventLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'viewed', 'exported' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('auditable_type')
                    ->label('Tip date')
                    ->formatStateUsing(fn (Audit $record): string => $record->auditableLabel()),
                TextColumn::make('auditable_id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Acțiune')
                    ->options([
                        'created' => 'Creare',
                        'updated' => 'Modificare',
                        'deleted' => 'Ștergere',
                        'restored' => 'Restaurare',
                        'viewed' => 'Vizualizare',
                        'exported' => 'Export',
                    ]),
                SelectFilter::make('auditable_type')
                    ->label('Tip date')
                    ->options([
                        Grade::class => 'Notă',
                        Absence::class => 'Absență',
                        AcademicRecord::class => 'Foaie matricolă',
                        TermAverage::class => 'Medie semestrială',
                        Student::class => 'Elev (date personale)',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
