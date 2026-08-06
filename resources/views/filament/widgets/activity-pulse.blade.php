{{-- PULSUL ACTIVITĂȚII — calendarul de intensitate al muncii personale (07.08.2026, înlocuiește
     linia pe luni a vechiului „Monitor activitate"). Toată judecata stă pe server
     (ActivityMonitor::pulse()); blade-ul doar desenează. Stilurile trăiesc în theme.css
     (fi-pulse__*) — un <style> frate ar fi eliminat la morphing (regula fi-welcome). --}}
<x-filament-widgets::widget>
    @php($pulse = $this->pulse())

    <section class="fi-pulse" wire:poll.300s>
        {{-- Antet: titlu + pastilele de perioadă (inline, nu după pâlnie). --}}
        <header class="fi-pulse__head">
            <div>
                <h2 class="fi-pulse__title">{{ __('panel.widgets.activity_monitor.heading') }}</h2>
                <p class="fi-pulse__subtitle">{{ __('panel.widgets.activity_monitor.subheading') }}</p>
            </div>

            <div class="fi-pulse__periods" role="group" aria-label="{{ __('panel.widgets.activity_monitor.filter_period') }}">
                @foreach ($pulse['period_options'] as $value => $label)
                    <button
                        type="button"
                        wire:click="setPeriod('{{ $value }}')"
                        @class(['fi-pulse__period', 'fi-pulse__period--active' => $pulse['period'] === $value])
                    >{{ $label }}</button>
                @endforeach
            </div>
        </header>

        @if ($pulse['empty'])
            <p class="fi-pulse__empty">{{ __('panel.widgets.activity_monitor.empty') }}</p>
        @else
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

            {{-- Calendarul de intensitate: coloană = săptămână, rând = zi; se derulează orizontal și
                 pornește ancorat la PREZENT (capătul din dreapta) — trecutul se descoperă derulând. --}}
            <div class="fi-pulse__map" x-data x-init="$el.scrollLeft = $el.scrollWidth">
                <div class="fi-pulse__grid-wrap">
                    <div class="fi-pulse__weekdays" aria-hidden="true">
                        @foreach ($pulse['weekday_labels'] as $index => $label)
                            <span>{{ in_array($index, [0, 2, 4], true) ? $label : '' }}</span>
                        @endforeach
                    </div>

                    <div>
                        <div class="fi-pulse__months" aria-hidden="true">
                            @foreach ($pulse['weeks'] as $weekIndex => $week)
                                <span>{{ $pulse['month_marks'][$weekIndex] ?? '' }}</span>
                            @endforeach
                        </div>

                        <div class="fi-pulse__grid" role="img" aria-label="{{ __('panel.widgets.activity_monitor.heading') }}">
                            @foreach ($pulse['weeks'] as $week)
                                <div class="fi-pulse__week">
                                    @foreach ($week as $day)
                                        <span
                                            @class([
                                                'fi-pulse__day',
                                                'fi-pulse__day--l'.$day['level'] => ! $day['future'],
                                                'fi-pulse__day--future' => $day['future'],
                                                'fi-pulse__day--today' => $day['today'],
                                            ])
                                            title="{{ $day['title'] }}"
                                        ></span>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Chips de categorii (cu numărători) = legendă ȘI filtru: click stinge/aprinde. --}}
            <footer class="fi-pulse__foot">
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

                <div class="fi-pulse__legend" aria-hidden="true">
                    <span>{{ __('panel.widgets.activity_monitor.legend_less') }}</span>
                    @foreach (range(0, 4) as $level)
                        <span class="fi-pulse__day fi-pulse__day--l{{ $level }}"></span>
                    @endforeach
                    <span>{{ __('panel.widgets.activity_monitor.legend_more') }}</span>
                </div>
            </footer>
        @endif
    </section>
</x-filament-widgets::widget>
