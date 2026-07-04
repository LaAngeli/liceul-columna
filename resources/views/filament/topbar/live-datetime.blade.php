@php
    use App\Enums\UserRole;

    $user = auth()->user();
    $roleValue = $user?->getRoleNames()->first();
    $role = $roleValue !== null ? UserRole::tryFrom($roleValue) : null;
    $roleLabel = $role !== null ? trans('site.roles.'.$role->value, [], app()->getLocale()) : null;

    // Locala JS pentru formatarea client-side a ceasului (live, fără polling server).
    $jsLocale = ['ro' => 'ro-RO', 'ru' => 'ru-RU', 'en' => 'en-GB'][app()->getLocale()] ?? 'ro-RO';
@endphp

{{-- Ceas+dată LIVE + badge de rol în topbar. Stiluri inline: Tailwind-ul Filament NU scanează
     resources/views/ (vezi language-switcher.blade.php). --}}
<div class="fi-topbar-extras">
    @if ($roleLabel !== null)
        <span class="fi-role-badge" title="{{ $roleLabel }}">
            <svg class="fi-role-badge__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
            </svg>
            <span>{{ $roleLabel }}</span>
        </span>
    @endif

    <span
        class="fi-live-datetime"
        x-data
        x-init="
            const fmt = () => {
                const now = new Date();
                const date = now.toLocaleDateString('{{ $jsLocale }}', { weekday: 'short', day: 'numeric', month: 'short' });
                const hh = String(now.getHours()).padStart(2, '0');
                const mm = String(now.getMinutes()).padStart(2, '0');
                const ss = String(now.getSeconds()).padStart(2, '0');
                $el.querySelector('[data-date]').textContent = date;
                $el.querySelector('[data-hm]').textContent = hh + ':' + mm;
                $el.querySelector('[data-sec]').textContent = ss;
            };
            fmt();
            setInterval(fmt, 1000);
        "
    >
        <svg class="fi-live-datetime__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2" />
        </svg>
        {{-- Structura: [icon] [data mică] │ [HH:MM mare] [SS mic] — fiecare parte are min-width
             fix ca să nu se disloce restul topbar-ului la schimbarea cifrelor. --}}
        <span class="fi-live-datetime__date" data-date>—</span>
        <span class="fi-live-datetime__sep" aria-hidden="true"></span>
        <span class="fi-live-datetime__time">
            <span data-hm>—</span><span class="fi-live-datetime__sec"><span aria-hidden="true">:</span><span data-sec>—</span></span>
        </span>
    </span>
</div>

<style>
    .fi-topbar-extras {
        display: none;
        align-items: center;
        gap: 0.625rem;
        padding-right: 0.5rem;
    }

    /* Ascuns sub sm ca să nu aglomereze topbar-ul pe mobil. */
    @media (min-width: 640px) {
        .fi-topbar-extras { display: inline-flex; }
    }

    .fi-role-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.25rem 0.625rem;
        border-radius: 9999px;
        border: 1px solid rgb(229 231 235);
        background-color: rgb(249 250 251);
        color: rgb(55 65 81);
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
    }

    .dark .fi-role-badge {
        border-color: rgba(255, 255, 255, 0.10);
        background-color: rgba(255, 255, 255, 0.05);
        color: rgb(209 213 219);
    }

    .fi-role-badge__icon { width: 0.85rem; height: 0.85rem; opacity: 0.7; }

    /* Ceasul — card pill discret cu accent de brand. Ierarhie vizuală: ora HH:MM = principal
       (navy, medium), secundele = subtile (mai mici, opacitate redusă), data = discret (gri, mic),
       iconița = accent navy. Delimitare completă via `min-width` fix pe fiecare parte → restul
       topbar-ului rămâne complet stabil între ticks. */
    .fi-live-datetime {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.3rem 0.75rem;
        border-radius: 9999px;
        border: 1px solid color-mix(in srgb, var(--brand-navy) 12%, transparent);
        background: color-mix(in srgb, var(--brand-navy) 4%, transparent);
        font-variant-numeric: tabular-nums;
        font-feature-settings: 'tnum';
        white-space: nowrap;
        line-height: 1;
        transition: background-color 200ms ease, border-color 200ms ease;
    }
    .dark .fi-live-datetime {
        border-color: rgba(191, 219, 254, 0.12);
        background: rgba(255, 255, 255, 0.03);
    }
    .fi-live-datetime:hover {
        border-color: color-mix(in srgb, var(--brand-navy) 22%, transparent);
        background: color-mix(in srgb, var(--brand-navy) 7%, transparent);
    }
    .dark .fi-live-datetime:hover {
        border-color: rgba(191, 219, 254, 0.22);
        background: rgba(255, 255, 255, 0.06);
    }

    .fi-live-datetime__icon {
        width: 0.95rem;
        height: 0.95rem;
        color: var(--brand-navy);
        opacity: 0.7;
        flex-shrink: 0;
    }
    .dark .fi-live-datetime__icon { color: rgb(191 219 254); opacity: 0.65; }

    /* DATA — mică, palidă, ancorată la stânga cu min-width fix. */
    .fi-live-datetime__date {
        display: inline-block;
        min-width: 5rem;
        text-align: left;
        font-size: 0.7rem;
        font-weight: 500;
        color: rgb(107 114 128);
        letter-spacing: 0.01em;
    }
    .dark .fi-live-datetime__date { color: rgb(156 163 175); }

    /* Divider vertical subtil între dată și oră. */
    .fi-live-datetime__sep {
        width: 1px;
        height: 0.9rem;
        background: color-mix(in srgb, var(--brand-navy) 15%, transparent);
        flex-shrink: 0;
    }
    .dark .fi-live-datetime__sep { background: rgba(191, 219, 254, 0.18); }

    /* ORA — HH:MM = principal (navy, medium), :SS = subtil (mai mic, palid). */
    .fi-live-datetime__time {
        display: inline-flex;
        align-items: baseline;
        gap: 0.05rem;
        color: var(--brand-navy);
        font-size: 0.82rem;
        font-weight: 600;
        letter-spacing: 0.01em;
    }
    .dark .fi-live-datetime__time { color: rgb(191 219 254); }
    .fi-live-datetime__time [data-hm] {
        display: inline-block;
        min-width: 2.7rem;
        text-align: right;
    }
    .fi-live-datetime__sec {
        display: inline-flex;
        align-items: baseline;
        min-width: 1.6rem;
        font-size: 0.65rem;
        font-weight: 500;
        opacity: 0.55;
    }
    .fi-live-datetime__sec [data-sec] {
        display: inline-block;
        min-width: 1rem;
        text-align: left;
    }

    /* Pe ecrane medii ascunde ceasul, păstrează rolul (prioritar). */
    @media (max-width: 1023px) {
        .fi-live-datetime { display: none; }
    }
</style>
