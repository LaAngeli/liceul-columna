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
             alege contextul de aici, fără logout — dropdown Filament în locul <select>-ului nativ
             (lista aceluia e desenată de OS, deci nu putea prelua designul panoului). Fiecare rând
             = POST + redirect (Filament re-randează meniul/dashboard-ul/filtrele pe request).
             Exclusiv panoul staff: familia nu are comutare (decizia beneficiarului, 30.07.2026). --}}
        @php
            $switchables = \App\Support\ActiveRole::switchableValues($user->getRoleNames()->all());

            // Subtitlul fiecărui rol = PERIMETRUL lui de lucru (static, zero interogări):
            // comutarea nu schimbă doar eticheta, schimbă ce vezi — iar meniul o spune explicit.
            $scopeSchool = __('panel.role_switch.scope_school');
            $roleScopes = [
                UserRole::Admin->value => $scopeSchool,
                UserRole::Director->value => $scopeSchool,
                UserRole::PrimVicedirector->value => $scopeSchool,
                UserRole::AdministratorOperational->value => $scopeSchool,
                UserRole::AdministratorTehnic->value => __('panel.role_switch.scope_infra'),
                UserRole::Diriginte->value => $homeroom ?? __('panel.role_switch.scope_homeroom'),
                UserRole::Profesor->value => __('panel.role_switch.scope_taught'),
            ];
            $roleIcons = [
                UserRole::Admin->value => 'heroicon-m-shield-check',
                UserRole::Director->value => 'heroicon-m-building-library',
                UserRole::PrimVicedirector->value => 'heroicon-m-briefcase',
                UserRole::AdministratorOperational->value => 'heroicon-m-clipboard-document-check',
                UserRole::AdministratorTehnic->value => 'heroicon-m-wrench-screwdriver',
                UserRole::Diriginte->value => 'heroicon-m-academic-cap',
                UserRole::Profesor->value => 'heroicon-m-book-open',
            ];
        @endphp

        {{-- width="xs" e OBLIGATORIU: fi-dropdown-panel are implicit max-width 14rem (224px),
             iar meniul de ~19rem ar depăși fundalul pictat (rândul activ „ieșea" din panou). --}}
        <x-filament::dropdown placement="bottom-end" width="xs" class="fi-role-switch-dd">
            <x-slot name="trigger">
                <button
                    type="button"
                    id="fi-role-switch"
                    class="fi-role-switch"
                    aria-haspopup="true"
                    title="{{ __('panel.role_switch.label') }}: {{ $roleLabel }}"
                >
                    <svg class="fi-role-badge__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                    </svg>
                    <span class="fi-role-switch__label">{{ $roleLabel }}</span>
                    <svg class="fi-role-switch__chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 8 4 4 4-4" />
                    </svg>
                </button>
            </x-slot>

            <div class="fi-role-menu">
                <p class="fi-role-menu__heading">{{ __('panel.role_switch.label') }}</p>

                @foreach ($switchables as $switchable)
                    @php $isActive = $roleValue === $switchable; @endphp
                    <form method="POST" action="{{ route('staff.role.switch') }}">
                        @csrf
                        <input type="hidden" name="role" value="{{ $switchable }}">
                        <button
                            type="submit"
                            class="fi-role-menu__item{{ $isActive ? ' fi-role-menu__item--active' : '' }}"
                            @disabled($isActive)
                            @if ($isActive) aria-current="true" @endif
                        >
                            <span class="fi-role-menu__icon" aria-hidden="true">
                                <x-filament::icon :icon="$roleIcons[$switchable] ?? 'heroicon-m-user-circle'" />
                            </span>
                            <span class="fi-role-menu__text">
                                <span class="fi-role-menu__role">{{ trans('site.roles.'.$switchable, [], app()->getLocale()) }}</span>
                                <span class="fi-role-menu__scope">{{ $roleScopes[$switchable] ?? '' }}</span>
                            </span>
                            @if ($isActive)
                                <svg class="fi-role-menu__check" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                </svg>
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>
        </x-filament::dropdown>
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
        display: inline-flex;
        align-items: center;
        gap: 0.625rem;
        padding-right: 0.5rem;
    }

    /* Pe mobil, elementele INFORMATIVE (badge static, ceas) dispar ca să nu aglomereze topbar-ul;
       comutatorul de rol însă e FUNCȚIONAL (singura cale de schimbare a contextului) → rămâne,
       compactat prin elipsă pe etichetă. */
    @media (max-width: 639.98px) {
        .fi-role-badge { display: none; }
        .fi-role-switch__label { max-width: 6.5rem; }
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

    /* Wrapper-ul dropdown-ului Filament (div.fi-dropdown) trebuie să curgă inline în topbar. */
    .fi-role-switch-dd {
        display: inline-flex;
    }

    /* Trigger — aceeași pastilă ca badge-ul static, dar interactivă. Panoul deschis vine de la
       componenta Filament (fi-dropdown-panel): fundal/umbră/ring identice cu meniul de utilizator,
       light & dark — exact ce <select>-ul nativ nu putea oferi (popup desenat de OS). */
    .fi-role-switch {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        min-height: 2rem;
        padding: 0.25rem 0.5rem 0.25rem 0.625rem;
        border-radius: 9999px;
        border: 1px solid color-mix(in srgb, var(--brand-navy) 25%, transparent);
        background-color: color-mix(in srgb, var(--brand-navy) 6%, transparent);
        color: rgb(55 65 81);
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1.25;
        white-space: nowrap;
        cursor: pointer;
        transition: border-color 150ms ease, background-color 150ms ease;
    }

    .fi-role-switch:hover {
        border-color: color-mix(in srgb, var(--brand-navy) 45%, transparent);
        background-color: color-mix(in srgb, var(--brand-navy) 10%, transparent);
    }

    .fi-role-switch:focus-visible {
        outline: 2px solid var(--brand-navy);
        outline-offset: 2px;
    }

    .dark .fi-role-switch {
        border-color: rgba(191, 219, 254, 0.25);
        background-color: rgba(255, 255, 255, 0.05);
        color: rgb(209 213 219);
    }

    .dark .fi-role-switch:focus-visible {
        outline-color: rgb(191 219 254);
    }

    .fi-role-switch__label {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .fi-role-switch__chevron {
        width: 0.8rem;
        height: 0.8rem;
        opacity: 0.6;
        flex-shrink: 0;
    }

    /* Panoul de roluri — antet + rânduri „iconiță · rol + perimetru · bifă". Lățime plafonată
       la viewport pe mobil; înălțimea rândurilor ≥44px (țintă tactilă). */
    .fi-role-menu {
        width: min(19rem, calc(100vw - 1.25rem));
        padding: 0.375rem;
    }

    .fi-role-menu__heading {
        padding: 0.5rem 0.75rem 0.375rem;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: rgb(107 114 128);
    }

    .dark .fi-role-menu__heading { color: rgb(156 163 175); }

    .fi-role-menu__item {
        display: flex;
        width: 100%;
        min-height: 2.75rem;
        align-items: center;
        gap: 0.625rem;
        padding: 0.45rem 0.625rem;
        border: none;
        border-radius: 0.5rem;
        background: transparent;
        text-align: left;
        cursor: pointer;
        transition: background-color 150ms ease;
    }

    .fi-role-menu__item:hover:not(:disabled) {
        background-color: color-mix(in srgb, var(--brand-navy) 6%, transparent);
    }

    .dark .fi-role-menu__item:hover:not(:disabled) {
        background-color: rgba(255, 255, 255, 0.05);
    }

    .fi-role-menu__item:focus-visible {
        outline: 2px solid var(--brand-navy);
        outline-offset: -2px;
    }

    .dark .fi-role-menu__item:focus-visible { outline-color: rgb(191 219 254); }

    /* Rolul ACTIV: rândul e stare, nu acțiune (disabled — un click pe el n-ar schimba nimic). */
    .fi-role-menu__item--active {
        background-color: color-mix(in srgb, var(--brand-navy) 8%, transparent);
        cursor: default;
    }

    .dark .fi-role-menu__item--active {
        background-color: rgba(191, 219, 254, 0.08);
    }

    .fi-role-menu__icon {
        display: flex;
        width: 2rem;
        height: 2rem;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        border-radius: 0.5rem;
        background-color: color-mix(in srgb, var(--brand-navy) 8%, transparent);
        color: var(--brand-navy);
    }

    /* SVG-urile cer width/height explicit (memoria filament-svg-inline-size). */
    .fi-role-menu__icon svg {
        width: 1.05rem;
        height: 1.05rem;
    }

    .dark .fi-role-menu__icon {
        background-color: rgba(191, 219, 254, 0.10);
        color: rgb(191 219 254);
    }

    .fi-role-menu__item--active .fi-role-menu__icon {
        background-color: var(--brand-navy);
        color: #ffffff;
    }

    .fi-role-menu__text {
        display: flex;
        min-width: 0;
        flex-direction: column;
        gap: 0.1rem;
    }

    .fi-role-menu__role {
        font-size: 0.8rem;
        font-weight: 600;
        line-height: 1.2;
        color: rgb(31 41 55);
    }

    .dark .fi-role-menu__role { color: rgb(229 231 235); }

    .fi-role-menu__item--active .fi-role-menu__role { color: var(--brand-navy); }
    .dark .fi-role-menu__item--active .fi-role-menu__role { color: rgb(191 219 254); }

    .fi-role-menu__scope {
        overflow: hidden;
        font-size: 0.68rem;
        line-height: 1.25;
        color: rgb(107 114 128);
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dark .fi-role-menu__scope { color: rgb(156 163 175); }

    /* Bifa = accentul de brand (verde §11 — element grafic, nu text). */
    .fi-role-menu__check {
        width: 1rem;
        height: 1rem;
        margin-left: auto;
        flex-shrink: 0;
        color: var(--brand-green, #9bc31e);
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
