{{-- PULSUL ACTIVITĂȚII (v2, 07.08.2026) — bare stivuite pe TOATĂ lățimea cardului: o bară = o zi
     (4 săptămâni) sau o săptămână (12 săptămâni / semestrul); segmentele = categoriile, în
     culorile chips-urilor. Secțiunea e `x-filament::section` NATIV — aceeași carcasă ca vecinii
     de pe dashboard. Toată judecata stă pe server (ActivityMonitor::pulse(), înălțimi în procente
     gata calculate); blade-ul doar desenează. Stilurile trăiesc în theme.css (fi-pulse__*). --}}
<x-filament-widgets::widget>
    @php($pulse = $this->pulse())

    <x-filament::section>
        <x-slot name="heading">{{ __('panel.widgets.activity_monitor.heading') }}</x-slot>
        <x-slot name="description">
            {{ __($pulse['granularity'] === 'day'
                ? 'panel.widgets.activity_monitor.subheading_day'
                : 'panel.widgets.activity_monitor.subheading_week') }}
        </x-slot>

        <x-slot name="afterHeader">
            <div class="fi-pulse__periods" role="group" aria-label="{{ __('panel.widgets.activity_monitor.filter_period') }}">
                @foreach ($pulse['period_options'] as $value => $label)
                    <button
                        type="button"
                        wire:click="setPeriod('{{ $value }}')"
                        @class(['fi-pulse__period', 'fi-pulse__period--active' => $pulse['period'] === $value])
                    >{{ $label }}</button>
                @endforeach
            </div>
        </x-slot>

        @if ($pulse['empty'])
            <p class="fi-pulse__empty">{{ __('panel.widgets.activity_monitor.empty') }}</p>
        @else
            <div class="fi-pulse" wire:poll.300s>
                {{-- KPI: cifrele pe care le cauți de fapt când deschizi „monitorul". --}}
                <div class="fi-pulse__kpis">
                    <div class="fi-pulse__kpi">
                        <span class="fi-pulse__kpi-value">{{ $pulse['kpi']['today'] }}</span>
                        <span class="fi-pulse__kpi-label">{{ __('panel.widgets.activity_monitor.kpi_today') }}</span>
                    </div>
                    <div class="fi-pulse__kpi">
                        <span class="fi-pulse__kpi-value">{{ $pulse['kpi']['week'] }}</span>
                        <span class="fi-pulse__kpi-label">{{ __('panel.widgets.activity_monitor.kpi_week') }}</span>
                    </div>
                    <div class="fi-pulse__kpi">
                        <span class="fi-pulse__kpi-value">{{ $pulse['kpi']['total'] }}</span>
                        <span class="fi-pulse__kpi-label">{{ __('panel.widgets.activity_monitor.kpi_total') }}</span>
                    </div>
                    @if ($pulse['kpi']['peak'] !== null)
                        <div class="fi-pulse__kpi">
                            <span class="fi-pulse__kpi-value">{{ $pulse['kpi']['peak']['count'] }}</span>
                            <span class="fi-pulse__kpi-label">{{ __('panel.widgets.activity_monitor.kpi_peak', ['date' => $pulse['kpi']['peak']['label']]) }}</span>
                        </div>
                    @endif
                </div>

                {{-- Barele: flex-1 → umplu ORICE lățime, fără spațiu mort; tooltip nativ per bară.
                     Viitorul (restul săptămânii curente) rămâne în axă, punctat — ritmul nu pare
                     că se rupe azi. Pe telefon, banda derulează orizontal. --}}
                <div class="fi-pulse__chart-wrap">
                    <div class="fi-pulse__chart" role="img" aria-label="{{ __('panel.widgets.activity_monitor.heading') }}">
                        @foreach ($pulse['bars'] as $bar)
                            <div
                                @class([
                                    'fi-pulse__col',
                                    'fi-pulse__col--today' => $bar['today'],
                                    'fi-pulse__col--future' => $bar['future'],
                                    'fi-pulse__col--weekend' => $bar['weekend'],
                                ])
                                title="{{ $bar['title'] }}"
                            >
                                <div class="fi-pulse__bar">
                                    @foreach ($bar['segments'] as $segment)
                                        <span
                                            class="fi-pulse__seg"
                                            style="height: {{ $segment['height'] }}%; background: {{ $segment['color'] }}"
                                        ></span>
                                    @endforeach
                                </div>
                                <span class="fi-pulse__tick">{{ $bar['label'] }}</span>
                                <span class="fi-pulse__month">{{ $bar['month_mark'] ?? '' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Chips de categorii (cu numărători) = legendă ȘI filtru: click stinge/aprinde. --}}
                <div class="fi-pulse__cats">
                    @foreach ($pulse['cats'] as $cat)
                        <button
                            type="button"
                            wire:click="toggleCategory('{{ $cat['key'] }}')"
                            @class(['fi-pulse__cat', 'fi-pulse__cat--off' => ! $cat['active']])
                            aria-pressed="{{ $cat['active'] ? 'true' : 'false' }}"
                        >
                            <span class="fi-pulse__cat-dot" style="background: {{ $cat['color'] }}"></span>
                            {{ $cat['label'] }}
                            <span class="fi-pulse__cat-count">{{ $cat['count'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
