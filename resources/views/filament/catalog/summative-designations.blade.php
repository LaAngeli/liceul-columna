{{-- Discipline cu sumativă: pastile pe ani → ACOPERIRE + carduri de clasă (grupate pe cicluri)
     → tabelul designărilor unei clase. Designarea e o proprietate a clasei, deci așa se citește:
     un card per clasă, cu disciplinele ca etichete și cu starea gărzii scrisă pe card. --}}
<x-filament-panels::page>
    <div class="space-y-6">
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

        @if ($this->hasClassContext())
            {{-- ── Designările unei clase ─────────────────────────────────────────────── --}}
            @php($class = $this->activeClass())

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
                        {{ __('grading.designation.fields.class') }}
                    </p>
                    <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                        {{ trim($class->name.' '.($class->section ?? '')) }}
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                            · {{ $this->summativeLabelFor($class) }}
                        </span>
                    </h2>
                </div>
            </div>

            {{ $this->table }}
        @else
            {{-- ── ACOPERIREA: singura informație operațională a secțiunii ─────────────── --}}
            @php($coverage = $this->coverage())

            @if ($coverage['total'] > 0)
                <div @class([
                    'rounded-xl p-4 ring-1',
                    'bg-warning-50 ring-warning-600/20 dark:bg-warning-400/10 dark:ring-warning-400/30' => $coverage['missing'] > 0,
                    'bg-success-50 ring-success-600/20 dark:bg-success-400/10 dark:ring-success-400/30' => $coverage['missing'] === 0,
                ])>
                    <p @class([
                        'flex flex-wrap items-center gap-1.5 text-sm font-semibold',
                        'text-warning-800 dark:text-warning-300' => $coverage['missing'] > 0,
                        'text-success-800 dark:text-success-300' => $coverage['missing'] === 0,
                    ])>
                        <span>{{ __('grading.designation.coverage.title', ['configured' => $coverage['configured'], 'total' => $coverage['total']]) }}</span>
                        {{-- Ghidul „i": efectul de prag (prima desemnare armează garda) nu se deduce din ecran. --}}
                        {{ $this->coverageHint() }}
                    </p>

                    @if ($coverage['missing'] > 0)
                        <p class="mt-1 text-sm text-warning-800/90 dark:text-warning-300/90">
                            {{ trans_choice('grading.designation.coverage.missing', $coverage['missing'], ['count' => $coverage['missing']]) }}
                            <span class="font-medium">{{ implode(', ', array_slice($coverage['missing_labels'], 0, 12)) }}@if (count($coverage['missing_labels']) > 12), …@endif</span>
                        </p>
                    @endif
                </div>
            @endif

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $this->configHint() }}
            </p>

            @php($groups = $this->classGroups())

            @if (count($groups) === 0)
                <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-tag" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('grading.designation.no_classes') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('grading.designation.no_classes_hint') }}</p>
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
                                    @class([
                                        'group min-w-0 rounded-xl bg-white p-4 text-start shadow-sm ring-1 transition duration-75 hover:ring-2 hover:ring-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 disabled:pointer-events-none disabled:opacity-70 dark:bg-gray-900 dark:hover:ring-primary-500',
                                        'ring-gray-950/5 dark:ring-white/10' => $card['configured'],
                                        'ring-warning-600/40 dark:ring-warning-400/40' => ! $card['configured'],
                                    ])
                                >
                                    <span class="flex items-start justify-between gap-2">
                                        <span class="min-w-0 truncate text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                            {{ $card['title'] }}
                                        </span>

                                        <x-filament::badge :color="$card['configured'] ? 'primary' : 'warning'" size="sm">
                                            {{ $card['type'] }}
                                        </x-filament::badge>
                                    </span>

                                    @if ($card['configured'])
                                        <span class="mt-3 flex flex-wrap gap-1.5">
                                            @foreach ($card['subjects'] as $subject)
                                                <span class="rounded-full bg-gray-50 px-2 py-0.5 text-xs text-gray-700 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                                                    {{ $subject }}
                                                </span>
                                            @endforeach
                                        </span>
                                    @else
                                        {{-- Clasa neconfigurată NU e „goală": e o clasă în care garda nu apără nimic. --}}
                                        <span class="mt-3 block text-xs font-medium text-warning-700 dark:text-warning-400">
                                            {{ __('grading.designation.card_unconfigured') }}
                                        </span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        @endif
    </div>

    {{-- Pagina de listare randează modalele prin TABEL, iar pe aterizare tabelul lipsește:
         fără linia asta, acțiunea „Desemnare în masă" nu s-ar deschide deloc. --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
