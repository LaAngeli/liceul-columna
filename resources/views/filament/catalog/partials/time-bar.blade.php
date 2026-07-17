{{-- BARA TEMPORALĂ comună (Teme / Note / Absențe): Toate/Zi/Săptămână/Lună + ◀ ▶ pe perioadă +
     revenire la azi. Se randează doar pe paginile cu HasTimeNavigator — vezi list-with-navigator. --}}
<div class="flex flex-wrap items-center gap-x-4 gap-y-2">
    <x-filament::tabs :label="__('panel.homework_time.aria')" class="w-fit">
        @foreach ($this->timePills() as $pill)
            <x-filament::tabs.item
                :active="$pill['active']"
                wire:click="setTimeMode('{{ $pill['key'] }}')"
            >
                {{ $pill['label'] }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    @if ($this->timeMode() !== null)
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
