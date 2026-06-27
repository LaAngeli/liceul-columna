<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\SetUserLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\View;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Fără login separat: oaspeții pe /admin sunt trimiși la autentificarea unică (/login).
            ->brandName('Liceul Columna · Administrare')
            // Pagina Profil (nume/email/parolă/2FA), în layout cu sidebar (isSimple: false).
            ->profile(EditProfile::class, isSimple: false)
            // Clopoțelul de notificări din panou (spec §5): personalul își primește notificările „de
            // nișă" (database) chiar în dashboard, ca structură proprie de recepție.
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::Amber,
            ])
            // Ordinea grupurilor din sidebar; „Setări" la final (oglindește cabinetul elev/părinte).
            ->navigationGroups([
                'Catalog',
                'Configurare',
                'Comunicare',
                'Admitere',
                'Administrare',
                'Setări',
            ])
            // Pagina de profil Filament e legată de meniul user, nu de sidebar (`->profile()` nu o
            // adaugă în navigație). Adăugăm manual linkul „Setări → Profil" către `getProfileUrl()`.
            ->navigationItems([
                NavigationItem::make('Profil')
                    ->group('Setări')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (): ?string => filament()->getProfileUrl())
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.auth.profile'))
                    ->sort(1),
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('Vezi site-ul public')
                    ->url('/', shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedGlobeAlt),
            ])
            // Pastilă segmentată de limbă (RO/RU/EN), inserată în meniul user după item-ul de profil.
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_AFTER,
                fn (): string => View::make('filament.topbar.language-switcher')->render(),
            )
            // Ceas+dată live (client-side) + badge cu rolul logat, în topbar — chiar înaintea avatarului
            // (USER_MENU_BEFORE randează ÎN interiorul clusterului din dreapta, nu după avatar).
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => View::make('filament.topbar.live-datetime')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                SetUserLocale::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordChanged::class,
            ]);
    }
}
