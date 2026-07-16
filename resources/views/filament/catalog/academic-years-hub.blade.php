{{-- Ani școlari: HUB-ul configurării — un card per an, cu badge „An curent", conținutul lui și
     sărituri pre-filtrate (Semestre / Clase / Înmatriculări) + Editare și Arhivează în matricolă. --}}
<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $this->configHint() }}
        </p>

        @php($cards = $this->yearCards())

        @if (count($cards) === 0)
            <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <x-filament::icon icon="heroicon-o-calendar" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.catalog_nav.empty_title') }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.catalog_nav.empty_description') }}</p>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($cards as $card)
                    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <span class="flex items-start justify-between gap-2">
                            <span class="min-w-0 truncate text-base font-semibold text-gray-950 dark:text-white">
                                {{ $card['title'] }}
                            </span>

                            @if ($card['current'])
                                <x-filament::badge color="primary" size="sm">
                                    {{ __('panel.config_nav.current_year') }}
                                </x-filament::badge>
                            @endif
                        </span>

                        @if ($card['period'] !== null)
                            <span class="mt-0.5 block truncate text-sm text-gray-500 dark:text-gray-400">
                                {{ $card['period'] }}
                            </span>
                        @endif

                        <span class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            @foreach ($card['stats'] as $stat)
                                <span>{{ $stat }}</span>
                            @endforeach
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

                            @if ($card['can_archive'])
                                <button
                                    type="button"
                                    wire:click="mountAction('archiveYear', { year: {{ $card['id'] }} })"
                                    class="rounded-full bg-white px-3 py-1 text-sm font-medium text-warning-700 ring-1 ring-warning-600/30 transition duration-75 hover:bg-warning-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-warning-600 dark:bg-white/5 dark:text-warning-400 dark:ring-warning-400/30 dark:hover:bg-white/10"
                                >
                                    {{ __('panel.actions.archive_year.label') }}
                                </button>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
