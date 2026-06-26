<?php

namespace App\Filament\Resources\DocumentRequests;

use App\Enums\RequestStatus;
use App\Filament\Resources\DocumentRequests\Pages\ListDocumentRequests;
use App\Filament\Resources\DocumentRequests\Tables\DocumentRequestsTable;
use App\Models\DocumentRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Secretariatul vede cererile tipice depuse de familii (§4.3), descarcă PDF-ul și le marchează
 * procesate. Cererile se DEPUN din cabinet, nu de-aici.
 */
class DocumentRequestResource extends Resource
{
    protected static ?string $model = DocumentRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Administrare';

    protected static ?string $navigationLabel = 'Cereri';

    protected static ?string $modelLabel = 'cerere';

    protected static ?string $pluralModelLabel = 'Cereri';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdministrator() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return DocumentRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentRequests::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        if (! (auth()->user()?->isAdministrator() ?? false)) {
            return null;
        }

        $pending = DocumentRequest::query()->where('status', RequestStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }
}
