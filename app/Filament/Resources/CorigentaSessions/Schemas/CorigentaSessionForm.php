<?php

namespace App\Filament\Resources\CorigentaSessions\Schemas;

use App\Enums\CorigentaSeason;
use App\Enums\CorigentaSessionType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class CorigentaSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('academic_year_id')
                ->label('An școlar')
                ->relationship('academicYear', 'name')
                ->required(),
            Select::make('season')
                ->label('Sezon')
                ->options(CorigentaSeason::class)
                ->required(),
            Select::make('type')
                ->label('Tip sesiune')
                ->options(CorigentaSessionType::class)
                ->required(),
            DatePicker::make('starts_on')
                ->label('Început')
                ->required(),
            DatePicker::make('ends_on')
                ->label('Sfârșit')
                ->required(),
        ]);
    }
}
