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
                ->label(__('panel.exports.last_name')),
            ExportColumn::make('first_name')
                ->label(__('panel.exports.first_name')),
            ExportColumn::make('sex')
                ->label(__('panel.exports.sex'))
                ->formatStateUsing(fn (?string $state): string => $state ?? ''),
            ExportColumn::make('register_number')
                ->label(__('panel.exports.register_number')),
            ExportColumn::make('second_language')
                ->label(__('panel.exports.second_language'))
                ->formatStateUsing(fn (?string $state): string => $state ?? ''),
            ExportColumn::make('english_group')
                ->label(__('panel.exports.english_group'))
                ->enabledByDefault(false),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('panel.exports.done_students', ['count' => Number::format($export->successful_rows)]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.__('panel.exports.failed', ['count' => Number::format($failedRowsCount)]);
        }

        return $body;
    }
}
