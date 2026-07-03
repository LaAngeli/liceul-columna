<?php

namespace App\Filament\Pages;

use App\Enums\NotificationChannel;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Setări → Notificări (personal). Oglindește fereastra din cabinetul familiei: limba notificărilor,
 * datele de contact pe canale și matricea „ce tip pe ce canal", limitată la tipurile relevante
 * rolului (spec §5). Personalul își primește notificările în clopoțelul panoului (database) +
 * e-mail + rețele sociale, după aceste preferințe.
 *
 * @property-read Schema $form  Schema formularului (proprietate magică Filament).
 */
class NotificationSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.settings');
    }

    public function getTitle(): string
    {
        return __('panel.pages.notifications.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.pages.notifications.title');
    }

    protected string $view = 'filament.pages.notification-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $user = $this->currentUser();

        $this->form->fill([
            'notification_locale' => $user->notification_locale ?? $user->locale ?? 'ro',
            // Email-ul e sursa unică din contul de logare (`User::email`). Îl prefill-uim aici ca
            // să apară completat dacă utilizatorul deja îl are, dar rămâne editabil — modificarea
            // se propagă înapoi în cont la save() (sincronizare bidirecțională).
            'email' => $user->email,
            'contacts' => $user->notification_contacts ?? [],
            'preferences' => $user->effectiveNotificationMatrix(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('notification_locale')
                    ->label(__('site.cabinet.notif_language'))
                    ->helperText(__('site.cabinet.notif_language_hint'))
                    ->options([
                        'ro' => 'Română',
                        'ru' => 'Русский',
                        'en' => 'English',
                    ])
                    ->native(false)
                    ->required(),

                Section::make(__('site.cabinet.notif_contacts'))
                    ->description(__('site.cabinet.notif_contacts_hint'))
                    ->schema([
                        // Emailul de notificare = emailul contului (sursă unică `User::email`).
                        // Editabil MEREU — corecția unei adrese introduse greșit trebuie posibilă
                        // fără să treci printr-un flux administrativ. Schimbarea resetează
                        // `email_verified_at` (adresa nouă cere re-verificare la primul login).
                        TextInput::make('email')
                            ->label(__('panel.fields.email'))
                            ->email()
                            ->maxLength(191)
                            ->placeholder((string) __('site.cabinet.notif_email_add_placeholder'))
                            ->helperText((string) __('site.cabinet.notif_email_add_hint'))
                            ->unique('users', 'email', ignorable: fn (): User => $this->currentUser()),
                        // Hint dinamic „neconfigurat" pe canalele sociale fără token de liceu.
                        // Câmpul rămâne editabil — user poate seta preemptiv contactul pentru ziua
                        // când canalul se activează + poate corecta o valoare introdusă greșit.
                        TextInput::make('contacts.telegram')->label('Telegram')->maxLength(120)
                            ->placeholder((string) __('site.cabinet.notif_telegram_placeholder'))
                            ->hint(NotificationChannel::Telegram->isConfigured() ? null : (string) __('site.cabinet.notif_channel_unconfigured')),
                        TextInput::make('contacts.viber')->label('Viber')->maxLength(120)
                            ->placeholder((string) __('site.cabinet.notif_viber_placeholder'))
                            ->hint(NotificationChannel::Viber->isConfigured() ? null : (string) __('site.cabinet.notif_channel_unconfigured')),
                    ])
                    ->columns(2),

                Section::make(__('site.cabinet.notif_matrix'))
                    ->description(__('site.cabinet.notif_matrix_hint'))
                    ->schema($this->matrixComponents()),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = $this->currentUser();

        $contacts = array_filter(
            is_array($data['contacts'] ?? null) ? $data['contacts'] : [],
            static fn ($value): bool => is_string($value) && $value !== '',
        );

        $updates = [
            'notification_locale' => $data['notification_locale'] ?? null,
            'notification_contacts' => $contacts,
            'notification_preferences' => is_array($data['preferences'] ?? null) ? $data['preferences'] : [],
        ];

        // Sincronizare cu contul: emailul este mereu editabil, ca utilizatorul să poată corecta
        // greșeli. Când valoarea se schimbă efectiv (diferă de cea din cont), resetăm și
        // `email_verified_at` — adresa nouă trebuie re-verificată. Golirea câmpului = null.
        $newEmail = is_string($data['email'] ?? null) && $data['email'] !== '' ? $data['email'] : null;
        if ($newEmail !== $user->email) {
            $updates['email'] = $newEmail;
            $updates['email_verified_at'] = null;
        }

        $user->update($updates);

        Notification::make()
            ->success()
            ->title(__('site.cabinet.notif_saved'))
            ->send();
    }

    /**
     * Câte un CheckboxList per tip relevant rolului — rândurile matricei „tip × canal".
     *
     * @return list<CheckboxList>
     */
    protected function matrixComponents(): array
    {
        // Doar canale livrabile (cabinet/email/telegram/viber).
        $channels = NotificationChannel::selectableOptions();

        $components = [];
        foreach ($this->currentUser()->availableNotificationTypes() as $type) {
            $components[] = CheckboxList::make("preferences.{$type->value}")
                ->label($type->label())
                ->options($channels)
                // Sociale fără token (telegram/viber) → checkbox blocat: utilizatorul nu
                // poate bifa un canal care oricum nu trimite. Cabinet/Email mereu activabile.
                ->disableOptionWhen(fn (string $value): bool => ! (NotificationChannel::tryFrom($value)?->isConfigured() ?? true))
                ->columns(3)
                ->bulkToggleable();
        }

        return $components;
    }

    private function currentUser(): User
    {
        $user = auth('web')->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
