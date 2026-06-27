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
        $notifications = $request->user()->notifications()->latest()->limit(50)->get()
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
        $request->user()->notifications()->whereKey($notification)->update(['read_at' => now()]);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function settings(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('cabinet/notification-settings', [
            'contacts' => $user->notification_contacts ?? [],
            // Matricea efectivă (cu implicitul „cabinet" pre-bifat), nu preferințele brute.
            'preferences' => $user->effectiveNotificationMatrix(),
            // Doar tipurile relevante pentru rolul utilizatorului (spec §5: „pe nișa lui").
            'types' => NotificationType::labelsFor($user->availableNotificationTypes()),
            'channels' => NotificationChannel::options(),
            'email' => $user->email,
            'locale' => $user->notification_locale ?? $user->locale ?? 'ro',
            'locales' => self::notificationLocales(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $channelValues = array_map(
            static fn (NotificationChannel $channel): string => $channel->value,
            NotificationChannel::cases(),
        );

        $data = $request->validate([
            'notification_locale' => ['nullable', 'string', Rule::in(array_keys(self::notificationLocales()))],
            'contacts' => ['nullable', 'array'],
            'contacts.telegram' => ['nullable', 'string', 'max:120'],
            'contacts.viber' => ['nullable', 'string', 'max:120'],
            'contacts.messenger' => ['nullable', 'string', 'max:120'],
            'contacts.whatsapp' => ['nullable', 'string', 'max:120'],
            'preferences' => ['nullable', 'array'],
            'preferences.*' => ['array'],
            'preferences.*.*' => [Rule::in($channelValues)],
        ]);

        $request->user()->update([
            'notification_locale' => $data['notification_locale'] ?? null,
            'notification_contacts' => array_filter($data['contacts'] ?? []),
            'notification_preferences' => $data['preferences'] ?? [],
        ]);

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
