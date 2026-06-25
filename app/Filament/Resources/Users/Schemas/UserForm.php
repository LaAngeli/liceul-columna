<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nume')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->label('Parolă')
                    ->password()
                    ->revealable()
                    // Obligatorie doar la creare; la editare, lasă gol pentru a păstra parola.
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255)
                    ->helperText('La editare, lasă gol pentru a păstra parola actuală.'),
                Select::make('roles')
                    ->label('Roluri')
                    // Doar rolurile pe care actorul are dreptul să le atribuie (ierarhia):
                    // director nu poate atribui admin, director-adjunct nu poate admin/director.
                    ->relationship(
                        'roles',
                        'name',
                        fn (Builder $query) => $query->whereIn('name', auth()->user()?->manageableRoleValues() ?? []),
                    )
                    ->multiple()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(
                        fn (Role $record): string => UserRole::tryFrom($record->name)?->label() ?? $record->name,
                    )
                    ->required(),
            ]);
    }
}
