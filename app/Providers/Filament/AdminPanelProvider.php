<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Absences\AbsenceResource;
use App\Filament\Resources\Grades\GradeResource;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\SetUserLocale;
use App\Livewire\PanelNotifications;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
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
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Fără login separat: oaspeții pe /admin sunt trimiși la autentificarea unică (/login).
            ->brandName('Liceul Columna · Administrare')
            // Logo de brand în topbar (variația „Long Orizontal" — light/dark).
            ->brandLogo(fn (): string => asset('images/logo/columna-wordmark.webp'))
            ->darkModeBrandLogo(fn (): string => asset('images/logo/columna-wordmark-white.webp'))
            ->brandLogoHeight('2.25rem')
            // Click pe logo → homepage-ul site-ului public (convenție „logo = acasă"). homeUrl
            // controlează DOAR linkul logo-ului (sidebar + topbar, via filament()->getHomeUrl()); NU
            // afectează redirectul de login (LoginResponse folosește getUrl() = /admin), deci staff-ul
            // aterizează tot pe dashboard. Meniul user păstrează separat „Vezi site-ul" (tab nou).
            ->homeUrl('/')
            // Favicon (emblema bicoloră a brand-ului — vezi public/favicon.ico generat din columna-crest-color).
            ->favicon(asset('favicon.ico'))
            // Pagina Profil (nume/email/parolă/2FA), în layout cu sidebar (isSimple: false).
            ->profile(EditProfile::class, isSimple: false)
            // Clopoțelul de notificări din panou (spec §5): personalul își primește notificările „de
            // nișă" (database) chiar în dashboard, ca structură proprie de recepție. Componenta
            // PROPRIE (retenție 2026-07-21): fără ștergere per notificare / „Șterge tot" — doar
            // active; arhiva e pe pagina „Notificările mele".
            ->databaseNotifications()
            ->databaseNotificationsLivewireComponent(PanelNotifications::class)
            ->databaseNotificationsPolling('30s')
            // Brand (§11): navy #0f4d77 = PRIMAR; verdele #9bc31e = ACCENT, înregistrat ca o
            // culoare custom „brand-green" pentru folosire țintită (badge-uri/butoane secundare).
            // Filament generează automat cele 11 shades din culoarea hex.
            ->colors([
                'primary' => '#0f4d77',
                'brand-green' => '#9bc31e',
            ])
            // Ordinea grupurilor din sidebar; „Setări" la final (oglindește cabinetul elev/părinte).
            // Etichetele se localizează la fiecare request (SetUserLocale rulează înainte de panel()):
            // Filament grupează pe label exact, deci resursele returnează aceeași cheie tradusă.
            ->navigationGroups([
                __('panel.nav.groups.catalog'),
                __('panel.nav.groups.approvals'),
                __('panel.nav.groups.configuration'),
                __('panel.nav.groups.communication'),
                __('panel.nav.groups.admission'),
                __('panel.nav.groups.administration'),
                __('panel.nav.groups.documents'),
                __('panel.nav.groups.settings'),
            ])
            // Pagina de profil Filament e legată de meniul user, nu de sidebar (`->profile()` nu o
            // adaugă în navigație). Adăugăm manual linkul „Setări → Profil" către `getProfileUrl()`.
            ->navigationItems([
                NavigationItem::make(__('panel.nav.items.profile'))
                    ->group(__('panel.nav.groups.settings'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (): ?string => filament()->getProfileUrl())
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.auth.profile'))
                    ->sort(1),
            ])
            ->userMenuItems([
                // Quick-create pe fluxul zilnic de mare volum al profesorului (§spec catalog).
                // Gated pe canCreate() — administratorul operațional/tehnic NU vede aceste linkuri
                // pentru că nu pot crea note/absențe (vezi GradeResource::canCreate, §3.2).
                MenuItem::make()
                    ->label(fn (): string => __('panel.nav.items.new_grade'))
                    ->url(fn (): string => GradeResource::getUrl('create'))
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->visible(fn (): bool => GradeResource::canCreate()),
                MenuItem::make()
                    ->label(fn (): string => __('panel.nav.items.new_absence'))
                    ->url(fn (): string => AbsenceResource::getUrl('create'))
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->visible(fn (): bool => AbsenceResource::canCreate()),
                MenuItem::make()
                    ->label(fn (): string => __('panel.nav.items.view_site'))
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
            // Componenta Alpine a calendarului de interval (bara temporală, „Personalizat") —
            // înregistrată GLOBAL, pe `alpine:init`: markup-ul ei apare prin morph abia la
            // activarea modului, moment în care un @script local ar rula prea târziu (elementul
            // e procesat de Alpine înainte) — panou mort, raportat 2026-08-01 pe cantina.
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => View::make('filament.catalog.partials.date-range-component')->render(),
            )
            // Meniul mobil (audit responsiv 2026-07-21): sub `lg` sidebar-ul deschis acoperă tot
            // ecranul, iar închiderea stă în ACEEAȘI bară cu logo-ul (colțul dreapta-sus) — X-ul
            // implicit din topbar rămânea ACOPERIT de sidebar-ul full-width. Stilurile în theme.css.
            ->renderHook(
                PanelsRenderHook::SIDEBAR_LOGO_AFTER,
                fn (): string => View::make('filament.topbar.sidebar-mobile-close')->render(),
            )
            // Afordanțe interactive (audit UI/UX 2026-07-17): Tailwind v4 a scos `cursor: pointer`
            // de pe butoane, deci cardurile navigatoarelor, pastilele vederilor și rândurile-buton
            // arătau săgeată deși sunt apăsabile. Regulă de PANOU injectată în <head> (supraviețuiește
            // morphing-ului Livewire; deliberat NU în theme.css): tot ce e apăsabil arată mâna, ce e
            // dezactivat arată interdicția + micro-hover pe filtrele/evenimentele calendarului
            // (singurele interactive fără feedback — restul claselor cal-*/navigatoarele îl au deja).
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => '<style>'
                    .'button:not(:disabled),[role="button"]:not([aria-disabled="true"]),summary,input[type="checkbox"]:not(:disabled),input[type="radio"]:not(:disabled){cursor:pointer}'
                    .'button:disabled,[aria-disabled="true"]{cursor:not-allowed}'
                    .'.cal-tab:not([aria-pressed="true"]):hover,.cal-chip[aria-pressed="false"]:hover,.cal-chip-all:hover{opacity:1}'
                    .'.cal-pill:hover{filter:brightness(.94)}.dark .cal-pill:hover{filter:brightness(1.15)}'
                    .'.cal-daybar:hover{background:color-mix(in srgb,currentColor 4%,transparent)}'
                    // Retenție notificări (2026-07-21): X-ul per notificare din CLOPOȚEL ștergea din
                    // bază — ascuns doar în modalul clopoțelului (.fi-no-database); toast-urile
                    // efemere își păstrează închiderea. Serverul e oricum neutralizat
                    // (PanelNotifications) — CSS-ul doar aliniază UI-ul cu realitatea.
                    .'.fi-no-database .fi-no-notification-close-btn{display:none}'
                    // Etichete inline lipite de câmpul lor (raportat 2026-08-01 pe „Date de
                    // contact"): containerul etichetei e de lățime FIXĂ, deci un text scurt
                    // („Viber") lăsa 71px de gol față de 16px la un text lung („Adresă de email").
                    // Se strânge la conținut DOAR unde secțiunea o cere explicit — acolo unde
                    // câmpurile stau pe coloane diferite și lățimea fixă nu aliniază nimic.
                    // Cauza reală: câmpul cu etichetă inline e el însuși o GRILĂ care moștenește
                    // coloanele secțiunii (`columns(3)` → `88px 88px 88px`), iar eticheta ocupă o
                    // coloană întreagă indiferent cât e textul. De aici golul: 88−33+16 = 71px la
                    // „Viber", față de 16px la „Adresă de email", care umple coloana. Trecem câmpul
                    // pe flex: eticheta ia exact cât scrie, inputul umple restul, iar distanța
                    // rămâne gap-ul — aceeași peste tot.
                    // Între `sm` și `lg` câmpurile se stivuiesc dar eticheta rămâne inline: acolo
                    // flex-ul ar porni fiecare input din alt loc (zigzag), deci păstrăm coloana fixă
                    // și doar împingem eticheta la dreapta ei — distanța devine tot gap-ul, iar
                    // inputurile rămân aliniate. Sub `sm` Filament trece eticheta DEASUPRA câmpului,
                    // deci nu e nimic de aliniat pe orizontală.
                    .'@media (min-width:640px) and (max-width:1023.98px){'
                    .'.fi-inline-label-tight .fi-fo-field-label-col{justify-items:end;text-align:right}'
                    .'}'
                    // Planificatorul cantinei pe telefon (cerință 2026-08-01): singura lui acțiune
                    // de antet stă ÎN DREPTUL titlului, nu pe un rând propriu dedesubt — un buton
                    // scurt lângă un titlu scurt încape, iar rândul câștigat e primul card de meniu.
                    // Titlul primește `min-width:0` ca să se scurteze el, nu să împingă butonul.
                    .'@media (max-width:639.98px){'
                    .'.fi-canteen-planner .fi-header{flex-direction:row;align-items:center;justify-content:space-between;gap:.75rem}'
                    .'.fi-canteen-planner .fi-header>:first-child{min-width:0}'
                    .'.fi-canteen-planner .fi-header .fi-ac{flex-shrink:0}'
                    .'}'
                    .'@media (min-width:1024px){'
                    .'.fi-inline-label-tight .fi-fo-field-has-inline-label{display:flex;align-items:center;gap:1rem}'
                    .'.fi-inline-label-tight .fi-fo-field-has-inline-label>.fi-fo-field-label-col{display:block;width:auto;flex:0 0 auto}'
                    .'.fi-inline-label-tight .fi-fo-field-has-inline-label>.fi-fo-field-content-col{flex:1 1 auto;min-width:0}'
                    .'}'
                    .'</style>',
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            // Widget-urile custom se descoperă automat din app/Filament/Widgets. NU înregistrăm
            // AccountWidget-ul implicit Filament: e redundant (avatar + rol + deconectare există deja
            // în meniul user din colțul dreapta-sus) și adăuga un card „Bun venit" derutant pe dashboard.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
                EnsureAccountActive::class,
                EnsurePasswordChanged::class,
                EnsureTwoFactorEnrolled::class,
            ]);
    }
}
