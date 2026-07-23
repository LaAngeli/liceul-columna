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
        {{-- Pe telefon: rând propriu, câmpuri late și înalte de 44px (ținta tactilă a proiectului). --}}
        <div class="flex flex-wrap items-end gap-2 max-sm:w-full">
            <label class="flex flex-col gap-1 max-sm:flex-1">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ __('panel.homework_time.from') }}
                </span>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="date"
                        wire:model.live="timeFrom"
                        max="{{ $this->timeUntil }}"
                        class="max-sm:min-h-11"
                        :aria-label="__('panel.homework_time.from')"
                    />
                </x-filament::input.wrapper>
            </label>

            <label class="flex flex-col gap-1 max-sm:flex-1">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ __('panel.homework_time.until') }}
                </span>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="date"
                        wire:model.live="timeUntil"
                        min="{{ $this->timeFrom }}"
                        class="max-sm:min-h-11"
                        :aria-label="__('panel.homework_time.until')"
                    />
                </x-filament::input.wrapper>
            </label>

            @unless ($this->timeCustomIsEmpty())
                <x-filament::button
                    size="sm"
                    color="gray"
                    icon="heroicon-m-x-mark"
                    wire:click="clearCustomRange"
                    class="max-sm:min-h-11"
                >
                    {{ __('panel.homework_time.clear_range') }}
                </x-filament::button>
            @endunless
        </div>

        <p @class([
            'text-sm max-sm:w-full',
            'text-gray-500 dark:text-gray-400' => $this->timeCustomIsEmpty(),
            'font-medium' => ! $this->timeCustomIsEmpty(),
        ])>
            {{ $this->timeCustomIsEmpty()
                ? __('panel.homework_time.custom_hint')
                : $this->timePeriodLabel() }}
        </p>
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
