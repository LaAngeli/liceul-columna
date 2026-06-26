<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

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
                Select::make('role')
                    ->label('Rol')
                    // Un singur rol per utilizator. Opțiunile sunt limitate la ierarhie (§3.3):
                    // directorul nu atribuie super-admin/administrator tehnic; administratorul
                    // operațional doar conturi de familie + personal pedagogic.
                    ->options(fn (): array => self::roleOptions())
                    ->native(false)
                    ->required(),
            ]);
    }

    /**
     * Rolurile pe care actorul curent are dreptul să le atribuie, cu etichete RO.
     *
     * @return array<string, string>
     */
    private static function roleOptions(): array
    {
        $options = [];
        foreach (auth()->user()?->manageableRoleValues() ?? [] as $value) {
            $options[$value] = UserRole::tryFrom($value)?->label() ?? $value;
        }

        return $options;
    }
}
