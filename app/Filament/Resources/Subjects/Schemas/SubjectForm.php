<?php

namespace App\Filament\Resources\Subjects\Schemas;

use App\Enums\GradingType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('panel.forms.subject.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('abbreviation')
                    ->label(__('panel.forms.subject.abbreviation'))
                    ->maxLength(30),
                Select::make('grading_type')
                    ->label(__('panel.forms.subject.grading_type'))
                    ->options(GradingType::class)
                    ->default(GradingType::Numeric->value)
                    ->required(),
                TextInput::make('min_grade')
                    ->label(__('panel.forms.subject.min_grade'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12),
                TextInput::make('max_grade')
                    ->label(__('panel.forms.subject.max_grade'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12),
                TextInput::make('report_order')
                    ->label(__('panel.forms.subject.report_order_long'))
                    ->numeric(),
            ]);
    }
}
