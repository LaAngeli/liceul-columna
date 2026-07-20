<?php

namespace App\Http\Controllers;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\Student;
use App\Support\SchoolCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
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
        $user = $request->user('web');

        $records = $user->notifications()->latest()->limit(50)->get();

        // Elevii care NU mai există (fișă arhivată) → link-urile „cabinet/elev/{id}" din notificările
        // vechi ar da 404 (binding-ul implicit exclude soft-deleted). Neutralizăm URL-ul pentru ei ca
        // să nu trimitem familia spre pagini moarte (#37).
        $missingStudentIds = self::referencedMissingStudentIds($records);

        $notifications = $records
            ->map(function (DatabaseNotification $notification) use ($missingStudentIds): array {
                $url = $notification->data['url'] ?? null;
                $studentId = self::studentIdFromUrl(is_string($url) ? $url : null);

                return [
                    'id' => $notification->id,
                    'type' => $notification->data['type'] ?? null,
                    'title' => $notification->data['title'] ?? '',
                    'body' => $notification->data['body'] ?? '',
                    'url' => ($studentId !== null && in_array($studentId, $missingStudentIds, true)) ? null : $url,
                    'read' => $notification->read_at !== null,
                    'at' => SchoolCalendar::local($notification->created_at)?->format('d.m.Y H:i'),
                ];
            })->all();

        return Inertia::render('cabinet/notifications', [
            'notifications' => $notifications,
            // Totalul REAL de necitite (badge-ul din header) — lista de mai sus e plafonată la 50, deci
            // butonul „Marchează tot" se afișează după acest total, nu după cele 50 afișate (#37).
            'unreadTotal' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Extrage id-ul elevului dintr-un URL de notificare „…/cabinet/elev/{id}" (sau null).
     */
    private static function studentIdFromUrl(?string $url): ?int
    {
        if ($url === null) {
            return null;
        }

        return preg_match('#/cabinet/elev/(\d+)#', $url, $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * Id-urile de elev referite de notificări care NU mai există (fișă arhivată/ștearsă).
     *
     * @param  Collection<int, DatabaseNotification>  $records
     * @return list<int>
     */
    private static function referencedMissingStudentIds(Collection $records): array
    {
        $ids = $records
            ->map(fn (DatabaseNotification $n): ?int => self::studentIdFromUrl(is_string($n->data['url'] ?? null) ? $n->data['url'] : null))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $existing = Student::query()->whereIn('id', $ids->all())->pluck('id')->all();

        return array_values($ids->reject(fn (int $id): bool => in_array($id, $existing, true))->all());
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        // Folosim API-ul DatabaseNotification (la fel ca markAllRead) ca să declanșăm event-urile
        // modelului — orice listener viitor pe „marcat citit" (sync cross-device, analytics) prinde
        // ambele căi uniform. null-safe = no-op silențios dacă id-ul nu există / nu aparține userului.
        $request->user('web')->notifications()->whereKey($notification)->first()?->markAsRead();

        return back();
    }

    /**
     * Deschide o notificare: marcarea „citit" e EFECT SECUNDAR al accesării (idempotent), iar
     * răspunsul e redirecția către țintă — un singur click face ambele. Țintele moarte primesc
     * aceeași neutralizare ca în index (elev arhivat / fără URL → rămâi pe inbox).
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user('web')->notifications()->whereKey($notification)->first();

        // Id inexistent sau al altui utilizator → înapoi la inbox, fără efecte și fără a divulga
        // dacă id-ul există (scoping-ul pe relația userului face oricum imposibilă citirea străină).
        if ($record === null) {
            return redirect()->route('cabinet.notifications');
        }

        $record->markAsRead();

        $url = $record->data['url'] ?? null;

        // Doar ținte RELATIVE (așa generăm toate URL-urile de notificare) — o valoare absolută
        // strecurată vreodată în payload nu poate transforma ruta într-un open-redirect.
        if (! is_string($url) || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return redirect()->route('cabinet.notifications');
        }

        $studentId = self::studentIdFromUrl($url);
        if ($studentId !== null && ! Student::query()->whereKey($studentId)->exists()) {
            return redirect()->route('cabinet.notifications');
        }

        return redirect($url);
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
