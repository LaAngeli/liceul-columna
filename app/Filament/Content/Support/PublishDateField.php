<?php

namespace App\Filament\Content\Support;

use App\Filament\Content\Concerns\HandlesPublishDate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Câmpul de publicare, partajat de toate secțiunile de conținut (articole, galerie, bibliotecă):
 * IMPLICIT conținutul se publică automat acum, fără nicio interacțiune cerută editorului.
 * Comutatorul dezvăluie explicit un DatePicker (fără componentă de oră — nu i se cere niciodată
 * editorului) pentru a alege altă dată sau a-l lăsa gol (ciornă). Vezi {@see HandlesPublishDate}
 * pentru logica de creare/editare care interpretează aceste două câmpuri.
 */
class PublishDateField
{
    /**
     * @return array{0: Toggle, 1: DatePicker}
     */
    public static function schema(): array
    {
        return [
            Toggle::make('schedule_publish')
                ->label('Setează manual data publicării')
                ->helperText('Implicit, data publicării se completează automat (azi). Activează doar dacă vrei să alegi altă dată.')
                ->live()
                ->default(false),
            DatePicker::make('published_at')
                ->label('Data publicării')
                ->native(false)
                ->displayFormat('d F Y')
                ->visible(fn (Get $get): bool => (bool) $get('schedule_publish'))
                ->dehydrated(fn (Get $get): bool => (bool) $get('schedule_publish')),
        ];
    }
}
