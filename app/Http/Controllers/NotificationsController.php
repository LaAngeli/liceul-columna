<?php

namespace App\Http\Controllers;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            // Emailul de login se poate SETA din cabinet doar prima dată (userii migrați au email gol);
            // odată setat, schimbarea trece prin personal (vezi updateSettings, #37).
            'emailEditable' => trim((string) ($user->email ?? '')) === '',
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
        // reintroducă adresa.
        $submittedEmail = trim((string) $request->input('email', ''));

        // SECURITATE (#37): emailul e identificatorul de login + destinația codului OTP 2FA + adresa
        // pe care pleacă linkul de resetare a parolei. O sesiune deschisă care ar putea REPOINTA
        // adresa = preluare de cont (schimbă emailul → „am uitat parola" → link la atacator) + lockout
        // 2FA. De aceea permitem DOAR PRIMA setare (userii migrați au email gol), nu și schimbarea
        // ulterioară — corectarea unei adrese deja setate se face de personal (UserResource), coerent
        // cu doctrina „cabinet view-only, conturi gestionate de personal".
        $currentEmail = trim((string) ($user->email ?? ''));
        $emailIsFirstTimeSet = $submittedEmail !== '' && $currentEmail === '';

        $rules = [
            'notification_locale' => ['nullable', 'string', Rule::in(array_keys(self::notificationLocales()))],
            'contacts' => ['nullable', 'array'],
            'contacts.telegram' => ['nullable', 'string', 'max:120'],
            'contacts.viber' => ['nullable', 'string', 'max:120'],
            'preferences' => ['nullable', 'array'],
            'preferences.*' => ['array'],
            'preferences.*.*' => [Rule::in($channelValues)],
        ];

        if ($emailIsFirstTimeSet) {
            $rules['email'] = ['string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)];
        }

        // Încercare de a SCHIMBA o adresă deja setată → respinsă cu îndrumare spre secretariat.
        if ($submittedEmail !== '' && $currentEmail !== '' && $submittedEmail !== $currentEmail) {
            throw ValidationException::withMessages([
                'email' => __('cabinet_flash.email_change_via_staff'),
            ]);
        }

        $data = $request->validate($rules);

        $availableTypeValues = array_map(
            static fn (NotificationType $type): string => $type->value,
            $user->availableNotificationTypes(),
        );

        // Sanitizează preferințele: DOAR tipurile RELEVANTE rolului (§5) — UI-ul arată doar aceste tipuri,
        // dar un POST manipulat putea salva o preferință pe un tip străin rolului (ex. părinte pe un tip
        // destinat staff-ului). Canalele rămân cele livrabile (validate mai sus prin Rule::in); un canal
        // social încă fără token e păstrat și se activează când liceul îl configurează. Audit S-6/#40.
        $preferences = [];
        foreach ((is_array($data['preferences'] ?? null) ? $data['preferences'] : []) as $type => $channels) {
            if (! in_array($type, $availableTypeValues, true)) {
                continue;
            }

            $preferences[$type] = array_values(array_intersect(
                is_array($channels) ? $channels : [],
                $channelValues,
            ));
        }

        $attributes = [
            'notification_locale' => $data['notification_locale'] ?? null,
            'notification_contacts' => array_filter($data['contacts'] ?? []),
            'notification_preferences' => $preferences,
        ];

        if ($emailIsFirstTimeSet) {
            $attributes['email'] = $submittedEmail;
        }

        $user->update($attributes);

        if ($emailIsFirstTimeSet) {
            // Adresă nouă, neverificată → resetăm marcajul de verificare (coerent cu pagina de staff).
            // email_verified_at nu e mass-assignable → forceFill.
            $user->forceFill(['email_verified_at' => null])->save();
        }

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
