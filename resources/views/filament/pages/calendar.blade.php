<x-filament-panels::page>
    @php
        $dotColors = [
            'success' => 'bg-emerald-500',
            'accent' => 'bg-sky-500',
            'danger' => 'bg-red-500',
            'warning' => 'bg-amber-500',
            'event' => 'bg-violet-500',
            'neutral' => 'bg-slate-400',
            'muted' => 'bg-slate-300',
            'info' => 'bg-cyan-500',
        ];
        $days = $this->eventsByDay();
    @endphp

    <div class="space-y-4">
        {{-- Bara de navigare --}}
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" wire:click="previousMonth">
                <span class="sr-only">Luna anterioară</span>
            </x-filament::button>
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" wire:click="nextMonth">
                <span class="sr-only">Luna următoare</span>
            </x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="goToday">Azi</x-filament::button>
            <span class="ms-1 text-lg font-semibold capitalize">{{ $this->monthLabel() }}</span>
        </div>

        {{-- Agendă instituțională --}}
        @if (count($days) === 0)
            <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                Niciun eveniment instituțional în această lună.
            </div>
        @else
            <div class="space-y-3">
                @foreach ($days as $date => $events)
                    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <h3 class="text-sm font-semibold capitalize text-gray-700 dark:text-gray-200">
                            {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, j F') }}
                        </h3>
                        <div class="mt-2 space-y-1.5">
                            @foreach ($events as $event)
                                <div class="flex items-center gap-2.5">
                                    <span class="size-2.5 shrink-0 rounded-full {{ $dotColors[$event['color']] ?? 'bg-slate-400' }}"></span>
                                    <span class="text-sm text-gray-800 dark:text-gray-100">{{ $event['title'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <p class="text-xs text-gray-500 dark:text-gray-400">
            Calendarul instituției: semestre, vacanțe și sesiuni de corigență publicate. Temele și absențele
            individuale rămân în cabinetul fiecărui elev.
        </p>
    </div>
</x-filament-panels::page>
