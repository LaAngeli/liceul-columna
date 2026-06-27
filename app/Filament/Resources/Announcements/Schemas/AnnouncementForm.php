<?php

namespace App\Filament\Resources\Announcements\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Titlu')
                    ->required()
                    ->maxLength(200),
                Textarea::make('body')
                    ->label('Conținut')
                    ->required()
                    ->rows(6),
            ]);
    }
}
