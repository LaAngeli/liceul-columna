{{-- Înmatriculări: registrul claselor — pastile pe ani școlari → cardurile claselor (elevi
     activi / plecați) → registrul clasei (tabelul înmatriculărilor ei, cu plecarea din rând
     și adăugarea pre-completată pe clasă). --}}
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

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('panel.catalog_nav.enrollments_class_hint') }}
            </p>

            {{ $this->table }}
        </div>
    @else
        {{-- ── Aterizare: ani → cardurile claselor ────────────────────────────────────── --}}
        <div class="space-y-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $this->enrollmentsHint() }}
            </p>

            {{-- Semnalele registrului — înaintea efectelor (elev fără catalog, duplicat blocat). --}}
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
                    <x-filament::icon icon="heroicon-o-clipboard-document-list" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
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
                            class="group rounded-xl bg-white p-4 text-start shadow-sm ring-1 ring-gray-950/5 transition duration-75 hover:ring-2 hover:ring-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 disabled:pointer-events-none disabled:opacity-70 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-500"
                        >
                            <span class="flex items-start justify-between gap-2">
                                <span class="min-w-0 truncate text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                    {{ $card['title'] }}
                                </span>
                                @if ($card['no_homeroom'])
                                    <x-filament::badge color="warning" size="sm">{{ __('panel.enrollments_nav.no_homeroom') }}</x-filament::badge>
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
