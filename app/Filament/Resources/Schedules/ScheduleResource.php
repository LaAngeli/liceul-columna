<?php

namespace App\Filament\Resources\Schedules;

use App\Filament\Resources\Schedules\Pages\CreateSchedule;
use App\Filament\Resources\Schedules\Pages\EditSchedule;
use App\Filament\Resources\Schedules\Pages\ListSchedules;
use App\Filament\Resources\Schedules\Schemas\ScheduleForm;
use App\Filament\Resources\Schedules\Tables\SchedulesTable;
use App\Models\Schedule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Editarea orarelor publicabile (cele 9 secțiuni din pagina Calendar). Sursa UNICĂ: ce se salvează
 * aici se reflectă pe site (rândurile `is_public`). OBLIGAȚIA inserării revine administratorului
 * operațional (§3.2 AO: „publică orarul"); super-adminul are acces break-glass — `canManageSchedules()`.
 */
class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.schedules.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.schedules.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.schedules.plural');
    }

    /**
     * CITIREA se separă de SCRIERE: orarele publicabile se vede de toți cei cărora §3.3 le dă
     * dreptul (conducere, diriginte, profesor), dar se scrie doar de administratorul operațional
     * (`canManageSchedules`, metodele de mai jos). Înainte, ambele treceau prin capabilitatea de
     * scriere, deci secțiunea era invizibilă tuturor celorlalți.
     */
    public static function canAccess(): bool
    {
        return auth('web')->user()?->canViewSchedules() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth('web')->user()?->canManageSchedules() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth('web')->user()?->canManageSchedules() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth('web')->user()?->canManageSchedules() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth('web')->user()?->canManageSchedules() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return ScheduleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchedulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchedules::route('/'),
            'create' => CreateSchedule::route('/create'),
            'edit' => EditSchedule::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
