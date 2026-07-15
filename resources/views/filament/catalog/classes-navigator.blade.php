{{-- Clase: navigator cu carduri („ca la Elevi") — pastile pe ani școlari → cardurile claselor
     vizibile, cu sărituri directe în catalog. Înmatriculările, restaurarea și ștergerea trăiesc
     pe pagina de editare; clasele șterse au vederea dedicată „Clase șterse" (administrație). --}}
<x-filament-panels::page>
    @if ($this->isTrashedMode())
        <div class="space-y-6">
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::icon-button
                    icon="heroicon-o-arrow-uturn-left"
                    color="gray"
                    wire:click="leaveTrashed"
                    :label="__('panel.catalog_nav.back')"
                    :tooltip="__('panel.catalog_nav.back')"
                />

                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ __('panel.resources.school_classes.label') }}
                    </p>
                    <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                        {{ __('panel.catalog_nav.classes_trashed') }}
                    </h2>
                </div>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('panel.catalog_nav.classes_trashed_hint') }}
            </p>

            @php($trashed = $this->trashedCards())

            @if (count($trashed) === 0)
                <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-archive-box" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.catalog_nav.empty_title') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.catalog_nav.empty_description') }}</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($trashed as $card)
                        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <span class="block truncate text-base font-semibold text-gray-950 dark:text-white">
                                {{ $card['title'] }}
                            </span>

                            @if ($card['subtitle'] !== null)
                                <span class="mt-0.5 block truncate text-sm text-gray-500 dark:text-gray-400">
                                    {{ $card['subtitle'] }}
                                </span>
                            @endif

                            <span class="mt-2 block text-xs text-gray-500 dark:text-gray-400">
                                {{ $card['deleted'] }}
                            </span>

                            @if ($card['edit_url'] !== null)
                                <span class="mt-3 flex flex-wrap gap-2">
                                    <a
                                        href="{{ $card['edit_url'] }}"
                                        class="rounded-full bg-white px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-950/10 transition duration-75 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10"
                                    >
                                        {{ __('filament-actions::edit.single.label') }}
                                    </a>
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        <div class="space-y-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $this->classesHint() }}
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
                    <x-filament::icon icon="heroicon-o-rectangle-group" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.catalog_nav.empty_title') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.catalog_nav.empty_description') }}</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($cards as $card)
                        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <span class="flex items-start justify-between gap-2">
                                <span class="min-w-0 truncate text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $card['title'] }}
                                </span>

                                @if ($card['badge'] !== null)
                                    <x-filament::badge color="primary" size="sm">
                                        {{ $card['badge'] }}
                                    </x-filament::badge>
                                @elseif ($card['missing_homeroom'])
                                    <x-filament::badge color="danger" size="sm">
                                        {{ __('panel.tables.school_classes.homeroom_only_no') }}
                                    </x-filament::badge>
                                @endif
                            </span>

                            @if ($card['subtitle'] !== null)
                                <span class="mt-0.5 block truncate text-sm text-gray-500 dark:text-gray-400">
                                    {{ $card['subtitle'] }}
                                </span>
                            @endif

                            <span class="mt-2 block text-xs text-gray-500 dark:text-gray-400">
                                {{ $card['students'] }}
                            </span>

                            <span class="mt-3 flex flex-wrap gap-2">
                                @foreach ($card['links'] as $label => $url)
                                    <a
                                        href="{{ $url }}"
                                        class="rounded-full bg-white px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-950/10 transition duration-75 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10"
                                    >
                                        {{ $label }}
                                    </a>
                                @endforeach

                                @if ($card['edit_url'] !== null)
                                    <a
                                        href="{{ $card['edit_url'] }}"
                                        class="rounded-full bg-white px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-950/10 transition duration-75 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10"
                                    >
                                        {{ __('filament-actions::edit.single.label') }}
                                    </a>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
