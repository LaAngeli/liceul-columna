<?php

namespace App\Filament\Resources\AdmissionRequests;

use App\Enums\AdmissionStatus;
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

class AdmissionRequestResource extends Resource
{
    use AdministratorOnly;

    protected static ?string $model = AdmissionRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.admission');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.admission_requests.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.admission_requests.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.admission_requests.plural');
    }

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
        $new = AdmissionRequest::query()->where('status', AdmissionStatus::Nou)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
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
