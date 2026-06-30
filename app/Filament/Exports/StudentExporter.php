<?php

namespace App\Filament\Exports;

use App\Models\Student;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

/**
 * Export listă elevi (PII de minori): pleacă pe coadă, livrabil prin notificarea Filament.
 * Filtrele și scoping-ul din UI sunt respectate de export (Filament filtrează query-ul însuși).
 */
class StudentExporter extends Exporter
{
    protected static ?string $model = Student::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('last_name')
                ->label('Nume'),
            ExportColumn::make('first_name')
                ->label('Prenume'),
            ExportColumn::make('sex')
                ->label('Sex')
                ->formatStateUsing(fn (?string $state): string => $state ?? ''),
            ExportColumn::make('register_number')
                ->label('Nr. matricol'),
            ExportColumn::make('second_language')
                ->label('Limba a 2-a')
                ->formatStateUsing(fn (?string $state): string => $state ?? ''),
            ExportColumn::make('english_group')
                ->label('Grupa engleză')
                ->enabledByDefault(false),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Exportul listei de elevi s-a încheiat — '.Number::format($export->successful_rows).' rânduri exportate.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' rânduri nu au putut fi exportate.';
        }

        return $body;
    }
}
