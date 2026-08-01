<?php

namespace App\Filament\Resources\CanteenMenus;

use App\Filament\Resources\CanteenMenus\Pages\CreateCanteenMenu;
use App\Filament\Resources\CanteenMenus\Pages\EditCanteenMenu;
use App\Filament\Resources\CanteenMenus\Pages\ListCanteenMenus;
use App\Filament\Resources\CanteenMenus\Schemas\CanteenMenuForm;
use App\Filament\Resources\CanteenMenus\Tables\CanteenMenusTable;
use App\Models\CanteenMenu;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Meniul cantinei (cerință 2026-08-01). CITIREA e a întregului personal — resursa apare tuturor în
 * grupul Comunicare, cu previzualizarea zilei; SCRIEREA (creare/editare/ștergere) aparține exclusiv
 * administratorului operațional, cu break-glass-ul obișnuit al super-adminului
 * ({@see User::canManageCanteenMenu}). Familia consultă același meniu în cabinet
 * (/cabinet/meniu). Fără cache pe nicio cale de citire — salvarea se vede instant peste tot.
 */
class CanteenMenuResource extends Resource
{
    protected static ?string $model = CanteenMenu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.communication');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.canteen_menus.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.canteen_menus.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.canteen_menus.plural');
    }

    /** Consultarea e a tuturor celor cu acces în panou; doar scrierea e gardată mai jos. */
    public static function canAccess(): bool
    {
        return auth('web')->check();
    }

    public static function canCreate(): bool
    {
        return auth('web')->user()?->canManageCanteenMenu() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth('web')->user()?->canManageCanteenMenu() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth('web')->user()?->canManageCanteenMenu() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth('web')->user()?->canManageCanteenMenu() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return CanteenMenuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CanteenMenusTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCanteenMenus::route('/'),
            'create' => CreateCanteenMenu::route('/create'),
            'edit' => EditCanteenMenu::route('/{record}/edit'),
        ];
    }
}
