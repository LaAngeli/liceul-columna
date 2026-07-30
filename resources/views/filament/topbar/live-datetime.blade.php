@php
    use App\Enums\UserRole;

    $user = auth()->user();
    // Rolul ACTIV (F1): sub multi-rol, badge-ul arata contextul sub care lucrezi acum.
    $role = $user?->activeRole();
    $roleValue = $role?->value;
    $roleLabel = $role !== null ? trans('site.roles.'.$role->value, [], app()->getLocale()) : null;

    // FUNCȚIA EFECTIVĂ, nu doar rolul contului: dirigenția e o DESEMNARE pe fișă, iar drepturile
    // ei (validarea motivărilor de absență) vin de acolo, nu din rol. Un cont cu rolul „Profesor"
    // desemnat diriginte primea, corect, acele drepturi — dar badge-ul spunea doar „Profesor",
    // deci comportamentul corect părea o breșă (raportat de beneficiar, 2026-07-27).
    $homeroom = $user?->homeroomLabel();
    $badgeLabel = $roleLabel;
    $badgeTitle = $roleLabel;

    if ($homeroom !== null) {
        $badgeLabel = trans('site.roles.diriginte', [], app()->getLocale()).' · '.$homeroom;

        // Tooltipul păstrează ROLUL contului — funcția afișată nu-l ascunde, îl completează.
        // Când rolul e deja „Diriginte", badge-ul spune totul: nu repetăm cuvântul în tooltip.
        $badgeTitle = trans('panel.forms.user.role', [], app()->getLocale()).': '.$roleLabel;

        if ($role !== UserRole::Diriginte) {
            $badgeTitle .= ' · '.trans('site.roles.diriginte', [], app()->getLocale()).': '.$homeroom;
        }
    }

    // Locala JS pentru formatarea client-side a ceasului (live, fără polling server).
    $jsLocale = ['ro' => 'ro-RO', 'ru' => 'ru-RU', 'en' => 'en-GB'][app()->getLocale()] ?? 'ro-RO';
@endphp

{{-- Ceas+dată LIVE + badge de rol în topbar. Stiluri inline: Tailwind-ul Filament NU scanează
     resources/views/ (vezi language-switcher.blade.php). --}}
<div class="fi-topbar-extras">
    @if ($user !== null && $user->isMultiRole())
        {{-- COMUTATORUL DE ROL ACTIV (multi-rol F2, doc pct. 4): un cont cu mai multe funcții își
             alege contextul de aici, fără logout — select în locul badge-ului static, POST +
             redirect (Filament re-randează meniul/dashboard-ul/filtrele pe request). Exclusiv
             panoul staff: familia nu are comutare (decizia beneficiarului, 30.07.2026). --}}
        <form method="POST" action="{{ route('staff.role.switch') }}" class="fi-role-switch" title="{{ __('panel.role_switch.label') }}">
            @csrf
            <svg class="fi-role-badge__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
            </svg>
            <label class="fi-sr-only" for="fi-role-switch-select">{{ __('panel.role_switch.label') }}</label>
            <select
                id="fi-role-switch-select"
                name="role"
                class="fi-role-switch__select"
                onchange="this.form.submit()"
            >
                @foreach (\App\Support\ActiveRole::switchableValues($user->getRoleNames()->all()) as $switchable)
                    <option value="{{ $switchable }}" @selected($roleValue === $switchable)>
                        {{ \App\Enums\UserRole::tryFrom($switchable)?->label() ?? $switchable }}
                    </option>
                @endforeach
            </select>
        </form>
    @elseif ($badgeLabel !== null)
        <span class="fi-role-badge" title="{{ $badgeTitle }}">
            <svg class="fi-role-badge__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
            </svg>
            <span>{{ $badgeLabel }}</span>
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

    /* Comutatorul de rol activ — aceeași pastilă ca badge-ul, dar interactivă (select nativ:
       funcționează cu tastatura, pe mobil deschide picker-ul de sistem, zero JS de întreținut). */
    .fi-role-switch {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.25rem 0.375rem 0.25rem 0.625rem;
        border-radius: 9999px;
        border: 1px solid color-mix(in srgb, var(--brand-navy) 25%, transparent);
        background-color: color-mix(in srgb, var(--brand-navy) 6%, transparent);
        color: rgb(55 65 81);
        white-space: nowrap;
        transition: border-color 150ms ease, background-color 150ms ease;
    }

    .fi-role-switch:hover {
        border-color: color-mix(in srgb, var(--brand-navy) 45%, transparent);
        background-color: color-mix(in srgb, var(--brand-navy) 10%, transparent);
    }

    .dark .fi-role-switch {
        border-color: rgba(191, 219, 254, 0.25);
        background-color: rgba(255, 255, 255, 0.05);
        color: rgb(209 213 219);
    }

    .fi-role-switch__select {
        border: none;
        background: transparent;
        padding: 0 1.25rem 0 0;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1.25;
        color: inherit;
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3E%3C/svg%3E");
        background-position: right 0 center;
        background-repeat: no-repeat;
        background-size: 1rem 1rem;
    }

    .fi-role-switch__select:focus {
        outline: none;
        box-shadow: none;
    }

    .fi-role-switch__select option {
        color: rgb(17 24 39);
    }

    .fi-sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        border: 0;
    }

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
