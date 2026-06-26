<?php

namespace App\Filament\Resources\AcademicRecords\Schemas;

use App\Support\ContentTranslator;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AcademicRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('student.full_name')
                    ->label('Elev'),
                TextEntry::make('subject.name')
                    ->label('Disciplina')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '' : ContentTranslator::subject($state)),
                TextEntry::make('grade_level')
                    ->label('Clasa'),
                TextEntry::make('period')
                    ->label('Perioada')
                    ->badge(),
                TextEntry::make('value')
                    ->label('Media')
                    ->numeric(2)
                    ->placeholder('—'),
                TextEntry::make('calificativ')
                    ->label('Calificativ')
                    ->placeholder('—'),
            ]);
    }
}
