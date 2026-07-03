<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.users.label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.users.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.users.plural');
    }

    /**
     * Gestiunea conturilor e rezervată celor care au roluri de atribuit: super-admin, director,
     * administrator operațional (§3.3 „Conturi de familie"). Prim-vicedirectorul și administratorul
     * tehnic NU gestionează conturi. Ierarhia exactă e impusă în canCreate/canEdit + UserForm.
     */
    public static function canAccess(): bool
    {
        return auth('web')->user()?->canManageAccounts() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth('web')->user()?->canManageAccounts() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof User
            && (auth('web')->user()?->canManageUser($record) ?? false);
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof User
            && (auth('web')->user()?->canManageUser($record) ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
