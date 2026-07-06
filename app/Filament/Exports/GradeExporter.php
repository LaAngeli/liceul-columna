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
                ->label(__('panel.exports.last_name')),
            ExportColumn::make('student.first_name')
                ->label(__('panel.exports.first_name')),
            ExportColumn::make('schoolClass.name')
                ->label(__('panel.exports.class')),
            ExportColumn::make('subject.name')
                ->label(__('panel.exports.subject'))
                ->formatStateUsing(fn (?string $state): string => $state === null ? '' : ContentTranslator::subject($state)),
            ExportColumn::make('value')
                ->label(__('panel.exports.grade')),
            ExportColumn::make('calificativ')
                ->label(__('panel.exports.calificativ')),
            ExportColumn::make('evaluation_type')
                ->label(__('panel.exports.evaluation_type'))
                ->formatStateUsing(fn (?string $state): string => $state ?? ''),
            ExportColumn::make('term.name')
                ->label(__('panel.exports.term')),
            ExportColumn::make('graded_on')
                ->label(__('panel.exports.date')),
            ExportColumn::make('teacher.last_name')
                ->label(__('panel.exports.author_last')),
            ExportColumn::make('teacher.first_name')
                ->label(__('panel.exports.author_first')),
            ExportColumn::make('annulled_at')
                ->label(__('panel.exports.annulled_at'))
                ->enabledByDefault(false),
            ExportColumn::make('annulment_reason')
                ->label(__('panel.exports.annulment_reason'))
                ->enabledByDefault(false),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('panel.exports.done_grades', ['count' => Number::format($export->successful_rows)]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.__('panel.exports.failed', ['count' => Number::format($failedRowsCount)]);
        }

        return $body;
    }
}
