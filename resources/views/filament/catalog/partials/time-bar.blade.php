{{-- BARA TEMPORALĂ comună (Teme / Note / Absențe): Toate/Zi/Săptămână/Lună/Personalizat + ◀ ▶ pe
     perioadă + revenire la azi. Se randează doar pe paginile cu HasTimeNavigator — vezi
     list-with-navigator.

     Modul PERSONALIZAT înlocuiește săgețile cu două calendare (de la / până la): navigarea din
     pas în pas făcea o dată îndepărtată practic inaccesibilă. Câmpurile sunt `input type=date`
     NATIVE, deliberat: pe telefon deschid selectorul sistemului de operare — poziționat de OS,
     niciodată tăiat de marginea ecranului, cu ținte tactile native — iar pe desktop browserul
     afișează propriul calendar. Un calendar JS propriu ar fi trebuit să reinventeze exact asta,
     cu riscul clasic de decupare într-un rând flex. --}}
<div class="flex flex-wrap items-center gap-x-4 gap-y-2">
    {{-- Pe telefon pastilele curg pe DOUĂ rânduri. A cincea („Personalizat") a împins setul peste
         lățimea de 390px: bara temei e derulabilă orizontal (`overflow-x: auto`), deci se deschidea
         derulată pe pastila activă, cu „Toate" ieșită din vedere la stânga — corect tehnic, dar
         un filtru pe care nu-l vezi e un filtru pe care nu-l folosești. Wrap-ul le arată pe toate,
         fără gest de derulare (regula responsivă a proiectului). --}}
    <x-filament::tabs
        :label="__('panel.homework_time.aria')"
        class="w-fit max-sm:h-auto max-sm:w-full max-sm:flex-wrap"
    >
        @foreach ($this->timePills() as $pill)
            <x-filament::tabs.item
                :active="$pill['active']"
                wire:click="setTimeMode('{{ $pill['key'] }}')"
            >
                {{ $pill['label'] }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    @if ($this->timeIsCustom())
        @include('filament.catalog.partials.date-range-calendar')
    @elseif ($this->timeMode() !== null)
        <div class="flex items-center gap-1">
            <x-filament::icon-button
                icon="heroicon-m-chevron-left"
                color="gray"
                :label="__('panel.homework_time.prev')"
                wire:click="shiftTimePeriod(-1)"
            />
            <span class="min-w-32 text-center text-sm font-medium">{{ $this->timePeriodLabel() }}</span>
            <x-filament::icon-button
                icon="heroicon-m-chevron-right"
                color="gray"
                :label="__('panel.homework_time.next')"
                wire:click="shiftTimePeriod(1)"
            />
            @unless ($this->timeRefIsToday())
                <x-filament::button size="sm" color="gray" wire:click="goToTimeToday" class="ms-1">
                    {{ __('panel.homework_time.today') }}
                </x-filament::button>
            @endunless
        </div>
    @endif
</div>
