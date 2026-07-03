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
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getTwoFactorSection(),
            ]);
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
                    ->color('neutral')
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
                ]))->color('neutral'),
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
