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
                const time = now.toLocaleTimeString('{{ $jsLocale }}', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                $el.querySelector('[data-clock]').textContent = date + ' · ' + time;
            };
            fmt();
            setInterval(fmt, 1000);
        "
    >
        <svg class="fi-live-datetime__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2" />
        </svg>
        <span data-clock>—</span>
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

    .fi-live-datetime {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        color: rgb(107 114 128);
        font-size: 0.75rem;
        font-weight: 500;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .dark .fi-live-datetime { color: rgb(156 163 175); }

    .fi-live-datetime__icon { width: 1rem; height: 1rem; opacity: 0.8; }

    /* Pe ecrane medii ascunde ceasul, păstrează rolul (prioritar). */
    @media (max-width: 1023px) {
        .fi-live-datetime { display: none; }
    }
</style>
