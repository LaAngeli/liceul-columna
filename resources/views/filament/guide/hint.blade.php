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
        onTrigger(instance, event) { instance.guideTrigger = event?.type },
        onShow(instance) {
            {{-- Focusul NU e mereu al utilizatorului: modalul Filament își mută singur focusul la
                 deschidere, iar dacă primul câmp are ghid, capcana aterizează exact pe butonul
                 ăsta — și bula se deschidea nechemată, peste formular. Deosebirea o face
                 `:focus-visible`: Tab îl aprinde, focusul programatic după un click de mouse nu.
                 Aceeași regulă pe care o folosește deja stilul (`.fi-guide-hint:focus-visible`),
                 deci tooltipul și evidențierea se aprind acum împreună.
                 Hover-ul și atingerea trec neatinse: ele nu declanșează prin `focus`. --}}
            if (instance.guideTrigger !== 'focus' && instance.guideTrigger !== 'focusin') { return true }

            try { return instance.reference.matches(':focus-visible') } catch { return true }
        },
    }"
>
    {{-- Aceeași iconiță ca la câmpuri și butoane ({@see App\Support\PanelGuide::ICON}): un singur
         semn pentru un singur gest. --}}
    <x-filament::icon :icon="\App\Support\PanelGuide::ICON" aria-hidden="true" />
    <span class="fi-guide-hint__sr">{{ __('guide.aria_label') }}: {{ $text }}</span>
</button>
