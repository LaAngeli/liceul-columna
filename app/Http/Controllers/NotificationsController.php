<?php

namespace App\Http\Controllers;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Compartimentul de notificări din cabinet (spec §5): inboxul in-app + fereastra de setări
 * (contacte pe canale + matricea „ce tip pe ce canal").
 */
class NotificationsController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user('web')->notifications()->latest()->limit(50)->get()
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'type' => $notification->data['type'] ?? null,
                'title' => $notification->data['title'] ?? '',
                'body' => $notification->data['body'] ?? '',
                'url' => $notification->data['url'] ?? null,
                'read' => $notification->read_at !== null,
                'at' => $notification->created_at?->format('d.m.Y H:i'),
            ])->all();

        return Inertia::render('cabinet/notifications', [
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        // Folosim API-ul DatabaseNotification (la fel ca markAllRead) ca să declanșăm event-urile
        // modelului — orice listener viitor pe „marcat citit" (sync cross-device, analytics) prinde
        // ambele căi uniform. null-safe = no-op silențios dacă id-ul nu există / nu aparține userului.
        $request->user('web')->notifications()->whereKey($notification)->first()?->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user('web')->unreadNotifications->markAsRead();

        return back();
    }

    public function settings(Request $request): Response
    {
        $user = $request->user('web');

        return Inertia::render('cabinet/notification-settings', [
            'contacts' => $user->notification_contacts ?? [],
            // Matricea efectivă (cu implicitul „cabinet" pre-bifat), nu preferințele brute.
            'preferences' => $user->effectiveNotificationMatrix(),
            // Doar tipurile relevante pentru rolul utilizatorului (spec §5: „pe nișa lui").
            'types' => NotificationType::labelsFor($user->availableNotificationTypes()),
            // Doar canale livrabile (cabinet/email/telegram/viber).
            'channels' => NotificationChannel::selectableOptions(),
            // Per-canal: e activat de liceu? (cabinet/email mereu da; sociale după token din .env)
            // UI marchează vizual canalele sociale neconfigurate; matricea nu lasă să se bifeze.
            'channelStatus' => NotificationChannel::configurationStatus(),
            'email' => $user->email,
            'locale' => $user->notification_locale ?? $user->locale ?? 'ro',
            'locales' => self::notificationLocales(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        // Doar canale LIVRABILE — o preferință pe un canal necunoscut trimisă manual e respinsă (422).
        $channelValues = array_map(
            static fn (NotificationChannel $channel): string => $channel->value,
            array_filter(NotificationChannel::cases(), static fn (NotificationChannel $c): bool => $c->isDeliverable()),
        );

        $user = $request->user('web');

        // Empty string trimis de formularul HTML → tratat ca „nu se schimbă adresa" (nu declanșează
        // eroarea `email`). Astfel utilizatorul care doar salvează preferințele nu e obligat să
        // reintroducă adresa. Golirea intenționată a adresei se face separat (contact admin).
        $submittedEmail = trim((string) $request->input('email', ''));

        $rules = [
            'notification_locale' => ['nullable', 'string', Rule::in(array_keys(self::notificationLocales()))],
            'contacts' => ['nullable', 'array'],
            'contacts.telegram' => ['nullable', 'string', 'max:120'],
            'contacts.viber' => ['nullable', 'string', 'max:120'],
            'preferences' => ['nullable', 'array'],
            'preferences.*' => ['array'],
            'preferences.*.*' => [Rule::in($channelValues)],
        ];

        if ($submittedEmail !== '') {
            // Editare descentralizată: utilizatorul își gestionează adresa în această secțiune,
            // indiferent dacă e prima setare sau corectarea unei greșeli anterioare. Fortify acceptă
            // login pe email SAU username, deci schimbarea nu blochează accesul (username-ul rămâne).
            $rules['email'] = ['string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)];
        }

        $data = $request->validate($rules);

        $attributes = [
            'notification_locale' => $data['notification_locale'] ?? null,
            'notification_contacts' => array_filter($data['contacts'] ?? []),
            'notification_preferences' => $data['preferences'] ?? [],
        ];

        if ($submittedEmail !== '' && $submittedEmail !== $user->email) {
            $attributes['email'] = $submittedEmail;
        }

        $user->update($attributes);

        return back()->with('success', 'Preferințele de notificare au fost salvate.');
    }

    /**
     * Limbile în care se pot livra notificările — etichetate în limba proprie (selector de limbă).
     *
     * @return array<string, string>
     */
    private static function notificationLocales(): array
    {
        return [
            'ro' => 'Română',
            'ru' => 'Русский',
            'en' => 'English',
        ];
    }
}
