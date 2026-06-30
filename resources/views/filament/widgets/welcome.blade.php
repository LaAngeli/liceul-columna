{{-- Banner de bun-venit (dashboard staff). Stilizare prin atribute `style` inline (nu clase
     Tailwind), iar culorile vin din tokenii --brand-* definiți în resources/css/filament/admin/theme.css. --}}
<div class="fi-welcome">
    <div class="fi-welcome__main">
        <p class="fi-welcome__greeting">{{ $greeting }}, <span>{{ $name }}</span></p>
        <p class="fi-welcome__date">{{ \Illuminate\Support\Str::ucfirst($date) }}</p>
    </div>

    @if ($roleLabel !== null)
        <span class="fi-welcome__role">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
            </svg>
            {{ $roleLabel }}
        </span>
    @endif
</div>

@if ($missingTeacherProfile)
    {{-- Profesor/diriginte cu rol dar fără fișă Teacher: dashboard-ul „de rol" se ascunde — îl
         îndrumăm să contacteze administrația ca să-i atribuie o fișă. --}}
    <div class="fi-welcome-hint" role="status">
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 100 2 1 1 0 000-2zm-1 5a1 1 0 011-1h.01a1 1 0 01.99 1v3a1 1 0 11-2 0v-3z" clip-rule="evenodd" />
        </svg>
        <p>{{ trans('panel.widgets.welcome.missing_teacher') }}</p>
    </div>
@endif

<style>
    .fi-welcome {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.1rem 1.4rem;
        border-radius: 0.75rem;
        border: 1px solid rgb(229 231 235);
        background: linear-gradient(135deg, rgb(255 255 255), rgb(249 250 251));
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    }

    .dark .fi-welcome {
        border-color: rgba(255, 255, 255, 0.08);
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.01));
        box-shadow: none;
    }

    .fi-welcome__greeting {
        font-size: 1.15rem;
        font-weight: 600;
        color: rgb(31 41 55);
        margin: 0;
    }

    .fi-welcome__greeting span { font-weight: 800; }

    .dark .fi-welcome__greeting { color: rgb(243 244 246); }

    .fi-welcome__date {
        margin: 0.2rem 0 0;
        font-size: 0.8rem;
        color: rgb(107 114 128);
    }

    .dark .fi-welcome__date { color: rgb(156 163 175); }

    .fi-welcome__role {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.85rem;
        border-radius: 9999px;
        background-color: var(--brand-navy); /* contrast >6:1 cu alb (WCAG AA) */
        color: var(--brand-navy-contrast);
        font-size: 0.8rem;
        font-weight: 700;
        white-space: nowrap;
        box-shadow: 0 1px 3px rgba(15, 77, 119, 0.35);
    }

    .fi-welcome__role svg { width: 1rem; height: 1rem; }

    .fi-welcome-hint {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        margin-top: 0.6rem;
        padding: 0.9rem 1.1rem;
        border-radius: 0.6rem;
        /* Casetă informativă pe ton navy reținut — brandbook §11 nu permite alte culori (era amber). */
        border: 1px solid color-mix(in srgb, var(--brand-navy) 20%, transparent);
        background: color-mix(in srgb, var(--brand-navy) 6%, transparent);
        color: var(--brand-navy);
        font-size: 0.85rem;
    }

    .dark .fi-welcome-hint {
        /* Pe dark păstrăm navy ca fundal translucid, dar textul = navy deschis (lizibil). */
        border-color: rgba(127, 177, 214, 0.30); /* navy deschis 30% */
        background: color-mix(in srgb, var(--brand-navy) 18%, transparent);
        color: rgb(191 219 254); /* navy foarte deschis — contrast AA pe fundal închis */
    }

    .fi-welcome-hint svg { width: 1.1rem; height: 1.1rem; flex-shrink: 0; }

    .fi-welcome-hint p { margin: 0; }
</style>
