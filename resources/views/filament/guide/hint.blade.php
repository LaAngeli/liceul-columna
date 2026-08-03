{{-- Iconița „i" de lângă titlul secțiunii. Textul vine din `lang/<limba>/guide.php`, prin App\Support\PanelGuide.

     BUTON, nu <span>: tooltipul trebuie să se deschidă și din tastatură (Tippy declanșează pe focus,
     nu doar pe hover) și la atingere pe telefon, unde hover-ul nu există.

     ⚠️ FĂRĂ atributul `title`: Tippy îl elimină doar când își ia conținutul din el, iar aici
     conținutul e dat explicit — deci ar fi rămas, și pe hover apăreau DOUĂ explicații suprapuse
     (bula Tippy imediat, tooltipul nativ al browserului o secundă mai târziu). Textul ajunge la
     cititorul de ecran prin conținutul vizual-ascuns al butonului, care devine numele lui accesibil:
     bula Tippy e desenată de JS și lectorul n-o vede. --}}
<button
    type="button"
    class="fi-guide-hint"
    x-data
    x-tooltip="{
        content: @js($text),
        theme: $store.theme,
        placement: 'bottom-start',
        maxWidth: 420,
    }"
>
    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
    </svg>
    <span class="fi-guide-hint__sr">{{ __('guide.aria_label') }}: {{ $text }}</span>
</button>
