<?php

namespace App\Filament\Resources\AdmissionRequests;

use App\Filament\Concerns\AdministratorOnly;
use App\Filament\Resources\AdmissionRequests\Pages\ListAdmissionRequests;
use App\Filament\Resources\AdmissionRequests\Pages\ViewAdmissionRequest;
use App\Filament\Resources\AdmissionRequests\Schemas\AdmissionRequestInfolist;
use App\Filament\Resources\AdmissionRequests\Tables\AdmissionRequestsTable;
use App\Models\AdmissionRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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

    public static function infolist(Schema $schema): Schema
    {
        return AdmissionRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdmissionRequestsTable::configure($table);
    }

    /** Cererile se nasc pe site-ul public (formularul familiei) — panoul doar le procesează. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Datele trimise de familie nu se rescriu de personal — procesarea = acțiuni cu urmă. */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        // Coada întreagă (nou + contactat) — o cerere contactată e tot de lucru, nu dispare.
        $pending = AdmissionRequest::query()->pending()->count();

        return $pending > 0 ? (string) $pending : null;
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
            'view' => ViewAdmissionRequest::route('/{record}'),
        ];
    }
}
