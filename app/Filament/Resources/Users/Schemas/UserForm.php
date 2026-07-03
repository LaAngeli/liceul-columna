<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\AudienceDomain;
use App\Enums\UserRole;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('panel.forms.user.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('panel.forms.user.email'))
                    ->email()
                    // Obligatoriu doar la CREARE (acolo e identificatorul de login — formularul nu setează
                    // username). La editare e opțional: mulți elevi/părinți migrați nu au e-mail (intră cu
                    // username), iar resetarea parolei din panou nu trebuie să-i forțeze administrației inventarea unuia.
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->unique(ignoreRecord: true)
                    // Câmp gol → NULL (nu ''), ca să nu intre în coliziune cu indexul unique pe e-mail.
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)
                    ->maxLength(255),
                TextInput::make('password')
                    ->label(__('panel.forms.user.password'))
                    ->password()
                    ->revealable()
                    // Obligatorie doar la creare; la editare, lasă gol pentru a păstra parola.
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255)
                    ->helperText(__('panel.forms.user.password_hint')),
                Select::make('role')
                    ->label(__('panel.forms.user.role'))
                    // Un singur rol per utilizator. Opțiunile sunt limitate la ierarhie (§3.3):
                    // directorul nu atribuie super-admin/administrator tehnic; administratorul
                    // operațional doar conturi de familie + personal pedagogic.
                    ->options(fn (): array => self::roleOptions())
                    ->native(false)
                    ->live()
                    ->required(),
                CheckboxList::make('audience_domains')
                    ->label(__('panel.forms.user.audience_domains'))
                    ->helperText(__('panel.forms.user.audience_domains_hint'))
                    ->options(AudienceDomain::options())
                    ->columns(2)
                    ->visible(fn (Get $get): bool => in_array($get('role'), [
                        UserRole::Director->value,
                        UserRole::PrimVicedirector->value,
                        UserRole::AdministratorOperational->value,
                    ], true)),
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
        foreach (auth('web')->user()?->manageableRoleValues() ?? [] as $value) {
            $options[$value] = UserRole::tryFrom($value)?->label() ?? $value;
        }

        return $options;
    }
}
