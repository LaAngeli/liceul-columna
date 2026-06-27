{{-- Banner de bun-venit (dashboard staff). Stiluri inline: Tailwind-ul Filament nu scanează
     resources/views/ (la fel ca topbar/language-switcher). --}}
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
        background-color: #b45309; /* amber-700 — contrast ~5.9:1 cu alb (WCAG AA) */
        color: #ffffff;
        font-size: 0.8rem;
        font-weight: 700;
        white-space: nowrap;
        box-shadow: 0 1px 3px rgba(180, 83, 9, 0.35);
    }

    .fi-welcome__role svg { width: 1rem; height: 1rem; }
</style>
