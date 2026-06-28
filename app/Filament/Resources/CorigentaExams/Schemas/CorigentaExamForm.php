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
                ->label('Sesiune')
                ->relationship('session', 'id')
                ->getOptionLabelFromRecordUsing(fn (CorigentaSession $record): string => $record->season->label().' · '.$record->type->label().' ('.$record->starts_on->format('d.m.Y').')')
                ->searchable(),
            Select::make('exam_commission_id')
                ->label('Comisie')
                ->relationship('commission', 'name')
                ->searchable(),
            DatePicker::make('scheduled_on')
                ->label('Data examenului'),
            Select::make('passed')
                ->label('Rezultat')
                ->options(['1' => 'Promovat (a luat)', '0' => 'Respins'])
                ->placeholder('Programat / neexaminat'),
        ]);
    }
}
