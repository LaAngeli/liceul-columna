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
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Administrare';

    protected static ?string $navigationLabel = 'Utilizatori';

    protected static ?string $modelLabel = 'utilizator';

    protected static ?string $pluralModelLabel = 'Utilizatori';

    /**
     * Gestiunea conturilor e rezervată administrației (admin/director/director-adjunct).
     * Ierarhia exactă de creare/editare e impusă în canCreate/canEdit/canDelete + UserForm.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdministrator() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isAdministrator() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof User
            && (auth()->user()?->canManageUser($record) ?? false);
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof User
            && (auth()->user()?->canManageUser($record) ?? false);
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
