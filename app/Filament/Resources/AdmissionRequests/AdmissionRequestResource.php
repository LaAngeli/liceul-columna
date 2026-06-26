<?php

namespace App\Filament\Resources\AdmissionRequests;

use App\Filament\Concerns\AdministratorOnly;
use App\Filament\Resources\AdmissionRequests\Pages\EditAdmissionRequest;
use App\Filament\Resources\AdmissionRequests\Pages\ListAdmissionRequests;
use App\Filament\Resources\AdmissionRequests\Schemas\AdmissionRequestForm;
use App\Filament\Resources\AdmissionRequests\Tables\AdmissionRequestsTable;
use App\Models\AdmissionRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AdmissionRequestResource extends Resource
{
    use AdministratorOnly;

    protected static ?string $model = AdmissionRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Admitere';

    protected static ?string $navigationLabel = 'Cereri de înscriere';

    protected static ?string $modelLabel = 'cerere de înscriere';

    protected static ?string $pluralModelLabel = 'Cereri de înscriere';

    public static function form(Schema $schema): Schema
    {
        return AdmissionRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdmissionRequestsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $new = AdmissionRequest::query()->where('status', 'nou')->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmissionRequests::route('/'),
            'edit' => EditAdmissionRequest::route('/{record}/edit'),
        ];
    }
}
