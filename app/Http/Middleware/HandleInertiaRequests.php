<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\Locale;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'canAccessAdmin' => $request->user()?->hasAnyRole(UserRole::panelRoleValues()) ?? false,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Badge de notificări necitite (lazy: query doar pentru utilizatori autentificați).
            'notificationsUnread' => fn (): int => $request->user()?->unreadNotifications()->count() ?? 0,
            // Lazy: share() rulează înaintea middleware-ului de limbă, deci rezolvăm
            // limba la randare (după ce locale-ul a fost setat).
            'locale' => fn (): string => app()->getLocale(),
            'locales' => Locale::supported(),
            // Toate limbile, ca interfața să poată rezerva lățimea celei mai lungi
            // variante (butoanele nu-și schimbă dimensiunea la traducere).
            'messages' => fn (): array => collect(array_keys(Locale::supported()))
                ->mapWithKeys(fn (string $code): array => [$code => trans('site', [], $code)])
                ->all(),
        ];
    }
}
