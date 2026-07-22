<x-filament-panels::page>
    @php
        $mode = $this->mode;
        $weekdays = trans('panel.pages.calendar.weekdays');
        $tabs = [
            'month' => trans('panel.pages.calendar.tab_month'),
            'week' => trans('panel.pages.calendar.tab_week'),
            'day' => trans('panel.pages.calendar.tab_day'),
            'agenda' => trans('panel.pages.calendar.tab_agenda'),
        ];
        $legend = [
            'success' => trans('panel.pages.calendar.legend_homework'),
            'accent' => trans('panel.pages.calendar.legend_exams'),
            'danger' => trans('panel.pages.calendar.legend_absences'),
            'warning' => trans('panel.pages.calendar.legend_deadlines'),
            'event' => trans('panel.pages.calendar.legend_events'),
            'muted' => trans('panel.pages.calendar.legend_structure'),
        ];
        // Numele tradus al categoriei pentru aria-label (semnal non-culoar) — pentru orice categorie.
        $catLabel = fn (array $e): string => \App\Enums\CalendarCategory::tryFrom($e['category'] ?? '')?->getLabel() ?? '';
    @endphp

    <div class="cal space-y-4">
        {{-- Bara de instrumente. Layout pe CLASE (nu stiluri inline) — pe mobil rândurile se
             restructurează din CSS: navigare+titlu / buton lat / taburi late (.cal-toolbar*). --}}
        <div class="cal-toolbar">
            <div class="cal-toolbar__nav">
                <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" wire:click="previous">
                    <span class="sr-only">{{ trans('panel.pages.calendar.back') }}</span>
                </x-filament::button>
                <x-filament::button color="gray" size="sm" wire:click="goToday">{{ trans('panel.pages.calendar.today') }}</x-filament::button>
                <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" wire:click="next">
                    <span class="sr-only">{{ trans('panel.pages.calendar.next') }}</span>
                </x-filament::button>
                <span class="cal-title">{{ $this->periodTitle() }}</span>
            </div>
            <div class="cal-toolbar__actions">
                @if ($this->canAddEvent())
                    <a href="{{ $this->addEventUrl() }}" wire:navigate class="cal-add">
                        <svg style="width:14px;height:14px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/></svg>
                        {{ trans('panel.pages.calendar.add_event') }}
                    </a>
                @endif
                <div class="cal-tabs" role="group" aria-label="{{ trans('panel.pages.calendar.title') }}">
                    @foreach ($tabs as $key => $label)
                        <button type="button" class="cal-tab" aria-pressed="{{ $mode === $key ? 'true' : 'false' }}" wire:click="setMode('{{ $key }}')">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Filtru pe categorii (chip-uri toggle). Starea = aria-pressed (CSS o reflectă vizual).
             „Afișează tot" reaprinde toate; byDay() filtrează server-side după $visibleCategories.
             Pe mobil chip-urile stau pe UN rând derulabil (etichetă fixă) — nu înfășurate zdrențuit. --}}
        @php($chips = $this->categoryChips())
        @php($allActive = collect($chips)->every(fn ($c) => $c['isActive']))
        <div class="cal-filter">
            <span class="cal-filter__label">{{ trans('panel.pages.calendar.filter_label') }}</span>
            <div class="cal-filter__chips">
                @foreach ($chips as $chip)
                    <button type="button" class="cal-chip cal-cat-{{ $chip['color'] }}" aria-pressed="{{ $chip['isActive'] ? 'true' : 'false' }}" wire:click="toggleCategory('{{ $chip['key'] }}')">
                        <span class="cal-chip__dot" aria-hidden="true"></span>
                        {{ $chip['label'] }}
                    </button>
                @endforeach
                @if (! $allActive)
                    <button type="button" class="cal-chip-all" wire:click="showAllCategories">
                        {{ trans('panel.pages.calendar.filter_all') }}
                    </button>
                @endif
            </div>
        </div>

        {{-- LUNĂ --}}
        @if ($mode === 'month')
            @php($cells = $this->monthCells())
            <div role="grid" aria-label="{{ $this->periodTitle() }}">
                <div class="cal-grid" role="row" style="margin-bottom:4px;">
                    @foreach ($weekdays as $w)
                        <div class="cal-weekhead" role="columnheader">{{ $w }}</div>
                    @endforeach
                </div>
                <div class="cal-grid">
                    @foreach ($cells as $cell)
                        @if ($cell === null)
                            <div class="cal-cell cal-cell--blank" role="gridcell" aria-hidden="true"></div>
                        @else
                            <div class="cal-cell {{ $cell['isToday'] ? 'cal-cell--today' : '' }}" role="gridcell">
                                <button type="button" class="cal-daynum" wire:click="openDay('{{ $cell['date'] }}')"
                                    aria-label="{{ \Illuminate\Support\Carbon::parse($cell['date'])->translatedFormat('l, j F Y') }}">{{ $cell['day'] }}</button>
                                @foreach (array_slice($cell['events'], 0, 3) as $event)
                                    <button type="button" class="cal-pill cal-cat-{{ $event['color'] }}" wire:click.stop="selectEvent('{{ $event['id'] }}')"
                                        aria-label="{{ trim($catLabel($event).': '.$event['title'], ': ') }}">
                                        <span class="cal-pill__dot" aria-hidden="true"></span>
                                        <span class="cal-pill__title">{{ $event['title'] }}</span>
                                    </button>
                                @endforeach
                                @if (count($cell['events']) > 3)
                                    <div class="cal-more">+{{ count($cell['events']) - 3 }}</div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        {{-- SĂPTĂMÂNĂ: 7 coloane pe desktop, LISTĂ verticală de zile pe mobil (.cal-week). --}}
        @if ($mode === 'week')
            <div class="cal-week">
                @foreach ($this->weekDays() as $day)
                    <div class="cal-card {{ $day['isToday'] ? 'cal-card--today' : '' }}">
                        <button type="button" class="cal-daynum" style="width:100%;justify-content:space-between;border-radius:8px;padding:0 6px;background:transparent;"
                            wire:click="openDay('{{ $day['date'] }}')"
                            aria-label="{{ \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('l, j F Y') }}">
                            <span style="font-size:11px;opacity:.6;text-transform:capitalize;font-weight:500;">{{ $day['weekday'] }}</span>
                            <span style="font-size:14px;font-weight:600;{{ $day['isToday'] ? 'color:#1d1d1c;' : '' }}">{{ $day['day'] }}</span>
                        </button>
                        <div style="display:flex;flex-direction:column;gap:4px;margin-top:6px;">
                            @forelse ($day['events'] as $event)
                                <button type="button" class="cal-pill cal-cat-{{ $event['color'] }}" wire:click.stop="selectEvent('{{ $event['id'] }}')"
                                    aria-label="{{ trim($catLabel($event).': '.$event['title'], ': ') }}">
                                    <span class="cal-pill__dot" aria-hidden="true"></span>
                                    @if ($event['startTime'])<span class="cal-pill__time">{{ $event['startTime'] }}</span>@endif
                                    <span class="cal-pill__title">{{ $event['title'] }}</span>
                                </button>
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
                <div class="cal-empty">{{ trans('panel.pages.calendar.empty_day') }}</div>
            @else
                <div style="display:flex;flex-direction:column;gap:8px;">
                    @foreach ($dayEvents as $event)
                        <button type="button" class="cal-daybar cal-cat-{{ $event['color'] }}" wire:click.stop="selectEvent('{{ $event['id'] }}')"
                            aria-label="{{ trim($catLabel($event).': '.$event['title'], ': ') }}">
                            <span class="cal-daybar__rail" aria-hidden="true"></span>
                            <span class="cal-daybar__body">{{ $event['title'] }}@if ($event['startTime'])<span style="opacity:.6;font-size:12px;"> · {{ $event['startTime'] }}</span>@endif</span>
                        </button>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- AGENDĂ --}}
        @if ($mode === 'agenda')
            @php($agendaDays = collect($this->monthCells())->filter(fn ($c) => $c !== null && count($c['events']) > 0))
            @if ($agendaDays->isEmpty())
                <div class="cal-empty">{{ trans('panel.pages.calendar.empty_month') }}</div>
            @else
                <div class="space-y-3">
                    @foreach ($agendaDays as $cell)
                        <div class="cal-agenda-card">
                            <h3 style="font-size:14px;font-weight:600;text-transform:capitalize;">{{ \Illuminate\Support\Carbon::parse($cell['date'])->translatedFormat('l, j F') }}</h3>
                            <div style="margin-top:8px;display:flex;flex-direction:column;gap:6px;">
                                @foreach ($cell['events'] as $event)
                                    <button type="button" class="cal-cat-{{ $event['color'] }}" wire:click.stop="selectEvent('{{ $event['id'] }}')"
                                        aria-label="{{ trim($catLabel($event).': '.$event['title'], ': ') }}"
                                        style="display:flex;align-items:center;gap:10px;background:transparent;border:none;cursor:pointer;text-align:start;color:inherit;padding:2px 0;width:100%;">
                                        <span style="width:10px;height:10px;border-radius:50%;background:var(--cal-dot);flex:none;" aria-hidden="true"></span>
                                        <span style="font-size:14px;">{{ $event['title'] }}@if ($event['startTime'])<span style="opacity:.6;font-size:12px;"> · {{ $event['startTime'] }}</span>@endif</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- Legendă --}}
        <div class="cal-legend">
            <span style="font-weight:600;opacity:.7;">{{ trans('panel.pages.calendar.legend') }}</span>
            @foreach ($legend as $key => $label)
                <span class="cal-legend__item cal-cat-{{ $key }}">
                    <span class="cal-legend__dot" aria-hidden="true"></span>{{ $label }}
                </span>
            @endforeach
        </div>

        {{-- Modal cu detalii eveniment. Dialog accesibil: role=dialog + aria-modal, focus trap
             (x-trap, plugin focus bundle-uit de Filament), închidere cu ESC, focus inițial pe „×".
             Theme-aware prin clasa .cal-modal-card (în panou var(--background) din SPA nu există). --}}
        @php($selectedEvent = $this->selectedEvent())
        @if ($selectedEvent !== null)
            @php($categoryLabel = $catLabel($selectedEvent) ?: ($legend[$selectedEvent['color']] ?? ''))
            <div class="cal-modal-overlay" wire:click="closeEvent"
                x-data
                x-trap.noscroll="true"
                x-on:keydown.escape.window="$wire.closeEvent()"
                role="dialog" aria-modal="true" aria-labelledby="cal-modal-title">
                <div class="cal-modal-card cal-cat-{{ $selectedEvent['color'] }}" x-on:click.stop wire:key="cal-modal-{{ $selectedEvent['id'] }}">
                    <div class="cal-modal-head">
                        <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                            <span style="width:10px;height:10px;border-radius:50%;background:var(--cal-dot);flex:none;" aria-hidden="true"></span>
                            <h3 id="cal-modal-title" style="margin:0;font-size:15px;font-weight:600;overflow:hidden;text-overflow:ellipsis;">{{ $selectedEvent['title'] }}</h3>
                        </div>
                        <button type="button" class="cal-modal-close" wire:click="closeEvent" x-ref="closeBtn" x-init="$nextTick(() => $refs.closeBtn.focus())"
                            aria-label="{{ trans('panel.pages.calendar.event_close') }}">&times;</button>
                    </div>
                    <div class="cal-modal-body">
                        <div class="cal-modal-row">
                            <span style="opacity:.6;">{{ trans('panel.pages.calendar.event_category') }}</span>
                            <span style="font-weight:600;color:var(--cal-fg);">{{ $categoryLabel }}</span>
                        </div>
                        <div class="cal-modal-row">
                            <span style="opacity:.6;">{{ trans('panel.fields.date') }}</span>
                            <span style="font-weight:500;text-transform:capitalize;">{{ \Illuminate\Support\Carbon::parse($selectedEvent['date'])->translatedFormat('l, j F Y') }}</span>
                        </div>
                        <div class="cal-modal-row">
                            <span style="opacity:.6;">{{ trans('panel.forms.lesson.starts_at') }}</span>
                            <span style="font-weight:500;">
                                @if ($selectedEvent['allDay'] ?? true)
                                    {{ trans('panel.pages.calendar.event_time_all_day') }}
                                @else
                                    {{ $selectedEvent['startTime'] ?? '—' }}@if (! empty($selectedEvent['endTime'])) – {{ $selectedEvent['endTime'] }}@endif
                                @endif
                            </span>
                        </div>
                        @if (! empty($selectedEvent['meta']['description']))
                            <div style="margin-top:6px;padding-top:8px;border-top:1px dashed var(--cal-border);">
                                <div style="opacity:.6;margin-bottom:4px;">{{ trans('panel.forms.calendar_event.description') }}</div>
                                <div style="white-space:pre-wrap;line-height:1.45;">{{ $selectedEvent['meta']['description'] }}</div>
                            </div>
                        @endif
                    </div>
                    @if (! empty($selectedEvent['deepLink']))
                        <div class="cal-modal-foot">
                            <a href="{{ $selectedEvent['deepLink'] }}" class="cal-edit">{{ trans('panel.pages.calendar.event_edit') }}</a>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
