{{-- Hub-ul de configurare: categorii logice, fiecare cu secțiunile ei. Cardurile sunt linkuri
     REALE (middle-click / tab nou funcționează), iar starea („N de configurat", „Doar citire")
     se derivă din policies, nu dintr-o matrice scrisă aici. --}}
<x-filament-panels::page>
    <div class="space-y-8">
        @if ($this->currentYearLabel() !== null)
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-filament::icon icon="heroicon-o-calendar-days" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                <span class="text-gray-500 dark:text-gray-400">{{ __('panel.config_hub.current_year') }}</span>
                <x-filament::badge color="primary">{{ $this->currentYearLabel() }}</x-filament::badge>
            </div>
        @endif

        @foreach ($this->categories() as $category)
            <section class="space-y-3">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                        <x-filament::icon :icon="$category['icon']" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                            {{ $category['label'] }}
                        </h2>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            {{ $category['description'] }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($category['sections'] as $section)
                        <a
                            href="{{ $section['url'] }}"
                            class="group rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition duration-75 hover:ring-2 hover:ring-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-500"
                        >
                            <span class="flex items-start justify-between gap-2">
                                <span class="min-w-0 truncate text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                    {{ $section['title'] }}
                                </span>

                                @if ($section['badge'] !== null)
                                    <x-filament::badge :color="$section['badge']['color']" class="shrink-0">
                                        {{ $section['badge']['label'] }}
                                    </x-filament::badge>
                                @endif
                            </span>

                            @if ($section['count'] !== null)
                                <span class="mt-2 block text-sm text-gray-500 dark:text-gray-400">
                                    {{ trans_choice('panel.config_hub.records', $section['count'], ['count' => $section['count']]) }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
