{{-- Orar structurat: pastile pe ani → cardurile claselor (lecții/săptămână; „fără orar" =
     avertisment) → GRILA SĂPTĂMÂNALĂ a clasei (zi × lecție): azi evidențiat, „Acum"/„Urmează"
     din orele orarului publicat, celulele libere = adăugare pre-completată. Lista clasică rămâne
     vedere secundară (?vedere=lista) pentru filtre și operațiuni pe rânduri. --}}
<x-filament-panels::page>
    @php($class = $this->activeClass())

    @if ($class !== null)
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <x-filament::icon-button
                        icon="heroicon-o-arrow-uturn-left"
                        color="gray"
                        wire:click="leaveClass"
                        :label="__('panel.catalog_nav.back')"
                        :tooltip="__('panel.catalog_nav.back')"
                    />

                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            {{ __('panel.fields.class') }}
                        </p>
                        <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                            {{ trim($class->name.' '.($class->section ?? '')) }}
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                · {{ $class->academicYear->name ?? '' }}
                                @if ($class->homeroomTeacher !== null)
                                    · {{ $class->homeroomTeacher->full_name }}
                                @endif
                            </span>
                        </h2>
                    </div>
                </div>

                <x-filament::tabs :label="__('panel.config_nav.timetable_view')">
                    <x-filament::tabs.item
                        :active="! $this->showListView()"
                        icon="heroicon-o-table-cells"
                        wire:click="openGridView"
                    >
                        {{ __('panel.config_nav.grid_view') }}
                    </x-filament::tabs.item>
                    <x-filament::tabs.item
                        :active="$this->showListView()"
                        icon="heroicon-o-list-bullet"
                        wire:click="openListView"
                    >
                        {{ __('panel.config_nav.list_view') }}
                    </x-filament::tabs.item>
                </x-filament::tabs>
            </div>

            @if ($this->showListView())
                {{ $this->table }}
            @else
                @php($grid = $this->timetableGrid())

                @if ($grid === null)
                    <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <x-filament::icon icon="heroicon-o-table-cells" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                        <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.config_nav.no_timetable') }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.config_nav.timetable_reader_empty') }}</p>
                    </div>
                @else
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                        <span>{{ trans_choice('panel.config_nav.lessons_per_week', $grid['lessons'], ['count' => $grid['lessons']]) }}</span>
                        <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">·</span>
                        <span>{{ trans_choice('panel.config_nav.subjects_count', $grid['subjects'], ['count' => $grid['subjects']]) }}</span>

                        @if ($grid['holiday_today'])
                            <x-filament::badge color="gray" size="sm">
                                {{ __('panel.config_nav.timetable_free_day') }}
                            </x-filament::badge>
                        @endif
                    </div>

                    {{-- ≥ md: grila propriu-zisă (matricea e forma naturală a orarului). --}}
                    <div class="hidden overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 md:block dark:bg-gray-900 dark:ring-white/10">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-white/10">
                                    <th class="sticky left-0 z-10 w-28 bg-white px-3 py-2.5 text-left align-bottom dark:bg-gray-900">
                                        <span class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                            {{ __('panel.forms.lesson.period') }}
                                        </span>
                                    </th>
                                    @foreach ($grid['days'] as $day)
                                        <th @class([
                                            'px-2 py-2.5 text-center align-bottom',
                                            'bg-primary-50/60 dark:bg-primary-500/10' => $grid['today'] === $day['value'],
                                        ])>
                                            <span @class([
                                                'block text-sm font-semibold',
                                                'text-primary-700 dark:text-primary-300' => $grid['today'] === $day['value'],
                                                'text-gray-950 dark:text-white' => $grid['today'] !== $day['value'],
                                            ])>{{ $day['label'] }}</span>
                                            <span class="mt-0.5 block text-xs font-normal text-gray-400 dark:text-gray-500">
                                                @if ($grid['today'] === $day['value'])
                                                    {{ __('panel.config_nav.timetable_today') }} ·
                                                @endif
                                                {{ $day['count'] }} {{ __('panel.config_nav.timetable_lessons_short') }}
                                            </span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                @foreach ($grid['slots'] as $slot)
                                    <tr>
                                        <th class="sticky left-0 z-10 bg-white px-3 py-2 text-left align-top dark:bg-gray-900">
                                            <span class="flex items-center gap-1.5">
                                                <span class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ $slot }}</span>
                                                @if ($grid['current_slot'] === $slot)
                                                    <x-filament::badge color="success" size="sm">{{ __('panel.config_nav.timetable_now') }}</x-filament::badge>
                                                @elseif ($grid['next_slot'] === $slot)
                                                    <x-filament::badge color="info" size="sm">{{ __('panel.config_nav.timetable_next') }}</x-filament::badge>
                                                @endif
                                            </span>
                                            @if (isset($grid['times'][$slot]))
                                                <span class="mt-0.5 block whitespace-nowrap text-xs tabular-nums text-gray-400 dark:text-gray-500">
                                                    {{ $grid['times'][$slot]['label'] }}
                                                </span>
                                            @endif
                                        </th>

                                        @foreach ($grid['days'] as $day)
                                            @php($cell = $grid['cells'][$day['value']][$slot] ?? null)
                                            <td @class([
                                                'p-1.5 align-top',
                                                'bg-primary-50/60 dark:bg-primary-500/10' => $grid['today'] === $day['value'],
                                            ])>
                                                @if ($cell !== null)
                                                    @php($isNow = $grid['today'] === $day['value'] && $grid['current_slot'] === $slot)
                                                    @if ($cell['edit_url'] !== null)
                                                        <a href="{{ $cell['edit_url'] }}" @class([
                                                            'block rounded-lg px-2.5 py-2 ring-1 transition duration-75 hover:ring-2 hover:ring-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:hover:ring-primary-500',
                                                            'bg-white ring-gray-950/10 dark:bg-white/5 dark:ring-white/10' => ! $isNow,
                                                            'bg-white ring-2 ring-success-500 dark:bg-white/5 dark:ring-success-400' => $isNow,
                                                        ])>
                                                            <span class="block truncate text-sm font-medium text-gray-950 dark:text-white">{{ $cell['subject'] }}</span>
                                                            @if ($cell['teacher'] !== null || $cell['room'] !== null)
                                                                <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">
                                                                    {{ $cell['teacher'] ?? '' }}@if ($cell['teacher'] !== null && $cell['room'] !== null) · @endif@if ($cell['room'] !== null){{ __('panel.forms.lesson.room') }} {{ $cell['room'] }}@endif
                                                                </span>
                                                            @endif
                                                        </a>
                                                    @else
                                                        <div @class([
                                                            'rounded-lg px-2.5 py-2 ring-1',
                                                            'bg-white ring-gray-950/10 dark:bg-white/5 dark:ring-white/10' => ! $isNow,
                                                            'bg-white ring-2 ring-success-500 dark:bg-white/5 dark:ring-success-400' => $isNow,
                                                        ])>
                                                            <span class="block truncate text-sm font-medium text-gray-950 dark:text-white">{{ $cell['subject'] }}</span>
                                                            @if ($cell['teacher'] !== null || $cell['room'] !== null)
                                                                <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">
                                                                    {{ $cell['teacher'] ?? '' }}@if ($cell['teacher'] !== null && $cell['room'] !== null) · @endif@if ($cell['room'] !== null){{ __('panel.forms.lesson.room') }} {{ $cell['room'] }}@endif
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                @elseif ($grid['can_write'])
                                                    <a
                                                        href="{{ $this->createSlotUrl($day['value'], $slot) }}"
                                                        aria-label="{{ __('panel.config_nav.timetable_add_at', ['day' => $day['label'], 'slot' => $slot]) }}"
                                                        class="flex min-h-11 items-center justify-center rounded-lg border border-dashed border-gray-200 text-gray-300 transition duration-75 hover:border-primary-400 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:border-white/10 dark:text-gray-600 dark:hover:border-primary-500 dark:hover:text-primary-400"
                                                    >
                                                        <x-filament::icon icon="heroicon-o-plus" class="h-4 w-4" />
                                                    </a>
                                                @else
                                                    <div class="flex min-h-11 items-center justify-center text-gray-200 dark:text-gray-700" aria-hidden="true">—</div>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- < md: secțiuni pe zile (grila ar cere scroll orizontal — interzis pe mobil). --}}
                    <div class="space-y-3 md:hidden">
                        @foreach ($grid['days'] as $day)
                            @php($dayLessons = collect($grid['slots'])->filter(fn (int $slot): bool => isset($grid['cells'][$day['value']][$slot])))
                            <section @class([
                                'rounded-xl bg-white p-4 shadow-sm ring-1 dark:bg-gray-900',
                                'ring-primary-400 dark:ring-primary-500' => $grid['today'] === $day['value'],
                                'ring-gray-950/5 dark:ring-white/10' => $grid['today'] !== $day['value'],
                            ])>
                                <div class="flex items-baseline justify-between gap-2">
                                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $day['label'] }}
                                        @if ($grid['today'] === $day['value'])
                                            <x-filament::badge color="primary" size="sm">{{ __('panel.config_nav.timetable_today') }}</x-filament::badge>
                                        @endif
                                    </h3>
                                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ $day['count'] }} {{ __('panel.config_nav.timetable_lessons_short') }}</span>
                                </div>

                                @if ($dayLessons->isEmpty())
                                    <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">{{ __('panel.config_nav.timetable_day_empty') }}</p>
                                @else
                                    <ul class="mt-2 divide-y divide-gray-100 dark:divide-white/10">
                                        @foreach ($dayLessons as $slot)
                                            @php($cell = $grid['cells'][$day['value']][$slot])
                                            @php($isNow = $grid['today'] === $day['value'] && $grid['current_slot'] === $slot)
                                            <li>
                                                @php($inner = 'flex items-start gap-3 py-2')
                                                @if ($cell['edit_url'] !== null)
                                                    <a href="{{ $cell['edit_url'] }}" class="{{ $inner }}">
                                                @else
                                                    <div class="{{ $inner }}">
                                                @endif
                                                    <span class="flex w-14 shrink-0 flex-col text-xs tabular-nums text-gray-400 dark:text-gray-500">
                                                        <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $slot }}</span>
                                                        @if (isset($grid['times'][$slot]))
                                                            {{ $grid['times'][$slot]['start'] }}
                                                        @endif
                                                    </span>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="flex items-center gap-1.5">
                                                            <span class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $cell['subject'] }}</span>
                                                            @if ($isNow)
                                                                <x-filament::badge color="success" size="sm">{{ __('panel.config_nav.timetable_now') }}</x-filament::badge>
                                                            @elseif ($grid['today'] === $day['value'] && $grid['next_slot'] === $slot)
                                                                <x-filament::badge color="info" size="sm">{{ __('panel.config_nav.timetable_next') }}</x-filament::badge>
                                                            @endif
                                                        </span>
                                                        @if ($cell['teacher'] !== null || $cell['room'] !== null)
                                                            <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">
                                                                {{ $cell['teacher'] ?? '' }}@if ($cell['teacher'] !== null && $cell['room'] !== null) · @endif@if ($cell['room'] !== null){{ __('panel.forms.lesson.room') }} {{ $cell['room'] }}@endif
                                                            </span>
                                                        @endif
                                                    </span>
                                                @if ($cell['edit_url'] !== null)
                                                    </a>
                                                @else
                                                    </div>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if ($grid['can_write'])
                                    <a
                                        href="{{ $this->createSlotUrl($day['value'], $dayLessons->max() !== null ? min($dayLessons->max() + 1, 8) : 1) }}"
                                        class="mt-2 inline-flex min-h-11 items-center gap-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        <x-filament::icon icon="heroicon-o-plus" class="h-4 w-4" />
                                        {{ __('panel.config_nav.timetable_add_lesson') }}
                                    </a>
                                @endif
                            </section>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    @else
        <div class="space-y-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $this->configHint() }}
            </p>

            @php($years = $this->yearPills())

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

            @php($cards = $this->classCards())

            @if (count($cards) === 0)
                <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-clock" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.catalog_nav.empty_title') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.catalog_nav.empty_description') }}</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($cards as $card)
                        <button
                            type="button"
                            wire:click="openClass({{ $card['id'] }})"
                            wire:loading.attr="disabled"
                            class="group min-w-0 rounded-xl bg-white p-4 text-start shadow-sm ring-1 ring-gray-950/5 transition duration-75 hover:ring-2 hover:ring-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 disabled:pointer-events-none disabled:opacity-70 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-500"
                        >
                            <span class="flex items-start justify-between gap-2">
                                <span class="min-w-0 truncate text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                    {{ $card['title'] }}
                                </span>

                                @if ($card['badge'] !== null)
                                    <x-filament::badge color="warning" size="sm">
                                        {{ $card['badge'] }}
                                    </x-filament::badge>
                                @endif
                            </span>

                            @if ($card['subtitle'] !== null)
                                <span class="mt-0.5 block truncate text-sm text-gray-500 dark:text-gray-400">
                                    {{ $card['subtitle'] }}
                                </span>
                            @endif

                            <span class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                @foreach ($card['stats'] as $stat)
                                    <span>{{ $stat }}</span>
                                @endforeach
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
