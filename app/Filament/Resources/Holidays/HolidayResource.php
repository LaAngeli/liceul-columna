<?php

namespace App\Filament\Resources\Holidays;

use App\Filament\Resources\Holidays\Pages\CreateHoliday;
use App\Filament\Resources\Holidays\Pages\EditHoliday;
use App\Filament\Resources\Holidays\Pages\ListHolidays;
use App\Filament\Resources\Holidays\Schemas\HolidayForm;
use App\Filament\Resources\Holidays\Tables\HolidaysTable;
use App\Models\Holiday;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Zile libere / vacanțe (sursa unică a „zilei nelucrătoare"). Întreținute de administratorul
 * operațional (`canManageSchedules()` = AO + super-admin break-glass), la fel ca orarele.
 * Folosite de calendar (fundal), de termenele motivărilor (zile lucrătoare) și de expandarea orarului.
 */
class HolidayResource extends Resource
{
    protected static ?string $model = Holiday::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.holidays.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.holidays.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.holidays.plural');
    }

    public static function canAccess(): bool
    {
        return auth('web')->user()?->canManageSchedules() ?? false;
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
        return HolidayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HolidaysTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHolidays::route('/'),
            'create' => CreateHoliday::route('/create'),
            'edit' => EditHoliday::route('/{record}/edit'),
        ];
    }
}
