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
                ->label(__('panel.fields.academic_year'))
                ->relationship('academicYear', 'name')
                ->required(),
            Select::make('season')
                ->label(__('panel.forms.corigenta_session.season'))
                ->options(CorigentaSeason::class)
                ->required(),
            Select::make('type')
                ->label(__('panel.forms.corigenta_session.type_long'))
                ->options(CorigentaSessionType::class)
                ->required(),
            DatePicker::make('starts_on')
                ->label(__('panel.forms.corigenta_session.starts_on'))
                ->required(),
            DatePicker::make('ends_on')
                ->label(__('panel.forms.corigenta_session.ends_on'))
                ->required(),
        ]);
    }
}
