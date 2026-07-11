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

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.document_requests.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.document_requests.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.document_requests.plural');
    }

    public static function canAccess(): bool
    {
        return auth('web')->user()?->isAdministrator() ?? false;
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
        if (! (auth('web')->user()?->isAdministrator() ?? false)) {
            return null;
        }

        // whereHas exclude cererile elevilor ARHIVAȚI — nu blochează coada cu rânduri fantomă.
        $pending = DocumentRequest::query()->where('status', RequestStatus::Pending)->whereHas('student')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
