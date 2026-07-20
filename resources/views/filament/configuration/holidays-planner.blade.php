{{-- Planificatorul zilelor libere: pastile pe ani → „următoarea zi liberă" + pastile de categorie
     (cu numărul de ZILE) → calendarul anual (lunile sept→aug, intervalele colorate pe categorii,
     azi inelat) → cronologia detaliată (carduri, nu tabel). Scrierea doar pentru AO/super-admin;
     ceilalți văd aceeași imagine fără afordanțe de scriere. --}}
<x-filament-panels::page>
    <div class="space-y-6">
        @php($years = $this->yearPills())
        @php($pills = $this->typePills())
        @php($next = $this->nextFreeDay())
        @php($months = $this->months())
        @php($timeline = $this->timeline())
        @php($initials = $this->weekdayInitials())
        @php($canWrite = $this->canWrite())

        <div class="flex flex-wrap items-center justify-between gap-3">
            @if (count($years) > 1)
                <x-filament::tabs :label="__('panel.fields.academic_year')">
                    @foreach ($years as $year)
                        <x-filament::tabs.item
                            :active="$this->activeYear()?->id === $year['id']"
                            :badge="$year['count'] > 0 ? $year['count'] : null"
                            wire:click="openYear({{ $year['id'] }})"
                        >
                            {{ $year['label'] }}
                        </x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>
            @else
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ $this->activeYear()?->name }}
                </p>
            @endif

            <x-filament::input.wrapper class="w-full sm:w-64">
                <x-slot name="prefix">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-4 w-4 text-gray-400" />
                </x-slot>
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.400ms="search"
                    :placeholder="__('panel.holiday_planner.search_placeholder')"
                />
            </x-filament::input.wrapper>
        </div>

        {{-- Rândul de stare: următoarea zi liberă + pastilele de categorie (filtru + legendă). --}}
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-3 rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <x-filament::icon icon="heroicon-o-sun" class="h-6 w-6 shrink-0 text-primary-500" />
                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ __('panel.holiday_planner.next_free') }}
                    </p>
                    @if ($next === null)
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.holiday_planner.next_free_none') }}</p>
                    @else
                        <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $next['holiday']->name }}
                            <span class="font-normal text-gray-500 dark:text-gray-400">
                                ·
                                @if ($next['ongoing'])
                                    {{ __('panel.holiday_planner.ongoing') }}
                                @else
                                    {{ trans_choice('panel.holiday_planner.in_days', $next['in_days'], ['count' => $next['in_days']]) }}
                                @endif
                            </span>
                        </p>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('panel.holiday_planner.categories') }}">
                @foreach ($pills as $pill)
                    <button
                        type="button"
                        wire:click="openType({{ $pill['value'] === null ? 'null' : '\''.$pill['value'].'\'' }})"
                        @class([
                            'inline-flex min-h-9 items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium transition duration-75 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600',
                            'bg-primary-600 text-white shadow-sm dark:bg-primary-500' => $pill['active'],
                            'bg-white text-gray-700 ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10' => ! $pill['active'],
                        ])
                    >
                        @if ($pill['dot'] !== null)
                            <span class="h-2 w-2 rounded-full {{ $pill['dot'] }}" aria-hidden="true"></span>
                        @endif
                        {{ $pill['label'] }}
                        <span @class([
                            'text-xs tabular-nums',
                            'text-primary-100' => $pill['active'],
                            'text-gray-400 dark:text-gray-500' => ! $pill['active'],
                        ])>{{ trans_choice('panel.holiday_planner.days_count', $pill['days'], ['count' => $pill['days']]) }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        @if ($timeline === [] )
            <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <x-filament::icon icon="heroicon-o-sun" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.holiday_planner.empty_title') }}</p>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                    {{ $canWrite ? __('panel.holiday_planner.empty_description') : __('panel.holiday_planner.empty_description_reader') }}
                </p>
            </div>
        @else
            {{-- Calendarul anual: lunile anului școlar, intervalele colorate pe categorii. --}}
            <section aria-label="{{ __('panel.holiday_planner.calendar') }}">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($months as $month)
                        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <div class="flex items-baseline justify-between gap-2">
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $month['label'] }}</h3>
                                @if ($month['free_days'] > 0)
                                    <span class="text-xs tabular-nums text-gray-400 dark:text-gray-500">
                                        {{ trans_choice('panel.holiday_planner.days_count', $month['free_days'], ['count' => $month['free_days']]) }}
                                    </span>
                                @endif
                            </div>

                            <div class="mt-2 grid grid-cols-7 text-center">
                                @foreach ($initials as $initial)
                                    <span class="pb-1 text-[0.65rem] font-medium uppercase text-gray-400 dark:text-gray-500">{{ $initial }}</span>
                                @endforeach

                                @foreach ($month['weeks'] as $week)
                                    @foreach ($week as $day)
                                        @if (! $day['in_month'])
                                            <span aria-hidden="true"></span>
                                        @elseif ($day['holiday'] !== null)
                                            @php($shape = ($day['holiday']['is_start'] ? 'rounded-l-md ' : '').($day['holiday']['is_end'] ? 'rounded-r-md' : ''))
                                            @if ($day['holiday']['edit_url'] !== null)
                                                <a
                                                    href="{{ $day['holiday']['edit_url'] }}"
                                                    title="{{ $day['holiday']['name'] }}"
                                                    @class([
                                                        'flex h-8 items-center justify-center text-xs font-semibold tabular-nums transition duration-75 hover:brightness-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:hover:brightness-110',
                                                        $day['holiday']['cell'],
                                                        $shape,
                                                        'ring-2 ring-inset ring-primary-600 dark:ring-primary-400' => $day['today'],
                                                    ])
                                                >{{ $day['day'] }}</a>
                                            @else
                                                <span
                                                    title="{{ $day['holiday']['name'] }}"
                                                    @class([
                                                        'flex h-8 items-center justify-center text-xs font-semibold tabular-nums',
                                                        $day['holiday']['cell'],
                                                        $shape,
                                                        'ring-2 ring-inset ring-primary-600 dark:ring-primary-400' => $day['today'],
                                                    ])
                                                >{{ $day['day'] }}</span>
                                            @endif
                                        @else
                                            <span @class([
                                                'flex h-8 items-center justify-center text-xs tabular-nums',
                                                'text-gray-300 dark:text-gray-600' => $day['weekend'] && ! $day['today'],
                                                'text-gray-600 dark:text-gray-300' => ! $day['weekend'] && ! $day['today'],
                                                'rounded-md font-semibold text-primary-700 ring-2 ring-inset ring-primary-600 dark:text-primary-300 dark:ring-primary-400' => $day['today'],
                                            ])>{{ $day['day'] }}</span>
                                        @endif
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Cronologia: fiecare zi liberă drept card, grupată pe luni. --}}
            <section aria-label="{{ __('panel.holiday_planner.timeline') }}" class="space-y-2">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('panel.holiday_planner.timeline') }}
                </h2>

                @php($previousMonth = null)
                <div class="space-y-2">
                    @foreach ($timeline as $entry)
                        @if ($entry['month'] !== $previousMonth)
                            @php($previousMonth = $entry['month'])
                            <p class="pt-2 text-xs font-medium uppercase tracking-wide text-gray-400 first:pt-0 dark:text-gray-500">
                                {{ $entry['month'] }}
                            </p>
                        @endif

                        @php($card = 'group flex items-center gap-3 rounded-xl bg-white px-4 py-3 shadow-sm ring-1 transition duration-75 dark:bg-gray-900')
                        @php($ring = $entry['current'] ? 'ring-primary-500 dark:ring-primary-400' : 'ring-gray-950/5 dark:ring-white/10')
                        @php($hover = $entry['edit_url'] !== null ? ' hover:ring-2 hover:ring-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:hover:ring-primary-500' : '')

                        @if ($entry['edit_url'] !== null)
                            <a href="{{ $entry['edit_url'] }}" class="{{ $card }} {{ $ring }}{{ $hover }}">
                        @else
                            <div class="{{ $card }} {{ $ring }}">
                        @endif
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $entry['dot'] }}" aria-hidden="true"></span>

                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        <span @class([
                                            'truncate text-sm font-medium',
                                            'text-gray-950 dark:text-white' => ! $entry['past'],
                                            'text-gray-400 dark:text-gray-500' => $entry['past'],
                                        ])>{{ $entry['holiday']->name }}</span>

                                        @if ($entry['current'])
                                            <x-filament::badge color="primary" size="sm">{{ __('panel.holiday_planner.ongoing') }}</x-filament::badge>
                                        @endif
                                    </span>

                                    <span class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="tabular-nums">{{ $entry['range'] }}</span>
                                        <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">·</span>
                                        <span>{{ trans_choice('panel.holiday_planner.days_count', $entry['days'], ['count' => $entry['days']]) }}</span>
                                        <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">·</span>
                                        <span>{{ $entry['holiday']->type->label() }}</span>
                                        @if ($entry['holiday']->note !== null && $entry['holiday']->note !== '')
                                            <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">·</span>
                                            <span class="truncate">{{ $entry['holiday']->note }}</span>
                                        @endif
                                    </span>
                                </span>

                                @if ($entry['edit_url'] !== null)
                                    <x-filament::icon
                                        icon="heroicon-o-pencil-square"
                                        class="h-4 w-4 shrink-0 text-gray-300 transition duration-75 group-hover:text-primary-600 dark:text-gray-600 dark:group-hover:text-primary-400"
                                    />
                                @endif
                        @if ($entry['edit_url'] !== null)
                            </a>
                        @else
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- Pagina e HasTable (ListRecords), deci layout-ul Filament NU include modalele de acțiuni —
         le-ar fi adus view-ul tabelului (tables::index), pe care planificatorul nu-l randează.
         Fără includerea explicită, modalul generatorului de sărbători se montează pe server,
         dar nu apare niciodată în DOM. --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
