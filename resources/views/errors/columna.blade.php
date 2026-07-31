@php
    use App\Support\Locale;

    /**
     * Pagina de eroare a ZONELOR AUTENTIFICATE (panou staff Filament + cabinet) și a oricărei
     * rute care nu trece prin Inertia.
     *
     * ⚠️ ÎMPRUMUTĂ DESIGNUL, NU FUNDALUL (corecție cerută de beneficiar, 01.08.2026): elementele
     * de identitate vin de pe pagina de eroare a site-ului (numeralul Cervino, busola
     * „debusolată" a lui 404, eyebrow-ul cu accent verde, emblema), dar SUPRAFEȚELE sunt ale
     * aplicației — `--background`/`--card` din `resources/css/app.css`, gheață în light și navy
     * profund în dark. Banda navy plină aparține SITE-ULUI; în interiorul cabinetului rupea
     * continuitatea vizuală. Tema urmează cookie-ul `appearance`, exact ca `app.blade.php`.
     *
     * ⚠️ CSS INLINE, ZERO dependențe de build: o pagină de eroare trebuie să se randeze corect
     * chiar când Vite/manifestul lipsește (o `ViteException` e exact genul de 500 care ajunge
     * aici). Din același motiv fonturile au fallback de sistem, iar logo-ul e un `<img>` simplu.
     *
     * Cele DOUĂ direcții de reluare a drumului: „Panoul meu" (dashboard-ul potrivit rolului)
     * și „Pagina principală website".
     */
    $status = (int) ($status ?? 404);
    $known = [403, 404, 419, 429, 500, 503];
    $key = in_array($status, $known, true) ? (string) $status : 'generic';

    // Limba: la o rută inexistentă middleware-ul de grup NU rulează, deci o rezolvăm aici
    // exact ca `SetUserLocale` (preferința contului → cookie → implicit).
    $user = auth('web')->user();
    $cookieLocale = request()->cookie('locale');
    $locale = ($user?->locale) ?? (is_string($cookieLocale) ? $cookieLocale : null) ?? Locale::default();
    app()->setLocale(Locale::isSupported($locale) ? $locale : Locale::default());

    // Tema: același cookie ca restul aplicației (necriptat — vezi bootstrap/app.php).
    $appearance = request()->cookie('appearance') ?? 'system';

    $title = (string) __('site.error_page.status.'.$key.'.title');
    $body = (string) __('site.error_page.status.'.$key.'.body');

    // Direcția 1 — DASHBOARD: contul autentificat merge acasă la el (panou sau cabinet, după
    // rol); vizitatorul anonim primește poarta către el (autentificarea).
    $dashboardUrl = $user !== null ? $user->homePath() : route('login');
    $dashboardLabel = (string) __('site.error_page.'.($user !== null ? 'dashboard' : 'login'));
    $dashboardHint = (string) __('site.error_page.'.($user !== null ? 'dashboard_hint' : 'login_hint'));

    // Direcția 2 — SITE PUBLIC, în limba curentă (rădăcina are prefix la ru/en).
    $websiteUrl = url(Locale::path('/'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" @class(['dark' => $appearance === 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, follow">
    <meta name="theme-color" content="#0f4d77">
    <title>{{ $status }} — {{ $title }} · {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">

    {{-- Tema, aplicată înainte de primul paint. Ordinea e a lui `use-appearance.tsx`:
         `localStorage.theme` (cheia PARTAJATĂ cu panoul Filament) → `localStorage.appearance`
         → cookie-ul de server. Fără primul, pagina de eroare ar rămâne deschisă în panoul pus
         pe întuneric — Filament nu scrie cookie-ul, ci doar localStorage. --}}
    <script>
        (function () {
            var mode;
            try {
                mode = localStorage.getItem('theme') || localStorage.getItem('appearance');
            } catch (e) {
                mode = null;
            }

            mode = mode || '{{ $appearance }}';

            var dark = mode === 'dark'
                || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    <style>
        /* Fonturile de brand (§11) — cu fallback de sistem dacă fișierele lipsesc. */
        @font-face { font-family: 'Proxima Nova'; font-weight: 400; font-display: swap; src: url('/fonts/proxima-nova-400.woff2') format('woff2'); }
        @font-face { font-family: 'Proxima Nova'; font-weight: 600; font-display: swap; src: url('/fonts/proxima-nova-600.woff2') format('woff2'); }
        @font-face { font-family: 'Proxima Nova'; font-weight: 700; font-display: swap; src: url('/fonts/proxima-nova-700.woff2') format('woff2'); }
        @font-face { font-family: 'Cervino'; font-weight: 800; font-display: swap; src: url('/fonts/cervino-800.woff2') format('woff2'); }

        /* Suprafețele APLICAȚIEI — valorile din `resources/css/app.css`, ca pagina să stea pe
           același fundal ca panoul și cabinetul (nu pe banda navy a site-ului). */
        :root {
            --background: oklch(0.9735 0.0075 235);
            --foreground: oklch(0.23 0.002 106.5);
            --card: oklch(0.999 0.004 106.5);
            --muted-foreground: oklch(0.517 0.002 106.5);
            --border: oklch(0.916 0.004 240);
            --navy: #0f4d77;
            --green: #9bc31e;
            --ink: #1d1d1c;
            --numeral: var(--navy);
            --emblem-bg: color-mix(in srgb, var(--navy) 7%, transparent);
            --shadow: 0 1px 2px rgba(15, 77, 119, 0.06), 0 8px 24px -12px rgba(15, 77, 119, 0.18);
        }

        .dark {
            --background: oklch(0.272 0.046 244.4);
            --foreground: oklch(0.985 0.001 106.5);
            --card: oklch(0.322 0.05 244.4);
            --muted-foreground: oklch(0.78 0.026 240);
            --border: oklch(0.39 0.045 244.4);
            /* Navy pe navy nu se citește: în modul întunecat numeralul și emblema trec pe
               albastrul deschis folosit deja în topbar-ul panoului. */
            --numeral: rgb(191 219 254);
            --emblem-bg: rgba(191, 219, 254, 0.10);
            --shadow: 0 1px 2px rgba(0, 0, 0, 0.25), 0 8px 24px -12px rgba(0, 0, 0, 0.45);
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body { margin: 0; padding: 0; background: var(--background); }

        body {
            min-height: 100svh;
            display: flex;
            flex-direction: column;
            font-family: 'Proxima Nova', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            color: var(--foreground);
            -webkit-font-smoothing: antialiased;
        }

        .shell {
            flex: 1 0 auto;
            width: 100%;
            /* 44rem, nu 40: la 40rem cele două carduri-buton rupeau titlul pe trei rânduri. */
            max-width: 44rem;
            margin: 0 auto;
            padding: clamp(1.5rem, 5vw, 3rem) clamp(1rem, 5vw, 2rem) clamp(2rem, 6vw, 3rem);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        /* Logo-ul e link către site → ținta lui tactilă atinge 44px (derivata responsivă din
           brandbook), chiar dacă imaginea în sine e mai mică. */
        .brand { display: inline-flex; align-items: center; min-height: 2.75rem; padding: 0.25rem; border-radius: 0.5rem; }
        .brand:focus-visible { outline: 2px solid var(--green); outline-offset: 2px; }
        .logo { display: block; height: clamp(2rem, 6.5vw, 2.5rem); width: auto; }
        .logo--dark { display: none; }
        .dark .logo--light { display: none; }
        .dark .logo--dark { display: block; }

        /* CARDUL — suprafața ridicată a aplicației (ca panourile din dashboard). */
        .card {
            margin-top: clamp(1.5rem, 5vw, 2.5rem);
            width: 100%;
            padding: clamp(1.5rem, 6vw, 2.75rem) clamp(1.25rem, 4vw, 2rem) clamp(1.75rem, 6vw, 2.5rem);
            border-radius: 1rem;
            background: var(--card);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }

        .emblem {
            display: grid;
            place-items: center;
            margin: 0 auto;
            width: clamp(3.75rem, 14vw, 4.5rem);
            height: clamp(3.75rem, 14vw, 4.5rem);
            border-radius: 1.25rem;
            background: var(--emblem-bg);
            color: var(--numeral);
        }

        .emblem svg { width: 62%; height: 62%; }

        /* Numeralul — Cervino (display), plafonat pe mobil: e EXPANDED, deci scala e prudentă. */
        .numeral {
            margin: clamp(0.875rem, 3vw, 1.25rem) 0 0;
            font-family: 'Cervino', 'Proxima Nova', ui-sans-serif, system-ui, sans-serif;
            font-weight: 800;
            font-size: clamp(3.25rem, 15vw, 6rem);
            line-height: 0.9;
            letter-spacing: 0.01em;
            color: var(--numeral);
        }

        .eyebrow {
            margin: clamp(0.625rem, 2.5vw, 0.875rem) 0 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.625rem;
            font-size: clamp(0.68rem, 2.5vw, 0.72rem);
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            /* Verdele rămâne ACCENT (regula de contrast §11: nu pe text mic peste alb) — aici e
               pe eyebrow, cu ruler-ele în jur; culoarea textului rămâne navy/deschis. */
            color: var(--numeral);
        }

        .eyebrow .rule { height: 1px; width: clamp(1.25rem, 6vw, 2.25rem); background: var(--border); }
        .eyebrow .star { color: var(--green); font-size: 0.9em; line-height: 1; }

        h1 {
            margin: clamp(0.75rem, 3vw, 1rem) 0 0;
            font-family: 'Cervino', 'Proxima Nova', ui-sans-serif, system-ui, sans-serif;
            font-weight: 800;
            /* Cervino e EXPANDED → pe telefon titlurile lungi ar da overflow; scala rămâne mică. */
            font-size: clamp(1.25rem, 4.6vw, 1.875rem);
            line-height: 1.2;
            color: var(--foreground);
            text-wrap: balance;
        }

        .lead {
            margin: clamp(0.625rem, 2.5vw, 0.875rem) auto 0;
            max-width: 44ch;
            font-size: clamp(0.9rem, 2.7vw, 1rem);
            line-height: 1.6;
            color: var(--muted-foreground);
        }

        /* Cele DOUĂ direcții — o coloană pe telefon, două de la 32rem în sus. */
        .paths {
            margin-top: clamp(1.5rem, 5vw, 2rem);
            display: grid;
            gap: 0.75rem;
            grid-template-columns: 1fr;
        }

        @media (min-width: 32rem) {
            .paths { grid-template-columns: 1fr 1fr; gap: 0.875rem; }
        }

        .path {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            /* Țintă tactilă generoasă (≥44px, derivata responsivă din brandbook). */
            min-height: 3.5rem;
            padding: 0.875rem 1rem;
            border-radius: 0.75rem;
            text-align: left;
            text-decoration: none;
            color: var(--foreground);
            background: transparent;
            border: 1px solid var(--border);
            transition: background-color 160ms ease, border-color 160ms ease, transform 160ms ease;
        }

        .path:hover { border-color: color-mix(in srgb, var(--navy) 35%, var(--border)); transform: translateY(-1px); }
        .path:focus-visible { outline: 2px solid var(--green); outline-offset: 2px; }

        /* Direcția PRIMARĂ (dashboard): navy solid — butonul principal al aplicației. */
        .path--primary { background: var(--navy); border-color: var(--navy); color: #fff; }
        .path--primary:hover { background: #10598b; border-color: #10598b; }
        .path--primary .path__icon { background: rgba(255, 255, 255, 0.16); color: #fff; }
        .path--primary .path__hint { color: rgba(255, 255, 255, 0.78); }

        .path__icon {
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            width: 2.125rem;
            height: 2.125rem;
            border-radius: 0.5rem;
            background: var(--emblem-bg);
            color: var(--numeral);
        }

        .path__icon svg { width: 1.0625rem; height: 1.0625rem; }

        .path__text { min-width: 0; }
        .path__title { display: block; font-weight: 700; font-size: 0.9375rem; line-height: 1.3; }
        .path__hint { display: block; margin-top: 0.125rem; font-size: 0.8125rem; line-height: 1.35; color: var(--muted-foreground); }

        .foot {
            flex: 0 0 auto;
            padding: 0 1rem clamp(1rem, 4vw, 1.75rem);
            text-align: center;
            font-size: 0.8125rem;
            color: var(--muted-foreground);
        }

        .foot a { display: inline-flex; align-items: center; min-height: 2.75rem; padding: 0 0.25rem; color: var(--muted-foreground); text-decoration: none; }
        .foot a:hover { color: var(--foreground); text-decoration: underline; }
        .foot a:focus-visible { outline: 2px solid var(--green); outline-offset: 2px; border-radius: 0.375rem; }

        /* Acul busolei caută nordul, eratic (404 / necunoscut) — ca pe pagina publică. */
        @keyframes err-needle {
            0%, 100% { transform: rotate(-14deg); }
            18% { transform: rotate(128deg); }
            34% { transform: rotate(52deg); }
            56% { transform: rotate(206deg); }
            74% { transform: rotate(96deg); }
            88% { transform: rotate(-4deg); }
        }

        @keyframes err-pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.06); opacity: 0.85; }
        }

        @media (prefers-reduced-motion: no-preference) {
            .needle { transform-origin: 50px 50px; animation: err-needle 7s cubic-bezier(0.45, 0, 0.2, 1) infinite; }
            .pulse { transform-origin: center; animation: err-pulse 2.6s ease-in-out infinite; }
        }
    </style>
</head>
<body>
    <main class="shell">
        <a class="brand" href="{{ $websiteUrl }}" aria-label="{{ config('app.name') }}">
            <img class="logo logo--light" src="/images/logo/columna-horizontal.png" alt="{{ config('app.name') }}">
            <img class="logo logo--dark" src="/images/logo/columna-horizontal-white.png" alt="{{ config('app.name') }}">
        </a>

        <div class="card">
            <span class="emblem">
                @if ($key === '404' || $key === 'generic')
                    {{-- Busolă „debusolată": acul caută nordul — ilustrația 404 de pe site. --}}
                    <svg viewBox="0 0 100 100" fill="none" aria-hidden="true">
                        <circle cx="50" cy="50" r="45" stroke="currentColor" stroke-opacity="0.35" stroke-width="3" />
                        <g stroke="currentColor" stroke-opacity="0.3" stroke-width="3" stroke-linecap="round">
                            <line x1="50" y1="7" x2="50" y2="15" />
                            <line x1="50" y1="85" x2="50" y2="93" />
                            <line x1="7" y1="50" x2="15" y2="50" />
                            <line x1="85" y1="50" x2="93" y2="50" />
                        </g>
                        <g class="needle">
                            <path d="M50 17 L56.5 50 L50 45.5 L43.5 50 Z" fill="#9bc31e" />
                            <path d="M50 83 L43.5 50 L50 54.5 L56.5 50 Z" fill="currentColor" fill-opacity="0.45" />
                        </g>
                        <circle cx="50" cy="50" r="4.5" fill="currentColor" />
                    </svg>
                @elseif ($key === '403')
                    <svg class="pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="11" width="18" height="11" rx="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                @elseif ($key === '419')
                    <svg class="pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 7v5l3 2" />
                    </svg>
                @elseif ($key === '429')
                    <svg class="pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                        <path d="M3.6 15a9 9 0 1 1 16.8 0" />
                        <path d="m14 10 3-3" />
                    </svg>
                @elseif ($key === '503')
                    <svg class="pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14.7 6.3a4 4 0 0 0 5 5l-9.4 9.4a2.1 2.1 0 0 1-3-3l9.4-9.4a4 4 0 0 0-2-2Z" />
                    </svg>
                @else
                    <svg class="pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="3" width="20" height="8" rx="2" />
                        <rect x="2" y="13" width="20" height="8" rx="2" />
                        <path d="M6 7h.01M6 17h.01" />
                        <path d="m15 15 4 4m0-4-4 4" />
                    </svg>
                @endif
            </span>

            <p class="numeral">{{ $status }}</p>

            <p class="eyebrow">
                <span class="rule" aria-hidden="true"></span>
                <span class="star" aria-hidden="true">&#10022;</span>
                {{ __('site.error_page.eyebrow') }} {{ $status }}
                <span class="star" aria-hidden="true">&#10022;</span>
                <span class="rule" aria-hidden="true"></span>
            </p>

            <h1>{{ $title }}</h1>
            <p class="lead">{{ $body }}</p>

            {{-- CELE DOUĂ DIRECȚII: panoul propriu (sau autentificarea) și site-ul public. --}}
            <nav class="paths" aria-label="{{ __('site.error_page.helpful') }}">
                <a class="path path--primary" href="{{ $dashboardUrl }}">
                    <span class="path__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="9" rx="1.5" />
                            <rect x="14" y="3" width="7" height="5" rx="1.5" />
                            <rect x="14" y="12" width="7" height="9" rx="1.5" />
                            <rect x="3" y="16" width="7" height="5" rx="1.5" />
                        </svg>
                    </span>
                    <span class="path__text">
                        <span class="path__title">{{ $dashboardLabel }}</span>
                        <span class="path__hint">{{ $dashboardHint }}</span>
                    </span>
                </a>

                <a class="path" href="{{ $websiteUrl }}">
                    <span class="path__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m3 10 9-7 9 7v9a2 2 0 0 1-2 2h-4v-6H9v6H5a2 2 0 0 1-2-2Z" />
                        </svg>
                    </span>
                    <span class="path__text">
                        <span class="path__title">{{ __('site.error_page.website') }}</span>
                        <span class="path__hint">{{ __('site.error_page.website_hint') }}</span>
                    </span>
                </a>
            </nav>
        </div>
    </main>

    <footer class="foot">
        <a href="{{ $websiteUrl }}">{{ config('app.name') }}</a> · Chișinău
    </footer>
</body>
</html>
