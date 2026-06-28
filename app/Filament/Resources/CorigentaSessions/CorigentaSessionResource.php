<?php

namespace App\Filament\Resources\CorigentaSessions;

use App\Filament\Resources\CorigentaSessions\Pages\CreateCorigentaSession;
use App\Filament\Resources\CorigentaSessions\Pages\EditCorigentaSession;
use App\Filament\Resources\CorigentaSessions\Pages\ListCorigentaSessions;
use App\Filament\Resources\CorigentaSessions\Schemas\CorigentaSessionForm;
use App\Filament\Resources\CorigentaSessions\Tables\CorigentaSessionsTable;
use App\Models\CorigentaSession;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Sesiuni de lichidare a corigenței (spec §2.5 / #33): propuse de vicedirectorul pe instruire (draft)
 * → aprobate prin ordinul directorului → publicate de administratorul operațional. Fluxul de
 * aprobare/publicare e în acțiunile tabelului; accesul în {@see User::canManageCorigenta()}.
 */
class CorigentaSessionResource extends Resource
{
    protected static ?string $model = CorigentaSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Configurare';

    protected static ?string $navigationLabel = 'Sesiuni corigență';

    protected static ?string $modelLabel = 'sesiune de corigență';

    protected static ?string $pluralModelLabel = 'Sesiuni corigență';

    public static function form(Schema $schema): Schema
    {
        return CorigentaSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CorigentaSessionsTable::configure($table);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageCorigenta() ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCorigentaSessions::route('/'),
            'create' => CreateCorigentaSession::route('/create'),
            'edit' => EditCorigentaSession::route('/{record}/edit'),
        ];
    }
}
