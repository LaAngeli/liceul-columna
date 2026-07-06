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
                ->label(__('panel.exports.last_name')),
            ExportColumn::make('student.first_name')
                ->label(__('panel.exports.first_name')),
            ExportColumn::make('schoolClass.name')
                ->label(__('panel.exports.class')),
            ExportColumn::make('subject.name')
                ->label(__('panel.exports.subject'))
                ->formatStateUsing(fn (?string $state): string => $state === null ? '' : ContentTranslator::subject($state)),
            ExportColumn::make('occurred_on')
                ->label(__('panel.exports.date')),
            ExportColumn::make('is_motivated')
                ->label(__('panel.exports.motivated'))
                ->formatStateUsing(fn (mixed $state): string => $state ? (string) __('panel.exports.yes') : (string) __('panel.exports.no')),
            ExportColumn::make('term.name')
                ->label(__('panel.exports.term')),
            ExportColumn::make('teacher.last_name')
                ->label(__('panel.exports.author_last')),
            ExportColumn::make('teacher.first_name')
                ->label(__('panel.exports.author_first')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('panel.exports.done_absences', ['count' => Number::format($export->successful_rows)]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.__('panel.exports.failed', ['count' => Number::format($failedRowsCount)]);
        }

        return $body;
    }
}
