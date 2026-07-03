<?php

namespace App\Providers\Filament;

use App\Filament\MultiFactor\AppAuthentication;
use App\Models\Admin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panoul „Studio de conținut" (/studio) — administrarea conținutului de site (blog, actualități,
 * galerie, bibliotecă). COMPLET IZOLAT de panoul academic /admin:
 *  - guard propriu `admin` (tabelul `admins`, {@see Admin}) — fără RBAC, fără PII elevi;
 *  - descoperire de resurse pe namespace SEPARAT (`App\Filament\Content\*`) → nu preia cele 27 de
 *    resurse academice din `App\Filament\Resources`;
 *  - un singur cont, fără înregistrare; MFA (TOTP) obligatoriu implicit;
 *  - afișare exclusiv RO (fără comutator de limbă) — vezi CLAUDE.md.
 */
class ContentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('content')
            ->path('studio')
            // Guard izolat: sesiune + provider separate de `web`.
            ->authGuard('admin')
            // Doar login — auto-înregistrarea NU e activată (fără ->registration()).
            ->login()
            ->profile(isSimple: false)
            // MFA nativ Filament (TOTP + coduri de recuperare). Obligatoriu în producție; oprit în teste.
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->brandName('Columna · Studio'),
            ], isRequired: (bool) config('cms.require_mfa', true))
            ->brandName('Columna · Studio de conținut')
            ->brandLogo(fn (): string => asset('images/logo/columna-wordmark.webp'))
            ->darkModeBrandLogo(fn (): string => asset('images/logo/columna-wordmark-white.webp'))
            // +20% față de baza de 2.25rem (2.25 × 1.2). Per-panel — nu afectă /admin.
            ->brandLogoHeight('2.7rem')
            ->favicon(asset('favicon.ico'))
            // Panoul de conținut NU trebuie indexat de motoarele de căutare.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<meta name="robots" content="noindex, nofollow">',
            )
            // Ceas + dată LIVE în bara de sus, chiar lângă avatar (dreapta). Render hook DOAR pe acest
            // panou → nu apare în /admin.
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => self::topbarClockHtml(),
            )
            // Aceeași temă de brand ca panoul academic (Proxima Nova + tokenuri navy/verde).
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => '#0f4d77',
                'brand-green' => '#9bc31e',
            ])
            ->navigationGroups([
                'Conținut',
                'Setări',
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('Vezi site-ul')
                    ->url('/', shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedGlobeAlt),
            ])
            // Namespace SEPARAT — izolează CMS-ul de resursele academice.
            ->discoverResources(in: app_path('Filament/Content/Resources'), for: 'App\Filament\Content\Resources')
            ->discoverPages(in: app_path('Filament/Content/Pages'), for: 'App\Filament\Content\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Content/Widgets'), for: 'App\Filament\Content\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Ceas + dată LIVE pentru bara de sus. Actualizare 100% client-side prin Alpine (x-init +
     * setInterval), în locale RO — fără round-trip Livewire. Stilizat prin `.cms-topbar-clock*`
     * în theme.css. Markupul nu conține `$`, deci nowdoc-ul e sigur (fără interpolare PHP).
     */
    private static function topbarClockHtml(): string
    {
        return <<<'HTML'
            <div class="cms-topbar-clock" role="timer" x-data="{ time: '', date: '', tick() { const d = new Date(); this.time = d.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit', second: '2-digit' }); this.date = d.toLocaleDateString('ro-RO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); } }" x-init="tick(); window.setInterval(() => tick(), 1000)">
                <span class="cms-topbar-clock-time" x-text="time"></span>
                <span class="cms-topbar-clock-date" x-text="date"></span>
            </div>
            HTML;
    }
}
