<?php

namespace App\Filament\Resources\ConsentAcknowledgments;

use App\Filament\Resources\ConsentAcknowledgments\Pages\ListConsentAcknowledgments;
use App\Filament\Resources\ConsentAcknowledgments\Tables\ConsentAcknowledgmentsTable;
use App\Models\ConsentAcknowledgment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Dovada „luării la cunoștință" a notei de informare (Legea 133/2011 §7) — READ-ONLY, pentru
 * conducere: cine, ce versiune, când, de la ce IP. Vizibil conform matricei (`canViewAuditLog`).
 */
class ConsentAcknowledgmentResource extends Resource
{
    protected static ?string $model = ConsentAcknowledgment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'Administrare';

    protected static ?string $navigationLabel = 'Consimțăminte';

    protected static ?string $modelLabel = 'confirmare';

    protected static ?string $pluralModelLabel = 'Consimțăminte';

    public static function table(Table $table): Table
    {
        return ConsentAcknowledgmentsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canViewAuditLog() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsentAcknowledgments::route('/'),
        ];
    }
}
