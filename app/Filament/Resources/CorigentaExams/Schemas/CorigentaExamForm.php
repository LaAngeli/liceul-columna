<?php

namespace App\Filament\Resources\CorigentaExams\Schemas;

use App\Models\CorigentaSession;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class CorigentaExamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('corigenta_session_id')
                ->label(__('panel.forms.corigenta_exam.session'))
                ->relationship('session', 'id')
                ->getOptionLabelFromRecordUsing(fn (CorigentaSession $record): string => $record->season->label().' · '.$record->type->label().' ('.$record->starts_on->format('d.m.Y').')')
                ->searchable(),
            Select::make('exam_commission_id')
                ->label(__('panel.forms.corigenta_exam.commission'))
                ->relationship('commission', 'name')
                ->searchable(),
            DatePicker::make('scheduled_on')
                ->label(__('panel.forms.corigenta_exam.scheduled_on_long')),
            Select::make('passed')
                ->label(__('panel.forms.corigenta_exam.result'))
                ->options([
                    '1' => __('panel.forms.corigenta_exam.result_pass'),
                    '0' => __('panel.forms.corigenta_exam.result_fail'),
                ])
                ->placeholder(__('panel.forms.corigenta_exam.result_pending')),
        ]);
    }
}
