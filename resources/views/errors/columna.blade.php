@php
    use App\Support\Locale;

    /**
     * Pagina de eroare a ZONELOR AUTENTIFICATE (panou staff Filament + cabinet) și a oricărei
     * rute care nu trece prin Inertia. Site-ul public are varianta lui, bogată
     * ({@see resources/js/pages/public/error.tsx}) — aceasta reia LIMBAJUL VIZUAL al aceleia
     * (navy de brand, numeral Cervino, busolă „debusolată", stele cu patru colțuri), dar
     * STANDALONE: fără chrome de site sau de panou, potrivită oriunde.
     *
     * ⚠️ CSS INLINE, ZERO dependențe de build: o pagină de eroare trebuie să se randeze corect
     * chiar când Vite/manifestul lipsește (o `ViteException` e exact genul de 500 care ajunge
     * aici). Din același motiv fonturile au fallback de sistem, iar logo-ul e un `<img>` simplu.
     *
     * Cele DOUĂ direcții de reluare a drumului (cerința beneficiarului, 01.08.2026):
     * „Panoul meu" (dashboard-ul potrivit rolului) și „Pagina principală website".
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
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, follow">
    <meta name="theme-color" content="#0f4d77">
    <title>{{ $status }} — {{ $title }} · {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">

    <style>
        /* Fonturile de brand (§11) — cu fallback de sistem dacă fișierele lipsesc. */
        @font-face { font-family: 'Proxima Nova'; font-weight: 400; font-display: swap; src: url('/fonts/proxima-nova-400.woff2') format('woff2'); }
        @font-face { font-family: 'Proxima Nova'; font-weight: 600; font-display: swap; src: url('/fonts/proxima-nova-600.woff2') format('woff2'); }
        @font-face { font-family: 'Proxima Nova'; font-weight: 700; font-display: swap; src: url('/fonts/proxima-nova-700.woff2') format('woff2'); }
        @font-face { font-family: 'Cervino'; font-weight: 800; font-display: swap; src: url('/fonts/cervino-800.woff2') format('woff2'); }

        :root {
            --navy: #0f4d77;
            --navy-deep: #0b3a5b;
            --green: #9bc31e;
            --ink: #1d1d1c;
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body { margin: 0; padding: 0; }

        body {
            min-height: 100svh;
            display: flex;
            flex-direction: column;
            font-family: 'Proxima Nova', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            color: #fff;
            background: radial-gradient(120% 90% at 50% 0%, var(--navy) 0%, var(--navy-deep) 70%, #082d47 100%);
            -webkit-font-smoothing: antialiased;
        }

        /* Textură de brand: stele cu patru colțuri, rare (pattern-urile §11 — pe mobil rămân rare). */
        .bg {
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.5;
            background-image:
                radial-gradient(circle at 12% 22%, rgba(255, 255, 255, 0.10) 0 2px, transparent 2px),
                radial-gradient(circle at 82% 16%, rgba(155, 195, 30, 0.16) 0 3px, transparent 3px),
                radial-gradient(circle at 68% 74%, rgba(255, 255, 255, 0.08) 0 2px, transparent 2px),
                radial-gradient(circle at 24% 82%, rgba(155, 195, 30, 0.12) 0 2px, transparent 2px);
        }

        .shell {
            position: relative;
            flex: 1 0 auto;
            width: 100%;
            max-width: 46rem;
            margin: 0 auto;
            padding: clamp(1.5rem, 5vw, 3rem) clamp(1rem, 5vw, 2rem) clamp(2rem, 6vw, 3.5rem);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        /* Logo-ul e link către site → ținta lui tactilă trebuie să atingă 44px (derivata
           responsivă din brandbook), chiar dacă imaginea în sine e mai mică. */
        .brand { display: inline-flex; align-items: center; min-height: 2.75rem; padding: 0.25rem; border-radius: 0.5rem; }
        .brand:focus-visible { outline: 2px solid var(--green); outline-offset: 2px; }
        .logo { display: block; height: clamp(2.25rem, 7vw, 2.75rem); width: auto; }

        .emblem {
            margin-top: clamp(1.75rem, 6vw, 3rem);
            display: grid;
            place-items: center;
            width: clamp(4.25rem, 16vw, 5rem);
            height: clamp(4.25rem, 16vw, 5rem);
            border-radius: 1.375rem;
            background: rgba(255, 255, 255, 0.06);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.15);
        }

        .emblem svg { width: 62%; height: 62%; }

        /* Numeralul — Cervino (display), plafonat pe mobil: e EXPANDED, deci scala e prudentă. */
        .numeral {
            margin: clamp(1rem, 4vw, 1.5rem) 0 0;
            font-family: 'Cervino', 'Proxima Nova', ui-sans-serif, system-ui, sans-serif;
            font-weight: 800;
            font-size: clamp(3.75rem, 17vw, 8rem);
            line-height: 0.9;
            letter-spacing: 0.01em;
        }

        .eyebrow {
            margin-top: clamp(0.75rem, 3vw, 1rem);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.625rem;
            font-size: clamp(0.7rem, 2.6vw, 0.75rem);
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--green);
        }

        .eyebrow .rule { height: 1px; width: clamp(1.25rem, 6vw, 2.5rem); background: rgba(255, 255, 255, 0.3); }

        h1 {
            margin: clamp(0.875rem, 3vw, 1.25rem) 0 0;
            font-family: 'Cervino', 'Proxima Nova', ui-sans-serif, system-ui, sans-serif;
            font-weight: 800;
            /* Cervino e EXPANDED → pe telefon titlurile lungi ar da overflow; scala rămâne mică. */
            font-size: clamp(1.375rem, 5vw, 2.25rem);
            line-height: 1.15;
            text-wrap: balance;
        }

        .lead {
            margin: clamp(0.75rem, 2.5vw, 1rem) auto 0;
            max-width: 46ch;
            font-size: clamp(0.95rem, 2.8vw, 1.0625rem);
            line-height: 1.65;
            color: rgba(255, 255, 255, 0.85);
        }

        /* Cele DOUĂ direcții — carduri-buton: o coloană pe telefon, două de la 34rem în sus. */
        .paths {
            margin-top: clamp(1.75rem, 6vw, 2.5rem);
            width: 100%;
            display: grid;
            gap: 0.875rem;
            grid-template-columns: 1fr;
        }

        @media (min-width: 34rem) {
            .paths { grid-template-columns: 1fr 1fr; gap: 1rem; }
        }

        .path {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            /* Țintă tactilă generoasă (≥44px, derivata responsivă din brandbook). */
            min-height: 3.5rem;
            padding: 1rem 1.125rem;
            border-radius: 0.875rem;
            text-align: left;
            text-decoration: none;
            color: #fff;
            background: rgba(255, 255, 255, 0.07);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.14);
            transition: background-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
        }

        .path:hover { background: rgba(255, 255, 255, 0.12); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.28); transform: translateY(-1px); }
        .path:focus-visible { outline: 2px solid var(--green); outline-offset: 3px; }

        /* Direcția PRIMARĂ (dashboard) — verdele de brand ca accent, pe fundal solid. */
        .path--primary { background: var(--green); color: var(--ink); box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.08); }
        .path--primary:hover { background: #aad427; }
        .path--primary .path__icon { background: rgba(0, 0, 0, 0.12); color: var(--ink); }
        .path--primary .path__hint { color: rgba(29, 29, 28, 0.72); }

        .path__icon {
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.625rem;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .path__icon svg { width: 1.125rem; height: 1.125rem; }

        .path__text { min-width: 0; }
        .path__title { display: block; font-weight: 700; font-size: 0.9375rem; line-height: 1.3; }
        .path__hint { display: block; margin-top: 0.125rem; font-size: 0.8125rem; line-height: 1.35; color: rgba(255, 255, 255, 0.72); }

        .foot {
            flex: 0 0 auto;
            padding: 0 1rem clamp(1.25rem, 4vw, 2rem);
            text-align: center;
            font-size: 0.8125rem;
            color: rgba(255, 255, 255, 0.55);
        }

        .foot a { display: inline-flex; align-items: center; min-height: 2.75rem; padding: 0 0.25rem; color: rgba(255, 255, 255, 0.75); text-decoration: none; }
        .foot a:hover { color: #fff; text-decoration: underline; }
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
    <div class="bg" aria-hidden="true"></div>

    <main class="shell">
        <a class="brand" href="{{ $websiteUrl }}" aria-label="{{ config('app.name') }}">
            <img class="logo" src="/images/logo/columna-horizontal-white.png" alt="{{ config('app.name') }}">
        </a>

        <span class="emblem">
            @if ($key === '404' || $key === 'generic')
                {{-- Busolă „debusolată": acul caută nordul — ilustrația 404 de pe site. --}}
                <svg viewBox="0 0 100 100" fill="none" aria-hidden="true">
                    <circle cx="50" cy="50" r="45" stroke="#fff" stroke-opacity="0.45" stroke-width="3" />
                    <g stroke="#fff" stroke-opacity="0.4" stroke-width="3" stroke-linecap="round">
                        <line x1="50" y1="7" x2="50" y2="15" />
                        <line x1="50" y1="85" x2="50" y2="93" />
                        <line x1="7" y1="50" x2="15" y2="50" />
                        <line x1="85" y1="50" x2="93" y2="50" />
                    </g>
                    <g class="needle">
                        <path d="M50 17 L56.5 50 L50 45.5 L43.5 50 Z" fill="#9bc31e" />
                        <path d="M50 83 L43.5 50 L50 54.5 L56.5 50 Z" fill="#fff" fill-opacity="0.5" />
                    </g>
                    <circle cx="50" cy="50" r="4.5" fill="#fff" />
                </svg>
            @elseif ($key === '403')
                <svg class="pulse" viewBox="0 0 24 24" fill="none" stroke="#9bc31e" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="11" width="18" height="11" rx="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
            @elseif ($key === '419')
                <svg class="pulse" viewBox="0 0 24 24" fill="none" stroke="#9bc31e" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7v5l3 2" />
                </svg>
            @elseif ($key === '429')
                <svg class="pulse" viewBox="0 0 24 24" fill="none" stroke="#9bc31e" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                    <path d="M3.6 15a9 9 0 1 1 16.8 0" />
                    <path d="m14 10 3-3" />
                </svg>
            @elseif ($key === '503')
                <svg class="pulse" viewBox="0 0 24 24" fill="none" stroke="#9bc31e" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14.7 6.3a4 4 0 0 0 5 5l-9.4 9.4a2.1 2.1 0 0 1-3-3l9.4-9.4a4 4 0 0 0-2-2Z" />
                </svg>
            @else
                <svg class="pulse" viewBox="0 0 24 24" fill="none" stroke="#9bc31e" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
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
            {{ __('site.error_page.eyebrow') }} {{ $status }}
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
    </main>

    <footer class="foot">
        <a href="{{ $websiteUrl }}">{{ config('app.name') }}</a> · Chișinău
    </footer>
</body>
</html>
