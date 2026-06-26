<?php

namespace App\Filament\Resources\AdmissionRequests\Schemas;

use App\Models\AdmissionRequest;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AdmissionRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('parent_name')->label('Nume, prenume părinte')->disabled(),
            TextInput::make('phone')->label('Telefon')->disabled(),
            TextInput::make('email')->label('E-mail')->disabled(),
            TextInput::make('child_name')->label('Nume, prenume copil')->disabled(),
            TextInput::make('child_age')->label('Vârsta copilului')->disabled(),
            TextInput::make('desired_class')->label('Clasa pentru înmatriculare')->disabled(),
            Textarea::make('preferred_time')->label('Disponibilitate pentru vizită')->disabled()->columnSpanFull(),
            Select::make('status')->label('Status')->options(AdmissionRequest::statuses())->required()->default('nou'),
        ]);
    }
}
