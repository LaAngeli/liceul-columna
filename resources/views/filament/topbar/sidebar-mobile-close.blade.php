{{--
    Închiderea meniului mobil (audit responsiv 2026-07-21): butonul stă în ACEEAȘI bară cu
    logo-ul (colțul dreapta-sus al sidebar-ului deschis) — X-ul implicit al Filament trăiește în
    topbar, care rămâne ACOPERIT când sidebar-ul mobil ocupă tot ecranul. Vizibil doar sub `lg`
    (pe desktop sidebar-ul e permanent, nu are ce închide).
--}}
<button
    type="button"
    x-data="{}"
    x-on:click="$store.sidebar.close()"
    class="fi-sidebar-mobile-close-btn lg:hidden"
    aria-label="{{ __('panel.nav.close_menu') }}"
    title="{{ __('panel.nav.close_menu') }}"
>
    {{-- SVG cu width/height explicite: fără ele, Filament îl întinde (vezi memoria de stilizare). --}}
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="24" height="24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
    </svg>
</button>
