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
                    ->label('Denumire')
                    ->required()
                    ->maxLength(255),
                TextInput::make('abbreviation')
                    ->label('Abreviere')
                    ->maxLength(30),
                Select::make('grading_type')
                    ->label('Mod de notare')
                    ->options(GradingType::class)
                    ->default(GradingType::Numeric->value)
                    ->required(),
                TextInput::make('min_grade')
                    ->label('De la clasa')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12),
                TextInput::make('max_grade')
                    ->label('Până la clasa')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12),
                TextInput::make('report_order')
                    ->label('Ordine în foaia matricolă')
                    ->numeric(),
            ]);
    }
}
