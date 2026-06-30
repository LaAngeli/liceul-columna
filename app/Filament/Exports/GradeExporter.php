<?php

namespace App\Filament\Exports;

use App\Models\Grade;
use App\Support\ContentTranslator;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

/**
 * Export catalog note (PII de minori): pleacă pe coadă, livrabil prin notificarea Filament.
 * Filtrele și scoping-ul din UI sunt respectate de export (Filament filtrează query-ul însuși).
 */
class GradeExporter extends Exporter
{
    protected static ?string $model = Grade::class;

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
            ExportColumn::make('value')
                ->label('Nota'),
            ExportColumn::make('calificativ')
                ->label('Calificativ'),
            ExportColumn::make('evaluation_type')
                ->label('Tipul evaluării')
                ->formatStateUsing(fn (?string $state): string => $state ?? ''),
            ExportColumn::make('term.name')
                ->label('Semestrul'),
            ExportColumn::make('graded_on')
                ->label('Data'),
            ExportColumn::make('teacher.last_name')
                ->label('Autor (nume)'),
            ExportColumn::make('teacher.first_name')
                ->label('Autor (prenume)'),
            ExportColumn::make('annulled_at')
                ->label('Anulată la')
                ->enabledByDefault(false),
            ExportColumn::make('annulment_reason')
                ->label('Motivul anulării')
                ->enabledByDefault(false),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Exportul de note s-a încheiat — '.Number::format($export->successful_rows).' rânduri exportate.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' rânduri nu au putut fi exportate.';
        }

        return $body;
    }
}
