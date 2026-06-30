@php
    use App\Support\Locale;

    $locales = array_keys(Locale::supported());
    $current = app()->getLocale();
    $activeIndex = max(0, array_search($current, $locales, true));
    // Cale RELATIVĂ (LocaleController acceptă doar redirect care începe cu „/") → rămâi pe pagina curentă din panou.
    $redirect = request()->getRequestUri();
    $redirectQuery = '?redirect=' . urlencode($redirect);
@endphp

{{-- Comutator de limbă animat (pastilă segmentată), inserat în meniul user al panoului.
     Stilizare prin atribute `style` inline (nu clase utility); culorile vin din tokenii --brand-*
     definiți în resources/css/filament/admin/theme.css. --}}
<div class="fi-lang-switch">
    <div
        class="fi-lang-switch__pill"
        role="group"
        aria-label="{{ trans('site.language', [], $current) }}"
    >
        <span
            class="fi-lang-switch__indicator"
            style="transform: translateX({{ $activeIndex * 100 }}%);"
            aria-hidden="true"
        ></span>
        @foreach ($locales as $code)
            <a
                href="{{ url('/set-locale/' . $code . $redirectQuery) }}"
                class="fi-lang-switch__option {{ $code === $current ? 'fi-lang-switch__option--active' : '' }}"
                @if ($code === $current) aria-current="true" @endif
            >
                {{ $code }}
            </a>
        @endforeach
    </div>
</div>

<style>
    .fi-lang-switch {
        display: flex;
        justify-content: center;
        padding: 0.5rem 0.75rem;
    }

    .fi-lang-switch__pill {
        position: relative;
        display: inline-flex;
        padding: 2px;
        border-radius: 9999px;
        border: 1px solid rgb(229 231 235);
        background-color: rgb(249 250 251);
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1;
    }

    .dark .fi-lang-switch__pill {
        border-color: rgba(255, 255, 255, 0.08);
        background-color: rgba(255, 255, 255, 0.04);
    }

    .fi-lang-switch__indicator {
        position: absolute;
        top: 2px;
        bottom: 2px;
        left: 2px;
        width: 2.5rem;
        border-radius: 9999px;
        /* Navy de brand (§11) — fix, vizibil în light ȘI dark. */
        background-color: var(--brand-navy);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
        transition: transform 300ms cubic-bezier(0, 0, 0.2, 1);
    }

    .fi-lang-switch__option {
        position: relative;
        z-index: 10;
        display: inline-flex;
        width: 2.5rem;
        align-items: center;
        justify-content: center;
        padding: 0.3rem 0;
        text-align: center;
        text-transform: uppercase;
        text-decoration: none;
        color: rgb(107 114 128);
        transition: color 200ms;
    }

    .dark .fi-lang-switch__option {
        color: rgb(156 163 175);
    }

    .fi-lang-switch__option:hover {
        color: rgb(17 24 39);
    }

    .dark .fi-lang-switch__option:hover {
        color: #ffffff;
    }

    .fi-lang-switch__option.fi-lang-switch__option--active,
    .fi-lang-switch__option.fi-lang-switch__option--active:hover {
        color: #ffffff;
    }
</style>
