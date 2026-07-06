<?php

namespace App\Filament\Pages\Auth;

use App\Actions\ConfirmTwoFactorEmailSetup;
use App\Actions\SendTwoFactorEmailCode;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\UnorderedList;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

/**
 * Pagina „Profil" a panoului staff — editarea propriului cont (nume, email, parolă) + secțiunea
 * „Autentificare în doi pași". 2FA-ul staff folosește ACELAȘI sistem Fortify ca tot guard-ul `web`
 * (coloanele two_factor_* + challenge-ul unic de la /login) — NU MFA-ul de panel Filament, care ar
 * introduce un al doilea sistem paralel pe alte coloane (acela rămâne doar în Studio, pe guard-ul
 * `admin`). Operațiile sensibile (activare/dezactivare/regenerare coduri) cer parola actuală.
 *
 * ⚠️ Pagina de profil Filament e legată de meniul user, NU de navigația laterală: `->profile()`
 * doar îi înregistrează ruta + componenta Livewire, fără a o adăuga în colecția de pagini peste
 * care iterează sidebar-ul. De aceea `$navigationGroup`/`shouldRegisterNavigation()` nu au efect
 * aici. Linkul „Setări → Profil" din sidebar e un `NavigationItem` către `getProfileUrl()`,
 * declarat în `AdminPanelProvider`. Tema și limba rămân în meniul user (pastila + tabs).
 */
class EditProfile extends BaseEditProfile
{
    public function getTitle(): string|Htmlable
    {
        return __('panel.pages.profile.title');
    }

    public static function getLabel(): string
    {
        return __('panel.pages.profile.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 1. GENERAL — un singur câmp „Nume/Prenume" (convenția moldovenească „Nume Prenume",
                //    ex. „Popescu Ion"). Câmpul e `User::name` — sursa unică din DB. Section pe 2
                //    coloane + `columnSpan(1)` pe câmp = input pe stânga (nu ocupă tot rândul).
                //    Rescris ca TextInput direct (nu prin `getNameFormComponent()` care întoarce
                //    `Component` generic → phpstan pierde tipul; default-urile sunt identice).
                Section::make(__('panel.pages.profile.section_general'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('panel.pages.profile.name_full'))
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->columnSpan(1)
                            // READ-ONLY pentru toți în afară de super-admin: numele e ancora de
                            // identitate (legată de fișa oficială + audit), iar conturile sunt create
                            // de administrație → corecțiile de nume se fac din resursa „Utilizatori",
                            // nu din „profilul meu". Blocarea e server-side: `disabled` +
                            // `dehydrated(false)` → câmpul rămâne VIZIBIL dar needitabil, iar valoarea
                            // nu se persistă nici printr-un request falsificat.
                            ->disabled(fn (): bool => ! $this->twoFactorUser()->isSuperAdmin())
                            ->dehydrated(fn (): bool => $this->twoFactorUser()->isSuperAdmin())
                            ->helperText(fn (): ?string => $this->twoFactorUser()->isSuperAdmin()
                                ? null
                                : (string) __('panel.pages.profile.name_locked_hint')),
                    ]),

                // 2. DATE DE CONTACT — email + Telegram + Viber. Aceleași câmpuri ca la Setări →
                //    Notificări (sursă unică: `User::email` + `User::notification_contacts`).
                //    Modifici aici = se schimbă și acolo, și invers. Zero duplicare de state.
                Section::make(__('panel.pages.profile.section_contacts'))
                    ->description(__('panel.pages.profile.section_contacts_hint'))
                    ->columns(3)
                    ->schema([
                        // helperText nu se poate seta după `getEmailFormComponent()` (întoarce
                        // `Component` generic în signatura BaseEditProfile). Textul informativ e
                        // deja pe `section_contacts_hint` deasupra.
                        $this->getEmailFormComponent(),
                        TextInput::make('contacts.telegram')
                            ->label('Telegram')
                            ->placeholder((string) __('site.cabinet.notif_telegram_placeholder'))
                            ->maxLength(120),
                        TextInput::make('contacts.viber')
                            ->label('Viber')
                            ->placeholder((string) __('site.cabinet.notif_viber_placeholder'))
                            ->maxLength(120),
                    ]),

                // 3. SETARE PAROLĂ — AMBELE câmpuri vizibile SIMULTAN pe 2 coloane (stânga: parolă
                //    nouă, dreapta: confirmă parola). Confirmarea rescrisă ca TextInput direct (fără
                //    `->visible(filled(...))` din default-ul Filament) și cu `->required` condiționat:
                //    obligatorie DOAR când parola nouă e completată — altfel se pot ignora amândouă.
                //    Validare `->same('passwordConfirmation')` din `getPasswordFormComponent()`
                //    verifică potrivirea; goale = parola NU se schimbă (`dehydrated(filled)`).
                Section::make(__('panel.pages.profile.section_password'))
                    ->description(__('panel.pages.profile.section_password_hint'))
                    ->columns(2)
                    ->schema([
                        $this->getPasswordFormComponent(),
                        TextInput::make('passwordConfirmation')
                            ->label(__('filament-panels::auth/pages/edit-profile.form.password_confirmation.label'))
                            ->validationAttribute(__('filament-panels::auth/pages/edit-profile.form.password_confirmation.validation_attribute'))
                            ->password()
                            ->autocomplete('new-password')
                            ->revealable(filament()->arePasswordsRevealable())
                            ->required(fn (Get $get): bool => filled($get('password')))
                            ->dehydrated(false),
                    ]),

                // 4. SECURITATE — 2FA (aplicație autentificatoare + cod pe e-mail ca alternativă).
                $this->getTwoFactorSection(),
            ]);
    }

    /**
     * Prefill contactele din `notification_contacts` (sursă unică cu Setări → Notificări).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->twoFactorUser();
        $contacts = $user->notification_contacts ?? [];
        $data['contacts'] = [
            'telegram' => is_string($contacts['telegram'] ?? null) ? $contacts['telegram'] : '',
            'viber' => is_string($contacts['viber'] ?? null) ? $contacts['viber'] : '',
        ];

        return $data;
    }

    /**
     * Pliază contactele în `notification_contacts` (merge cu cele existente ca să nu ștergem
     * chei pe care alt UI le poate seta — ex. messenger).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = $this->twoFactorUser();
        $existing = is_array($user->notification_contacts) ? $user->notification_contacts : [];
        $incoming = is_array($data['contacts'] ?? null) ? $data['contacts'] : [];
        $merged = array_merge($existing, $incoming);
        $data['notification_contacts'] = array_filter(
            $merged,
            static fn ($value): bool => is_string($value) && $value !== '',
        );
        unset($data['contacts']);

        return $data;
    }

    private function getTwoFactorSection(): Section
    {
        return Section::make(__('panel.pages.profile.twofa.title'))
            ->description(__('panel.pages.profile.twofa.description'))
            ->schema([
                Text::make(__('panel.pages.profile.twofa.status_on'))
                    ->color('success')
                    ->visible(fn (): bool => $this->twoFactorUser()->hasEnabledTwoFactorAuthentication()),
                Text::make(__('panel.pages.profile.twofa.status_off'))
                    ->color('warning')
                    ->visible(fn (): bool => ! $this->twoFactorUser()->hasEnabledTwoFactorAuthentication()),
                Actions::make([
                    $this->getEnableTwoFactorAction(),
                    $this->getRecoveryCodesAction(),
                    $this->getRegenerateRecoveryCodesAction(),
                    $this->getDisableTwoFactorAction(),
                ])->key('twoFactorActions'),

                // A doua metodă: cod pe email (alternativă pentru cei fără aplicație de autentificare).
                Text::make(fn (): string => __('panel.pages.profile.twofa.email_status_on', ['email' => (string) $this->twoFactorUser()->email]))
                    ->color('success')
                    ->visible(fn (): bool => $this->twoFactorUser()->usesEmailTwoFactor()),
                Text::make(__('panel.pages.profile.twofa.email_status_off'))
                    ->color('gray')
                    ->visible(fn (): bool => ! $this->twoFactorUser()->usesEmailTwoFactor()),
                Actions::make([
                    $this->getSendEmailCodeAction(),
                    $this->getConfirmEmailCodeAction(),
                    $this->getDisableEmailTwoFactorAction(),
                ])->key('twoFactorEmailActions'),
            ]);
    }

    private function getSendEmailCodeAction(): Action
    {
        return Action::make('sendTwoFactorEmailCode')
            ->label(__('panel.pages.profile.twofa.email_send'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (): bool => ! $this->twoFactorUser()->usesEmailTwoFactor())
            ->modalHeading(__('panel.pages.profile.twofa.email_send_heading'))
            ->modalDescription(__('panel.pages.profile.twofa.email_send_description'))
            ->schema([
                TextInput::make('email')
                    ->label(__('panel.pages.profile.twofa.email_field'))
                    ->email()
                    ->required()
                    ->default(fn (): ?string => $this->twoFactorUser()->email)
                    ->rule(fn (): Unique => Rule::unique('users', 'email')->ignore($this->twoFactorUser()->id)),
                TextInput::make('current_password')
                    ->label(__('panel.pages.profile.twofa.current_password'))
                    ->password()
                    ->required()
                    ->rule('current_password'),
            ])
            ->action(function (array $data, Action $action): void {
                $user = $this->twoFactorUser();
                $email = (string) $data['email'];

                // Aceeași adresă ca a contului nu e „în așteptare" — codul merge pe emailul existent.
                $pendingEmail = ($user->email !== null && strcasecmp($email, $user->email) === 0) ? null : $email;

                if (! app(SendTwoFactorEmailCode::class)->execute($user, $pendingEmail)) {
                    Notification::make()->danger()
                        ->title(__('panel.pages.profile.twofa.email_cooldown'))
                        ->send();

                    $action->halt();
                }

                Notification::make()->success()
                    ->title(__('panel.pages.profile.twofa.email_code_sent'))
                    ->send();
            });
    }

    private function getConfirmEmailCodeAction(): Action
    {
        return Action::make('confirmTwoFactorEmailCode')
            ->label(__('panel.pages.profile.twofa.email_confirm'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->visible(fn (): bool => ! $this->twoFactorUser()->usesEmailTwoFactor()
                && $this->twoFactorUser()->twoFactorEmailCode()->exists())
            ->modalHeading(__('panel.pages.profile.twofa.email_confirm_heading'))
            ->schema([
                TextInput::make('code')
                    ->label(__('panel.pages.profile.twofa.code'))
                    ->required()
                    ->maxLength(6),
            ])
            ->action(function (array $data, Action $action): void {
                $result = app(ConfirmTwoFactorEmailSetup::class)->execute($this->twoFactorUser(), (string) $data['code']);

                if ($result !== 'ok') {
                    Notification::make()->danger()
                        ->title($result === 'email_taken'
                            ? __('panel.pages.profile.twofa.email_taken')
                            : __('panel.pages.profile.twofa.email_invalid_code'))
                        ->send();

                    $action->halt();
                }

                Notification::make()->success()
                    ->title(__('panel.pages.profile.twofa.email_enabled_success'))
                    ->send();
            });
    }

    private function getDisableEmailTwoFactorAction(): Action
    {
        return Action::make('disableTwoFactorEmail')
            ->label(__('panel.pages.profile.twofa.email_disable'))
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->color('danger')
            ->visible(fn (): bool => $this->twoFactorUser()->usesEmailTwoFactor())
            ->modalHeading(__('panel.pages.profile.twofa.email_disable_heading'))
            ->schema([
                TextInput::make('current_password')
                    ->label(__('panel.pages.profile.twofa.current_password'))
                    ->password()
                    ->required()
                    ->rule('current_password'),
            ])
            ->action(function (): void {
                $user = $this->twoFactorUser();
                $user->forceFill(['two_factor_email_enabled_at' => null])->save();
                $user->twoFactorEmailCode()->delete();

                Notification::make()->success()
                    ->title(__('panel.pages.profile.twofa.email_disabled_success'))
                    ->send();
            });
    }

    private function getEnableTwoFactorAction(): Action
    {
        return Action::make('enableTwoFactor')
            ->label(__('panel.pages.profile.twofa.enable'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->visible(fn (): bool => ! $this->twoFactorUser()->hasEnabledTwoFactorAuthentication())
            ->modalHeading(__('panel.pages.profile.twofa.enable_heading'))
            ->modalDescription(__('panel.pages.profile.twofa.enable_description'))
            ->modalSubmitActionLabel(__('panel.pages.profile.twofa.enable'))
            // La deschiderea modalei se generează secretul NEconfirmat (păstrat dacă există deja,
            // ca „reia configurarea") — QR-ul de mai jos are astfel mereu ce afișa.
            ->mountUsing(function (Schema $form): void {
                app(EnableTwoFactorAuthentication::class)($this->twoFactorUser(), force: false);
                $form->fill();
            })
            ->schema([
                Text::make(fn (): HtmlString => new HtmlString(
                    '<div style="display:flex;justify-content:center">'.$this->twoFactorUser()->twoFactorQrCodeSvg().'</div>'
                )),
                Text::make(fn (): string => __('panel.pages.profile.twofa.manual_key', [
                    'key' => decrypt($this->twoFactorUser()->two_factor_secret),
                ]))->color('gray'),
                TextInput::make('code')
                    ->label(__('panel.pages.profile.twofa.code'))
                    ->required()
                    ->maxLength(6),
                TextInput::make('current_password')
                    ->label(__('panel.pages.profile.twofa.current_password'))
                    ->password()
                    ->required()
                    ->rule('current_password'),
            ])
            ->action(function (array $data, Action $action): void {
                // Codul se verifică O SINGURĂ DATĂ, în Confirm: cache-ul anti-replay al Fortify
                // reține timestamp-ul codului, deci o pre-validare separată l-ar „consuma" și
                // confirmarea ar respinge același cod ca re-folosit.
                try {
                    app(ConfirmTwoFactorAuthentication::class)($this->twoFactorUser(), (string) $data['code']);
                } catch (ValidationException) {
                    Notification::make()->danger()
                        ->title(__('panel.pages.profile.twofa.invalid_code'))
                        ->send();

                    $action->halt();
                }

                Notification::make()->success()
                    ->title(__('panel.pages.profile.twofa.enabled_success'))
                    ->send();
            });
    }

    private function getRecoveryCodesAction(): Action
    {
        return Action::make('twoFactorRecoveryCodes')
            ->label(__('panel.pages.profile.twofa.recovery_view'))
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->visible(fn (): bool => $this->twoFactorUser()->hasEnabledTwoFactorAuthentication())
            ->modalHeading(__('panel.pages.profile.twofa.recovery_heading'))
            ->modalDescription(__('panel.pages.profile.twofa.recovery_description'))
            ->modalSubmitAction(false)
            ->schema([
                UnorderedList::make(fn (): array => array_map(
                    fn (string $code): Text => Text::make($code)
                        ->copyable()
                        ->fontFamily(FontFamily::Mono),
                    $this->twoFactorUser()->recoveryCodes(),
                )),
            ]);
    }

    private function getRegenerateRecoveryCodesAction(): Action
    {
        return Action::make('regenerateTwoFactorRecoveryCodes')
            ->label(__('panel.pages.profile.twofa.recovery_regenerate'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (): bool => $this->twoFactorUser()->hasEnabledTwoFactorAuthentication())
            ->modalHeading(__('panel.pages.profile.twofa.recovery_regenerate_heading'))
            ->modalDescription(__('panel.pages.profile.twofa.recovery_regenerate_description'))
            ->schema([
                TextInput::make('current_password')
                    ->label(__('panel.pages.profile.twofa.current_password'))
                    ->password()
                    ->required()
                    ->rule('current_password'),
            ])
            ->action(function (): void {
                app(GenerateNewRecoveryCodes::class)($this->twoFactorUser());

                Notification::make()->success()
                    ->title(__('panel.pages.profile.twofa.regenerated_success'))
                    ->send();
            });
    }

    private function getDisableTwoFactorAction(): Action
    {
        return Action::make('disableTwoFactor')
            ->label(__('panel.pages.profile.twofa.disable'))
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->color('danger')
            ->visible(fn (): bool => $this->twoFactorUser()->hasEnabledTwoFactorAuthentication())
            ->modalHeading(__('panel.pages.profile.twofa.disable_heading'))
            ->modalDescription(__('panel.pages.profile.twofa.disable_description'))
            ->schema([
                TextInput::make('current_password')
                    ->label(__('panel.pages.profile.twofa.current_password'))
                    ->password()
                    ->required()
                    ->rule('current_password'),
            ])
            ->action(function (): void {
                app(DisableTwoFactorAuthentication::class)($this->twoFactorUser());

                Notification::make()->success()
                    ->title(__('panel.pages.profile.twofa.disabled_success'))
                    ->send();
            });
    }

    private function twoFactorUser(): User
    {
        $user = auth('web')->user();
        assert($user instanceof User);

        return $user;
    }
}
