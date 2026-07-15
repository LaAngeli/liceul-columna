{{-- Meniul navigatorului: dimensiuni (tab-uri) + carduri de entitate cu statistici. --}}
<div class="space-y-6">
    <div class="space-y-2">
        @php($dimensions = $this->catalogDimensions())

        {{-- Cu o singură dimensiune, meniul de tab-uri nu are ce comuta — rămâne doar ghidul. --}}
        @if (count($dimensions) > 1)
            <x-filament::tabs :label="__('panel.catalog_nav.aria')">
                @foreach ($dimensions as $dimensionKey => $dimensionLabel)
                    <x-filament::tabs.item
                        :active="$this->catalogActiveDimension() === $dimensionKey"
                        wire:click="setCatalogDimension('{{ $dimensionKey }}')"
                    >
                        {{ $dimensionLabel }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
        @endif

        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $this->catalogHint() }}
        </p>
    </div>

    @php($cards = $this->catalogEntityCards())

    @if (count($cards) === 0)
        <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <x-filament::icon icon="heroicon-o-square-3-stack-3d" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.catalog_nav.empty_title') }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.catalog_nav.empty_description') }}</p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($cards as $card)
                <button
                    type="button"
                    wire:click="openCatalogEntity({{ $card['id'] }})"
                    wire:loading.attr="disabled"
                    class="group rounded-xl bg-white p-4 text-start shadow-sm ring-1 ring-gray-950/5 transition duration-75 hover:ring-2 hover:ring-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 disabled:pointer-events-none disabled:opacity-70 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-500"
                >
                    <span class="flex items-start justify-between gap-2">
                        <span class="min-w-0 truncate text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                            {{ $card['title'] }}
                        </span>

                        @if ($card['badge'] !== null)
                            <x-filament::badge color="primary" size="sm">
                                {{ $card['badge'] }}
                            </x-filament::badge>
                        @endif
                    </span>

                    @if ($card['subtitle'] !== null)
                        <span class="mt-0.5 block truncate text-sm text-gray-500 dark:text-gray-400">
                            {{ $card['subtitle'] }}
                        </span>
                    @endif

                    @if (count($card['stats']) > 0)
                        <span class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            @foreach ($card['stats'] as $stat)
                                <span>{{ $stat }}</span>
                            @endforeach
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif
</div>
