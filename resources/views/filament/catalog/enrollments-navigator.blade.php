{{-- Înmatriculări: registrul școlii — anul (cu progresul înmatriculării) → clasele, căutabile și
     grupate pe cicluri → registrul clasei, cu adăugare în masă. Restructurat 2026-08-02: grila
     plată de 52 de carduri și avertismentul „773 neînmatriculați" fără nicio cale de rezolvare
     în masă erau exact ce făcea secțiunea greu de operat. --}}
<x-filament-panels::page>
    @php($class = $this->activeClass())

    @if ($class !== null)
        {{-- ── Registrul clasei active ────────────────────────────────────────────────── --}}
        <div class="space-y-6">
            <div class="flex flex-wrap items-center gap-3">
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

                @php($roster = $this->rosterCounts())
                <span class="ms-auto flex flex-wrap items-center gap-1.5">
                    @if ($class->homeroomTeacher === null)
                        <x-filament::badge color="warning" size="sm">{{ __('panel.enrollments_nav.no_homeroom') }}</x-filament::badge>
                    @endif
                    <x-filament::badge color="success" size="sm">
                        {{ trans_choice('panel.catalog_nav.active_students', $roster['active'], ['count' => $roster['active']]) }}
                    </x-filament::badge>
                    @if ($roster['departed'] > 0)
                        <x-filament::badge color="gray" size="sm">
                            {{ trans_choice('panel.catalog_nav.departed_students', $roster['departed'], ['count' => $roster['departed']]) }}
                        </x-filament::badge>
                    @endif
                </span>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('panel.catalog_nav.enrollments_class_hint') }}
                </p>

                {{-- Adăugarea în MASĂ: până acum, completarea unei clase însemna un formular per elev. --}}
                {{ $this->addStudentsAction }}
            </div>

            {{ $this->table }}
        </div>
    @else
        {{-- ── Aterizare: anul (progres) → clasele grupate pe cicluri ─────────────────── --}}
        <div class="space-y-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $this->enrollmentsHint() }}
            </p>

            @foreach ($this->integrity() as $signal)
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

            @php($years = $this->yearPills())

            @if (count($years) > 1)
                <x-filament::tabs :label="__('panel.fields.academic_year')">
                    @foreach ($years as $year)
                        <x-filament::tabs.item
                            :active="$this->activeYearId() === $year['id']"
                            :badge="$year['count']"
                            :icon="$year['current'] ? 'heroicon-m-star' : null"
                            wire:click="openYear({{ $year['id'] }})"
                        >
                            {{ $year['label'] }}
                        </x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>
            @endif

            {{-- Progresul înmatriculării anului: numărul care spune dacă registrul e gata sau abia
                 început — înainte, asta se deducea dintr-un avertisment fără cifre de referință. --}}
            @php($progress = $this->yearProgress())

            @if ($progress['total'] > 0)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ __('panel.enrollments_nav.progress.title') }}
                        </p>
                        <p class="text-sm tabular-nums text-gray-500 dark:text-gray-400">
                            {{ __('panel.enrollments_nav.progress.counts', ['enrolled' => $progress['enrolled'], 'total' => $progress['total']]) }}
                        </p>
                    </div>

                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div
                            @class([
                                'h-full rounded-full transition-all',
                                'bg-success-500' => $progress['percent'] === 100,
                                'bg-primary-500' => $progress['percent'] < 100,
                            ])
                            style="width: {{ max($progress['percent'], 2) }}%"
                        ></div>
                    </div>
                </div>
            @endif

            @php($groups = $this->classGroups())

            @if ($this->yearClassCount() > 6)
                <x-filament::input.wrapper
                    prefix-icon="heroicon-m-magnifying-glass"
                    :suffix-actions="[]"
                >
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.300ms="classSearch"
                        :placeholder="__('panel.enrollments_nav.search_class')"
                    />
                </x-filament::input.wrapper>
            @endif

            @if (count($groups) === 0)
                <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-clipboard-document-list" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                    <p class="text-sm font-medium text-gray-950 dark:text-white">
                        {{ trim($this->classSearch) !== '' ? __('panel.enrollments_nav.search_empty') : __('panel.catalog_nav.empty_title') }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.catalog_nav.empty_description') }}</p>
                </div>
            @else
                @foreach ($groups as $group)
                    <div class="space-y-3">
                        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $group['label'] }}</h3>

                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                            @foreach ($group['cards'] as $card)
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
                                        @if ($card['no_homeroom'])
                                            <x-filament::badge color="warning" size="sm">{{ __('panel.enrollments_nav.no_homeroom') }}</x-filament::badge>
                                        @endif
                                    </span>

                                    <span class="mt-0.5 block truncate text-sm text-gray-500 dark:text-gray-400">
                                        {{ $card['subtitle'] ?? __('panel.enrollments_nav.no_homeroom_subtitle') }}
                                    </span>

                                    <span class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                        @if ($card['active'] === 0 && $card['departed'] === 0)
                                            <span class="text-warning-600 dark:text-warning-400">{{ __('panel.catalog_nav.no_enrollments') }}</span>
                                        @else
                                            <span>{{ trans_choice('panel.catalog_nav.active_students', $card['active'], ['count' => $card['active']]) }}</span>
                                            @if ($card['departed'] > 0)
                                                <span>{{ trans_choice('panel.catalog_nav.departed_students', $card['departed'], ['count' => $card['departed']]) }}</span>
                                            @endif
                                        @endif
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif

            {{-- ── Neînmatriculații anului activ: lista de lucru + înscriere rapidă ─────── --}}
            @php($unassigned = $this->unassigned())

            @if ($unassigned['count'] > 0)
                <div class="rounded-xl bg-white shadow-sm ring-1 ring-warning-600/30 dark:bg-gray-900 dark:ring-warning-500/30">
                    <button
                        type="button"
                        wire:click="toggleUnassigned"
                        class="flex w-full items-center justify-between gap-3 rounded-xl p-4 text-start focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-warning-600"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <x-filament::icon icon="heroicon-o-user-minus" class="h-5 w-5 shrink-0 text-warning-600 dark:text-warning-400" />
                            <span class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                {{ trans_choice('panel.enrollments_nav.unassigned.title', $unassigned['count'], ['count' => $unassigned['count']]) }}
                            </span>
                        </span>
                        <x-filament::icon
                            :icon="$this->showUnassigned ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'"
                            class="h-4 w-4 shrink-0 text-gray-400"
                        />
                    </button>

                    @if ($this->showUnassigned)
                        <ul class="divide-y divide-gray-100 border-t border-gray-100 dark:divide-white/5 dark:border-white/10">
                            @foreach ($unassigned['students'] as $student)
                                <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-gray-950 dark:text-white">{{ $student['name'] }}</span>
                                        @if ($student['register'] !== null)
                                            <span class="block text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ $student['register'] }}</span>
                                        @endif
                                    </span>
                                    <a
                                        href="{{ $student['enroll_url'] }}"
                                        class="shrink-0 rounded-full bg-white px-3 py-1 text-sm font-medium text-primary-600 ring-1 ring-primary-600/30 transition duration-75 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:bg-white/5 dark:text-primary-400 dark:ring-primary-400/30 dark:hover:bg-white/10"
                                    >
                                        {{ __('panel.enrollments_nav.unassigned.enroll') }}
                                    </a>
                                </li>
                            @endforeach

                            @if ($unassigned['count'] > count($unassigned['students']))
                                <li class="px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('panel.enrollments_nav.unassigned.more', ['count' => $unassigned['count'] - count($unassigned['students'])]) }}
                                </li>
                            @endif
                        </ul>
                    @endif
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
