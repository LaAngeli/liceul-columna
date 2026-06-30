<?php

namespace App\Filament\Exports;

use App\Models\Absence;
use App\Support\ContentTranslator;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

/**
 * Export absențe (PII de minori): pleacă pe coadă, livrabil prin notificarea Filament.
 * Filtrele și scoping-ul din UI sunt respectate de export (Filament filtrează query-ul însuși).
 */
class AbsenceExporter extends Exporter
{
    protected static ?string $model = Absence::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('student.last_name')
                ->label('Nume'),
            ExportColumn::make('student.first_name')
                ->label('Prenume'),
            ExportColumn::make('schoolClass.name')
                ->label('Clasa'),
            ExportColumn::make('subject.name')
                ->label('Disciplina')
                ->formatStateUsing(fn (?string $state): string => $state === null ? '' : ContentTranslator::subject($state)),
            ExportColumn::make('occurred_on')
                ->label('Data'),
            ExportColumn::make('is_motivated')
                ->label('Motivată')
                ->formatStateUsing(fn (mixed $state): string => $state ? 'Da' : 'Nu'),
            ExportColumn::make('term.name')
                ->label('Semestrul'),
            ExportColumn::make('teacher.last_name')
                ->label('Autor (nume)'),
            ExportColumn::make('teacher.first_name')
                ->label('Autor (prenume)'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Exportul de absențe s-a încheiat — '.Number::format($export->successful_rows).' rânduri exportate.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' rânduri nu au putut fi exportate.';
        }

        return $body;
    }
}
