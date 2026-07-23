<?php

namespace App\Filament\Schemas;

use App\Actions\CreateAccountForFiche;
use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\TemporaryPassword;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Secțiunea „Cont de acces" a unei FIȘE (profesor / elev) — cerința beneficiarului 2026-07-24.
 *
 * Înainte, fișa fără cont oferea un singur lucru: un select „leagă un cont existent". Inutil exact
 * în cazul care contează (nu există ce lega — 18 profesori importați sunt în situația asta) și
 * derutant, fiindcă la profesor lista nici măcar nu era filtrată: puteai lega un cont de părinte
 * sau unul deja folosit de altă fișă.
 *
 * Acum secțiunea tratează cele TREI stări ale unei fișe, separat:
 *  1. fișă CU cont     → identitatea contului, la vedere, + trecerea la administrarea lui;
 *  2. fișă FĂRĂ cont   → acțiunea „Creează cont de acces": doar câmpurile de acces, într-un modal,
 *                        fiindcă datele personale există deja în fișă și nu se re-tastează;
 *  3. cont orfan       → legarea rămâne posibilă, dar apare DOAR dacă există chiar conturi
 *                        potrivite și libere (altfel selectul e un câmp gol care nu explică nimic).
 */
class FicheAccountSection
{
    public static function make(): Section
    {
        return Section::make(__('panel.forms.fiche_account.section'))
            ->description(__('panel.forms.fiche_account.description'))
            ->schema([
                // STAREA contului, în cuvinte: cine e, cu ce utilizator intră, dacă e suspendat
                // sau are încă parolă temporară.
                Text::make(fn (?Model $record): string => self::statusLine($record))
                    ->color(fn (?Model $record): string => self::hasAccount($record) ? 'gray' : 'warning'),

                Actions::make([
                    // ── Fișă FĂRĂ cont: crearea, cu DOAR câmpurile de acces ──────────────────
                    // (cheia componentei o face adresabilă în teste: TestAction::schemaComponent)
                    Action::make('createFicheAccount')
                        ->label(__('panel.forms.fiche_account.create'))
                        ->icon('heroicon-o-key')
                        ->visible(fn (?Model $record): bool => self::canCreateFor($record))
                        ->modalHeading(fn (?Model $record): string => __('panel.forms.fiche_account.create_heading', [
                            'name' => self::ficheName($record),
                        ]))
                        ->modalDescription(__('panel.forms.fiche_account.create_description'))
                        ->modalSubmitActionLabel(__('panel.forms.fiche_account.create_submit'))
                        ->fillForm(fn (?Model $record): array => self::defaults($record))
                        ->schema([
                            // Rolul: doar la personalul pedagogic (fișa de elev îl determină singură).
                            Select::make('role')
                                ->label(__('panel.forms.user.role'))
                                ->options(fn (?Model $record): array => self::roleOptions($record))
                                ->native(false)
                                ->required()
                                ->visible(fn (?Model $record): bool => $record instanceof Teacher),
                            TextInput::make('username')
                                ->label(__('panel.forms.user.username'))
                                ->helperText(__('panel.forms.fiche_account.username_hint'))
                                ->required()
                                ->regex('/^[A-Za-z0-9._\-]+$/')
                                ->validationMessages(['regex' => __('panel.forms.user.username_format')])
                                // Regulă EXPLICITĂ, nu ->unique(): în modalul unei pagini de fișă,
                                // Filament ia $record (fișa) drept înregistrare de ignorat și
                                // interoghează `users` cu `teachers.id <> …` — eroare SQL.
                                ->rule('unique:users,username')
                                ->maxLength(60),
                            TextInput::make('email')
                                ->label(__('panel.forms.user.email'))
                                ->helperText(__('panel.forms.fiche_account.email_hint'))
                                ->email()
                                ->rule('unique:users,email')
                                ->maxLength(255),
                            TextInput::make('password')
                                ->label(__('panel.forms.user.temp_password'))
                                ->helperText(__('panel.forms.user.temp_password_hint'))
                                ->password()
                                ->revealable()
                                ->required()
                                ->maxLength(255)
                                ->suffixActions([
                                    Action::make('regenerateFicheAccountPassword')
                                        ->label(__('panel.forms.user.regenerate_password'))
                                        ->tooltip(__('panel.forms.user.regenerate_password'))
                                        ->icon('heroicon-o-arrow-path')
                                        ->action(fn (Set $set) => $set('password', TemporaryPassword::generate())),
                                ]),
                            Toggle::make('send_credentials')
                                ->label(__('panel.forms.user.send_credentials'))
                                ->helperText(__('panel.forms.user.send_credentials_hint')),
                        ])
                        ->action(function (array $data, ?Model $record): void {
                            if (! $record instanceof Teacher && ! $record instanceof Student) {
                                return;
                            }

                            /** @var array{username: string, email?: string|null, password: string, role?: string|null, send_credentials?: bool} $data */
                            $user = app(CreateAccountForFiche::class)->create($record, $data);

                            Notification::make()
                                ->success()
                                ->title(__('panel.forms.fiche_account.created_title'))
                                ->body(__('panel.forms.fiche_account.created_body', [
                                    'username' => $user->username,
                                    'password' => $data['password'],
                                ]))
                                ->persistent()
                                ->send();
                        }),

                    // ── Fișă CU cont: administrarea lui se face în secțiunea Utilizatori ─────
                    Action::make('openFicheAccount')
                        ->label(__('panel.forms.fiche_account.open'))
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('gray')
                        ->visible(fn (?Model $record): bool => self::hasAccount($record)
                            && (auth('web')->user()?->canManageAccounts() ?? false))
                        ->url(fn (?Model $record): string => ($record instanceof Teacher || $record instanceof Student)
                            ? UserResource::getUrl('edit', ['record' => $record->user_id])
                            : '#'),
                ])->key('ficheAccountActions'),

                // ── Supapa pentru CONTURI ORFANE (import): apare doar dacă există unul potrivit ──
                Select::make('user_id')
                    ->label(__('panel.forms.fiche_account.link_orphan'))
                    ->helperText(__('panel.forms.fiche_account.link_orphan_hint'))
                    ->options(fn (?Model $record): array => self::orphanAccountOptions($record))
                    ->searchable()
                    ->native(false)
                    ->visible(fn (?Model $record): bool => self::canCreateFor($record)
                        && self::orphanAccountOptions($record) !== []),
            ]);
    }

    private static function hasAccount(?Model $record): bool
    {
        return ($record instanceof Teacher || $record instanceof Student) && $record->user_id !== null;
    }

    /** Fișă existentă, fără cont, iar utilizatorul are dreptul de a administra conturi. */
    private static function canCreateFor(?Model $record): bool
    {
        return ($record instanceof Teacher || $record instanceof Student)
            && $record->exists
            && $record->user_id === null
            && (auth('web')->user()?->canManageAccounts() ?? false);
    }

    private static function ficheName(?Model $record): string
    {
        return ($record instanceof Teacher || $record instanceof Student)
            ? (string) $record->full_name
            : '';
    }

    /** Starea contului, într-o frază: nimic de ghicit din câmpuri goale. */
    private static function statusLine(?Model $record): string
    {
        if (! $record instanceof Teacher && ! $record instanceof Student) {
            return (string) __('panel.forms.fiche_account.status_new_fiche');
        }

        if ($record->user_id === null) {
            return (string) __('panel.forms.fiche_account.status_none');
        }

        $user = $record->user;

        if ($user === null) {
            return (string) __('panel.forms.fiche_account.status_none');
        }

        $state = match (true) {
            $user->isSuspended() => __('panel.forms.user.state_suspended'),
            (bool) $user->must_change_password => __('panel.forms.user.state_temp'),
            default => __('panel.forms.user.state_active'),
        };

        return (string) __('panel.forms.fiche_account.status_linked', [
            'username' => $user->username ?? $user->name,
            'state' => mb_strtolower((string) $state),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function roleOptions(?Model $record): array
    {
        $actor = auth('web')->user();
        $manageable = $actor instanceof User ? $actor->manageableRoleValues() : [];

        $options = [];

        foreach ([UserRole::Profesor, UserRole::Diriginte] as $role) {
            if (in_array($role->value, $manageable, true)) {
                $options[$role->value] = $role->label();
            }
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(?Model $record): array
    {
        if (! $record instanceof Teacher && ! $record instanceof Student) {
            return [];
        }

        return [
            'role' => $record instanceof Teacher ? UserRole::Profesor->value : null,
            'username' => CreateAccountForFiche::suggestUsername($record),
            // Fișa de profesor are e-mail propriu — se propune ca adresă a contului.
            'email' => $record instanceof Teacher ? $record->email : null,
            'password' => TemporaryPassword::generate(),
            'send_credentials' => false,
        ];
    }

    /**
     * Conturile ORFANE potrivite fișei: rolul corect ȘI nelegate de nicio altă fișă. Lista goală
     * ascunde selectul — un câmp fără opțiuni valide e mai rău decât absența lui.
     *
     * @return array<int, string>
     */
    private static function orphanAccountOptions(?Model $record): array
    {
        if (! $record instanceof Teacher && ! $record instanceof Student) {
            return [];
        }

        $isTeacher = $record instanceof Teacher;
        $roles = $isTeacher
            ? [UserRole::Profesor->value, UserRole::Diriginte->value]
            : [UserRole::Elev->value];
        $table = $isTeacher ? 'teachers' : 'students';

        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $roles))
            ->whereNotExists(fn ($sub) => $sub
                ->selectRaw('1')
                ->from($table)
                ->whereColumn($table.'.user_id', 'users.id')
                ->whereNull($table.'.deleted_at'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
