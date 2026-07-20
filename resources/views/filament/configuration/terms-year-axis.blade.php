{{-- „Semestre" ca AXĂ a anului academic: pastile pe ani → semnale de integritate → cronologia
     sept→aug (benzile semestrelor + vacanțe + sesiuni de corigență + reperul AZI) → câte un card
     bogat per semestru (stare, progres, durată, datele ancorate). Fără tabel: structura anului
     e o hartă temporală, nu o listă. --}}
<x-filament-panels::page>
    <div class="space-y-6">
        @php($years = $this->yearPills())
        @php($axis = $this->axis())
        @php($cards = $this->termCards())
        @php($signals = $this->integrity())

        @if (count($years) > 1)
            <x-filament::tabs :label="__('panel.fields.academic_year')">
                @foreach ($years as $year)
                    <x-filament::tabs.item
                        :active="$this->activeYearId() === $year['id']"
                        :badge="$year['count']"
                        wire:click="openYear({{ $year['id'] }})"
                    >
                        {{ $year['label'] }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
        @endif

        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $this->configHint() }}
        </p>

        {{-- Semnalele de integritate — vizibile înaintea oricărei surprize. --}}
        @foreach ($signals as $signal)
            @php($signalClasses = match ($signal['level']) {
                'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-300 dark:ring-danger-500/30',
                'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-300 dark:ring-warning-500/30',
                default => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
            })
            <p class="flex items-start gap-1.5 rounded-lg p-2.5 text-sm ring-1 {{ $signalClasses }}">
                <x-filament::icon
                    :icon="$signal['level'] === 'info' ? 'heroicon-o-information-circle' : 'heroicon-o-exclamation-triangle'"
                    class="mt-0.5 h-4 w-4 shrink-0"
                />
                {{ $signal['text'] }}
            </p>
        @endforeach

        @if ($axis === null || $cards === [])
            {{-- Anul fără structură: starea goală spune CE lipsește și unde se adaugă. --}}
            <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <x-filament::icon icon="heroicon-o-calendar" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.terms_axis.empty_title') }}</p>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">{{ __('panel.terms_axis.empty_description') }}</p>
            </div>
        @else
            {{-- AXA anului: sept→aug, poziționare procentuală pe zile. --}}
            <section
                class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                aria-label="{{ __('panel.terms_axis.axis_label') }}"
            >
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ __('panel.terms_axis.axis_label') }}
                    </h2>
                    <span class="text-xs tabular-nums text-gray-400 dark:text-gray-500">
                        {{ $axis['start']->format('d.m.Y') }} – {{ $axis['end']->format('d.m.Y') }}
                    </span>
                </div>

                <div class="relative mt-3 h-24 overflow-hidden rounded-lg bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    {{-- Gridul lunilor. --}}
                    @foreach ($axis['months'] as $month)
                        <span
                            class="absolute inset-y-0 border-l border-gray-200 dark:border-white/10"
                            style="left: {{ $month['left'] }}%"
                            aria-hidden="true"
                        ></span>
                    @endforeach

                    {{-- Banda semestrelor. --}}
                    @foreach ($axis['terms'] as $band)
                        <span
                            class="absolute top-2 flex h-9 items-center justify-center overflow-hidden rounded-md px-1 text-xs font-semibold {{ $band['current']
                                ? 'bg-primary-600 text-white ring-2 ring-primary-300 dark:ring-primary-500/50'
                                : 'bg-primary-100 text-primary-800 dark:bg-primary-500/25 dark:text-primary-200' }}"
                            style="left: {{ $band['left'] }}%; width: {{ $band['width'] }}%"
                            title="{{ $band['name'] }}"
                        >
                            <span class="hidden truncate sm:inline">{{ $band['name'] }}</span>
                            <span class="sm:hidden">{{ $band['short'] }}</span>
                        </span>
                    @endforeach

                    {{-- Vacanțe & sărbători (lane subțire). --}}
                    @foreach ($axis['holidays'] as $band)
                        <span
                            class="absolute bottom-7 h-2.5 rounded-sm {{ $band['class'] }}"
                            style="left: {{ $band['left'] }}%; width: {{ $band['width'] }}%"
                            title="{{ $band['title'] }}"
                        ></span>
                    @endforeach

                    {{-- Sesiunile de corigență. --}}
                    @foreach ($axis['sessions'] as $band)
                        <span
                            class="absolute bottom-3 h-2.5 rounded-sm bg-fuchsia-400 dark:bg-fuchsia-500"
                            style="left: {{ $band['left'] }}%; width: {{ $band['width'] }}%"
                            title="{{ $band['title'] }}"
                        ></span>
                    @endforeach

                    {{-- Reperul AZI. --}}
                    @if ($axis['today'] !== null)
                        <span
                            class="absolute inset-y-0 w-0.5 bg-red-500"
                            style="left: {{ $axis['today'] }}%"
                            title="{{ __('panel.terms_axis.today') }}"
                            aria-hidden="true"
                        ></span>
                    @endif
                </div>

                {{-- Etichetele lunilor (pe mobil, doar din 2 în 2). --}}
                <div class="relative mt-1 h-4">
                    @foreach ($axis['months'] as $month)
                        <span
                            class="absolute -translate-x-1/2 text-[10px] uppercase text-gray-400 dark:text-gray-500 {{ $loop->odd ? '' : 'hidden sm:inline' }}"
                            style="left: {{ $month['left'] }}%"
                        >{{ $month['label'] }}</span>
                    @endforeach
                </div>

                {{-- Legenda. --}}
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-4 rounded-sm bg-primary-600" aria-hidden="true"></span>{{ __('panel.terms_axis.legend_term_current') }}</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-4 rounded-sm bg-primary-100 dark:bg-primary-500/25" aria-hidden="true"></span>{{ __('panel.terms_axis.legend_term') }}</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-4 rounded-sm bg-green-400 dark:bg-green-500" aria-hidden="true"></span>{{ __('panel.terms_axis.legend_holidays') }}</span>
                    @if ($axis['sessions'] !== [])
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-4 rounded-sm bg-fuchsia-400 dark:bg-fuchsia-500" aria-hidden="true"></span>{{ __('panel.terms_axis.legend_sessions') }}</span>
                    @endif
                    @if ($axis['today'] !== null)
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-0.5 bg-red-500" aria-hidden="true"></span>{{ __('panel.terms_axis.today') }}</span>
                    @endif
                </div>
            </section>

            {{-- Cardurile semestrelor. --}}
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($cards as $card)
                    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 {{ $card['status'] === 'current' ? 'ring-2 ring-primary-600/40 dark:ring-primary-500/40' : '' }}">
                        <div class="flex items-start justify-between gap-2">
                            <span class="min-w-0">
                                <span class="block truncate text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $card['name'] }}
                                </span>
                                <span class="mt-0.5 block text-sm tabular-nums text-gray-500 dark:text-gray-400">
                                    {{ $card['interval'] ?? __('panel.terms_axis.no_interval') }}
                                </span>
                            </span>

                            @if ($card['status'] === 'current')
                                <x-filament::badge color="primary" size="sm">{{ __('panel.terms_axis.status.current') }}</x-filament::badge>
                            @elseif ($card['status'] === 'past')
                                <x-filament::badge color="gray" size="sm">{{ __('panel.terms_axis.status.past') }}</x-filament::badge>
                            @elseif ($card['status'] === 'future')
                                <x-filament::badge color="info" size="sm">{{ __('panel.terms_axis.status.future') }}</x-filament::badge>
                            @elseif ($card['status'] === 'undated')
                                <x-filament::badge color="danger" size="sm">{{ __('panel.terms_axis.status.undated') }}</x-filament::badge>
                            @endif
                        </div>

                        @if ($card['weeks'] !== null)
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ __('panel.terms_axis.duration', ['weeks' => $card['weeks'], 'days' => $card['school_days']]) }}
                            </p>
                        @endif

                        @if ($card['progress'] !== null)
                            <div class="mt-2">
                                <div class="flex items-baseline justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ __('panel.terms_axis.week_of', ['week' => $card['progress']['week'], 'weeks' => $card['progress']['weeks']]) }}</span>
                                    <span class="tabular-nums">{{ $card['progress']['percent'] }}%</span>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                    <div class="h-full rounded-full bg-primary-600" style="width: {{ $card['progress']['percent'] }}%"></div>
                                </div>
                            </div>
                        @endif

                        {{-- Datele ancorate de semestru — cât „cântărește" el în catalog. --}}
                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            <span class="tabular-nums">{{ __('panel.terms_axis.counts.grades', ['count' => number_format($card['counts']['grades'], 0, ',', '.')]) }}</span>
                            <span class="tabular-nums">{{ __('panel.terms_axis.counts.absences', ['count' => number_format($card['counts']['absences'], 0, ',', '.')]) }}</span>
                            <span class="tabular-nums">{{ __('panel.terms_axis.counts.averages', ['count' => number_format($card['counts']['averages'], 0, ',', '.')]) }}</span>
                            <span class="tabular-nums">{{ __('panel.terms_axis.counts.validations', ['count' => number_format($card['counts']['validations'], 0, ',', '.')]) }}</span>
                        </div>

                        @if ($card['drift'] > 0)
                            <p class="mt-2 flex items-start gap-1.5 text-xs text-warning-700 dark:text-warning-400">
                                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                {{ trans_choice('panel.terms_axis.card_drift', $card['drift'], ['count' => $card['drift']]) }}
                            </p>
                        @endif

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if ($card['edit_url'] !== null)
                                <a
                                    href="{{ $card['edit_url'] }}"
                                    class="rounded-full bg-white px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-950/10 transition duration-75 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10"
                                >
                                    {{ __('filament-actions::edit.single.label') }}
                                </a>
                            @elseif ($this->isYearClosed())
                                <span class="flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500">
                                    <x-filament::icon icon="heroicon-o-lock-closed" class="h-3.5 w-3.5" />
                                    {{ __('panel.terms_axis.locked') }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
