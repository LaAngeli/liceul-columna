<x-filament-panels::page>
    @php
        // Stiluri INLINE — panoul Filament are propriul build de CSS care nu scanează acest Blade.
        // [culoare punct/text, fundal chip]
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
        $mode = $this->mode;
        $weekdays = ['Lu', 'Ma', 'Mi', 'Jo', 'Vi', 'Sâ', 'Du'];
        $tabs = ['month' => 'Lună', 'week' => 'Săptămână', 'day' => 'Zi', 'agenda' => 'Agendă'];
        $legend = [
            'success' => 'Teme',
            'accent' => 'Evaluări și examene',
            'danger' => 'Absențe',
            'warning' => 'Termene-limită',
            'event' => 'Evenimente și ședințe',
            'muted' => 'Structură',
        ];
    @endphp

    <div class="space-y-4">
        {{-- Bara de instrumente --}}
        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;">
            <div style="display:flex;align-items:center;gap:6px;">
                <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" wire:click="previous">
                    <span class="sr-only">Înapoi</span>
                </x-filament::button>
                <x-filament::button color="gray" size="sm" wire:click="goToday">Azi</x-filament::button>
                <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" wire:click="next">
                    <span class="sr-only">Înainte</span>
                </x-filament::button>
                <span style="margin-inline-start:6px;font-size:16px;font-weight:600;text-transform:capitalize;">{{ $this->periodTitle() }}</span>
            </div>
            <div style="display:inline-flex;border:1px solid rgba(128,128,128,.3);border-radius:8px;overflow:hidden;">
                @foreach ($tabs as $key => $label)
                    <button type="button" wire:click="setMode('{{ $key }}')"
                        style="padding:6px 12px;font-size:13px;font-weight:500;cursor:pointer;{{ $mode === $key ? 'background:#0f4d77;color:#fff;' : 'opacity:.7;' }}{{ ! $loop->first ? 'border-inline-start:1px solid rgba(128,128,128,.3);' : '' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- LUNĂ --}}
        @if ($mode === 'month')
            @php($cells = $this->monthCells())
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
                            <div wire:click="openDay('{{ $cell['date'] }}')"
                                style="cursor:pointer;min-height:84px;border-radius:8px;padding:4px;overflow:hidden;{{ $cell['isToday'] ? 'border:1.5px solid #9bc31e;box-shadow:0 0 0 1px #9bc31e;' : 'border:1px solid rgba(128,128,128,.22);' }}">
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
        @endif

        {{-- SĂPTĂMÂNĂ --}}
        @if ($mode === 'week')
            <div style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px;">
                @foreach ($this->weekDays() as $day)
                    <div style="border-radius:10px;padding:8px;{{ $day['isToday'] ? 'border:1.5px solid #9bc31e;' : 'border:1px solid rgba(128,128,128,.2);' }}">
                        <div wire:click="openDay('{{ $day['date'] }}')" style="cursor:pointer;display:flex;align-items:baseline;justify-content:space-between;margin-bottom:6px;">
                            <span style="font-size:11px;opacity:.6;text-transform:capitalize;">{{ $day['weekday'] }}</span>
                            <span style="font-size:14px;font-weight:600;{{ $day['isToday'] ? 'color:#9bc31e;' : '' }}">{{ $day['day'] }}</span>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:4px;">
                            @forelse ($day['events'] as $event)
                                @php($c = $cat[$event['color']] ?? $cat['muted'])
                                <div style="display:flex;align-items:center;gap:5px;padding:3px 6px;border-radius:6px;font-size:11px;background:{{ $c[1] }};color:{{ $c[0] }};">
                                    <span style="width:6px;height:6px;border-radius:50%;background:{{ $c[0] }};flex:none;"></span>
                                    @if ($event['startTime'])<span style="font-weight:600;">{{ $event['startTime'] }}</span>@endif
                                    <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $event['title'] }}</span>
                                </div>
                            @empty
                                <span style="font-size:11px;opacity:.35;">—</span>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ZI --}}
        @if ($mode === 'day')
            @php($dayEvents = $this->dayEvents())
            @if (count($dayEvents) === 0)
                <div style="border:1px dashed rgba(128,128,128,.35);border-radius:12px;padding:28px;text-align:center;font-size:14px;opacity:.65;">
                    Nicio activitate în această zi.
                </div>
            @else
                <div style="display:flex;flex-direction:column;gap:8px;">
                    @foreach ($dayEvents as $event)
                        @php($c = $cat[$event['color']] ?? $cat['muted'])
                        <div style="display:flex;align-items:stretch;overflow:hidden;border-radius:10px;border:1px solid rgba(128,128,128,.18);">
                            <span style="width:4px;background:{{ $c[0] }};flex:none;"></span>
                            <span style="padding:10px 14px;font-size:14px;">{{ $event['title'] }}@if ($event['startTime'])<span style="opacity:.6;font-size:12px;"> · {{ $event['startTime'] }}</span>@endif</span>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- AGENDĂ --}}
        @if ($mode === 'agenda')
            @php($agendaDays = collect($this->monthCells())->filter(fn ($c) => $c !== null && count($c['events']) > 0))
            @if ($agendaDays->isEmpty())
                <div style="border:1px dashed rgba(128,128,128,.35);border-radius:12px;padding:28px;text-align:center;font-size:14px;opacity:.65;">
                    Niciun eveniment instituțional în această lună.
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($agendaDays as $cell)
                        <div style="border-radius:12px;padding:14px;border:1px solid rgba(128,128,128,.18);background:rgba(130,130,130,.04);">
                            <h3 style="font-size:14px;font-weight:600;text-transform:capitalize;">{{ \Illuminate\Support\Carbon::parse($cell['date'])->translatedFormat('l, j F') }}</h3>
                            <div style="margin-top:8px;display:flex;flex-direction:column;gap:6px;">
                                @foreach ($cell['events'] as $event)
                                    @php($c = $cat[$event['color']] ?? $cat['muted'])
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <span style="width:10px;height:10px;border-radius:50%;background:{{ $c[0] }};flex:none;"></span>
                                        <span style="font-size:14px;">{{ $event['title'] }}@if ($event['startTime'])<span style="opacity:.6;font-size:12px;"> · {{ $event['startTime'] }}</span>@endif</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- Legendă --}}
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;border-top:1px solid rgba(128,128,128,.2);padding-top:12px;font-size:12px;">
            <span style="font-weight:600;opacity:.7;">Legendă:</span>
            @foreach ($legend as $key => $label)
                @php($c = $cat[$key])
                <span style="display:inline-flex;align-items:center;gap:6px;">
                    <span style="width:9px;height:9px;border-radius:50%;background:{{ $c[0] }};"></span>{{ $label }}
                </span>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
