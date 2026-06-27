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
use UnitEnum;

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

    protected static string|UnitEnum|null $navigationGroup = 'Setări';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Notificări';

    protected static ?string $navigationLabel = 'Notificări';

    protected string $view = 'filament.pages.notification-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $user = $this->currentUser();

        $this->form->fill([
            'notification_locale' => $user->notification_locale ?? $user->locale ?? 'ro',
            'contacts' => $user->notification_contacts ?? [],
            'preferences' => $user->effectiveNotificationMatrix(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('notification_locale')
                    ->label(__('site.notif_language'))
                    ->helperText(__('site.notif_language_hint'))
                    ->options([
                        'ro' => 'Română',
                        'ru' => 'Русский',
                        'en' => 'English',
                    ])
                    ->native(false)
                    ->required(),

                Section::make(__('site.notif_contacts'))
                    ->description(__('site.notif_contacts_hint'))
                    ->schema([
                        TextInput::make('contacts.telegram')->label('Telegram')->maxLength(120),
                        TextInput::make('contacts.viber')->label('Viber')->maxLength(120),
                        TextInput::make('contacts.messenger')->label('Messenger (PSID)')->maxLength(120),
                        TextInput::make('contacts.whatsapp')->label('WhatsApp')->maxLength(120),
                    ])
                    ->columns(2),

                Section::make(__('site.notif_matrix'))
                    ->description(__('site.notif_contacts_hint'))
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

        $user->update([
            'notification_locale' => $data['notification_locale'] ?? null,
            'notification_contacts' => $contacts,
            'notification_preferences' => is_array($data['preferences'] ?? null) ? $data['preferences'] : [],
        ]);

        Notification::make()
            ->success()
            ->title(__('site.notif_saved'))
            ->send();
    }

    /**
     * Câte un CheckboxList per tip relevant rolului — rândurile matricei „tip × canal".
     *
     * @return list<CheckboxList>
     */
    protected function matrixComponents(): array
    {
        $channels = NotificationChannel::options();

        $components = [];
        foreach ($this->currentUser()->availableNotificationTypes() as $type) {
            $components[] = CheckboxList::make("preferences.{$type->value}")
                ->label($type->label())
                ->options($channels)
                ->columns(3)
                ->bulkToggleable();
        }

        return $components;
    }

    private function currentUser(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
