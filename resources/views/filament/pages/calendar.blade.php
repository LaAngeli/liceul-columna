<x-filament-panels::page>
    @php
        // Stiluri INLINE (nu clase Tailwind): panoul Filament are propriul build de CSS care nu
        // scanează acest Blade, deci clasele de grilă/culori arbitrare nu s-ar compila. [dot+text, fundal].
        $cat = [
            'success' => ['#10b981', 'rgba(16,185,129,.16)'],
            'accent' => ['#0ea5e9', 'rgba(14,165,233,.16)'],
            'danger' => ['#f87171', 'rgba(248,113,113,.16)'],
            'warning' => ['#f59e0b', 'rgba(245,158,11,.18)'],
            'event' => ['#a78bfa', 'rgba(167,139,250,.16)'],
            'neutral' => ['#94a3b8', 'rgba(148,163,184,.16)'],
            'muted' => ['#cbd5e1', 'rgba(203,213,225,.16)'],
            'info' => ['#22d3ee', 'rgba(34,211,238,.16)'],
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

        {{-- Gridul lunar (inline) --}}
        <div>
            <div style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:4px;margin-bottom:4px;">
                @foreach ($weekdays as $w)
                    <div style="text-align:center;font-size:12px;padding:4px 0;opacity:.6;">{{ $w }}</div>
                @endforeach
            </div>
            <div style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:4px;">
                @foreach ($cells as $cell)
                    @if ($cell === null)
                        <div style="min-height:84px;border-radius:8px;background:rgba(130,130,130,.06);"></div>
                    @else
                        <div style="min-height:84px;border-radius:8px;padding:4px;overflow:hidden;{{ $cell['isToday'] ? 'border:1.5px solid #9bc31e;box-shadow:0 0 0 1px #9bc31e;' : 'border:1px solid rgba(128,128,128,.22);' }}">
                            <div>
                                <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;font-size:12px;font-weight:600;{{ $cell['isToday'] ? 'background:#9bc31e;color:#1d1d1c;' : 'opacity:.55;' }}">{{ $cell['day'] }}</span>
                            </div>
                            @foreach (array_slice($cell['events'], 0, 3) as $event)
                                @php($c = $cat[$event['color']] ?? $cat['muted'])
                                <div style="display:flex;align-items:center;gap:4px;margin-top:2px;padding:1px 5px;border-radius:4px;font-size:10px;line-height:1.6;background:{{ $c[1] }};color:{{ $c[0] }};white-space:nowrap;overflow:hidden;">
                                    <span style="width:6px;height:6px;border-radius:50%;background:{{ $c[0] }};flex:none;"></span>
                                    <span style="overflow:hidden;text-overflow:ellipsis;">{{ $event['title'] }}</span>
                                </div>
                            @endforeach
                            @if (count($cell['events']) > 3)
                                <div style="font-size:10px;opacity:.55;padding:1px 5px;">+{{ count($cell['events']) - 3 }}</div>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Agenda lunii (complement la grilă) --}}
        @if ($agendaDays->isEmpty())
            <div style="border:1px dashed rgba(128,128,128,.35);border-radius:12px;padding:28px;text-align:center;font-size:14px;opacity:.65;">
                Niciun eveniment instituțional în această lună.
            </div>
        @else
            <div class="space-y-3">
                @foreach ($agendaDays as $cell)
                    <div style="border-radius:12px;padding:14px;border:1px solid rgba(128,128,128,.18);background:rgba(130,130,130,.04);">
                        <h3 class="text-sm font-semibold capitalize">
                            {{ \Illuminate\Support\Carbon::parse($cell['date'])->translatedFormat('l, j F') }}
                        </h3>
                        <div style="margin-top:8px;display:flex;flex-direction:column;gap:6px;">
                            @foreach ($cell['events'] as $event)
                                @php($c = $cat[$event['color']] ?? $cat['muted'])
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span style="width:10px;height:10px;border-radius:50%;background:{{ $c[0] }};flex:none;"></span>
                                    <span class="text-sm">{{ $event['title'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <p style="font-size:12px;opacity:.55;">
            Calendarul instituției: semestre, vacanțe, sesiuni de corigență publicate și evenimentele
            manuale. Temele și absențele individuale rămân în cabinetul fiecărui elev.
        </p>
    </div>
</x-filament-panels::page>
