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
        $chipColors = [
            'success' => 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
            'accent' => 'bg-sky-500/12 text-sky-700 dark:text-sky-300',
            'danger' => 'bg-red-500/12 text-red-600 dark:text-red-400',
            'warning' => 'bg-amber-500/12 text-amber-700 dark:text-amber-300',
            'event' => 'bg-violet-500/12 text-violet-700 dark:text-violet-300',
            'neutral' => 'bg-slate-400/12 text-slate-600 dark:text-slate-300',
            'muted' => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
            'info' => 'bg-cyan-500/12 text-cyan-700 dark:text-cyan-300',
        ];
        $cells = $this->monthCells();
        $weekdays = ['Lu', 'Ma', 'Mi', 'Jo', 'Vi', 'Sâ', 'Du'];
        $agendaDays = collect($cells)->filter(fn ($c) => $c !== null && count($c['events']) > 0);
    @endphp

    <div class="space-y-4">
        {{-- Bara de navigare --}}
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" wire:click="previousMonth">
                <span class="sr-only">Luna anterioară</span>
            </x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="goToday">Azi</x-filament::button>
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" wire:click="nextMonth">
                <span class="sr-only">Luna următoare</span>
            </x-filament::button>
            <span class="ms-1 text-lg font-semibold capitalize">{{ $this->monthLabel() }}</span>
        </div>

        {{-- Gridul lunar --}}
        <div>
            <div class="grid grid-cols-7 gap-1 pb-1 text-center text-xs text-gray-400">
                @foreach ($weekdays as $w)
                    <div class="py-1">{{ $w }}</div>
                @endforeach
            </div>
            <div class="grid grid-cols-7 gap-1">
                @foreach ($cells as $cell)
                    @if ($cell === null)
                        <div class="min-h-20 rounded-md bg-gray-50 dark:bg-white/5"></div>
                    @else
                        <div @class([
                            'min-h-20 rounded-lg border bg-white p-1 dark:bg-gray-900',
                            'border-[#9bc31e] ring-1 ring-[#9bc31e]' => $cell['isToday'],
                            'border-gray-200 dark:border-white/10' => ! $cell['isToday'],
                        ])>
                            <span @class([
                                'inline-flex size-6 items-center justify-center rounded-full text-xs font-semibold',
                                'bg-[#9bc31e] text-[#1d1d1c]' => $cell['isToday'],
                                'text-gray-500 dark:text-gray-400' => ! $cell['isToday'],
                            ])>{{ $cell['day'] }}</span>
                            @foreach (array_slice($cell['events'], 0, 3) as $event)
                                <div class="mt-0.5 flex items-center gap-1 truncate rounded px-1 py-0.5 text-[10px] leading-tight {{ $chipColors[$event['color']] ?? $chipColors['muted'] }}">
                                    <span class="size-1.5 shrink-0 rounded-full {{ $dotColors[$event['color']] ?? 'bg-slate-400' }}"></span>
                                    <span class="truncate">{{ $event['title'] }}</span>
                                </div>
                            @endforeach
                            @if (count($cell['events']) > 3)
                                <span class="px-1 text-[10px] text-gray-400">+{{ count($cell['events']) - 3 }}</span>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Agenda lunii (complement la grilă) --}}
        @if ($agendaDays->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                Niciun eveniment instituțional în această lună.
            </div>
        @else
            <div class="space-y-3">
                @foreach ($agendaDays as $cell)
                    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <h3 class="text-sm font-semibold capitalize text-gray-700 dark:text-gray-200">
                            {{ \Illuminate\Support\Carbon::parse($cell['date'])->translatedFormat('l, j F') }}
                        </h3>
                        <div class="mt-2 space-y-1.5">
                            @foreach ($cell['events'] as $event)
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
            Calendarul instituției: semestre, vacanțe, sesiuni de corigență publicate și evenimentele
            manuale. Temele și absențele individuale rămân în cabinetul fiecărui elev.
        </p>
    </div>
</x-filament-panels::page>
