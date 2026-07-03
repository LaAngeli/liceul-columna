<?php

namespace App\Filament\Resources\CorigentaExams\Schemas;

use App\Models\CorigentaSession;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
            TextInput::make('mark')
                ->label(__('panel.forms.corigenta_exam.mark'))
                ->numeric()
                ->minValue(1)
                ->maxValue(10)
                ->step(0.01)
                ->helperText(__('panel.forms.corigenta_exam.mark_hint'))
                ->placeholder(__('panel.forms.corigenta_exam.result_pending')),
        ]);
    }
}
