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

    {{-- CĂUTAREA din meniu (opt-in): o pagină cu zeci de carduri nu se parcurge cu ochiul, iar
         rezultatele directe (ex. elevul găsit după nume) sar peste alegerea entității. --}}
    @php($searchPlaceholder = $this->catalogSearchPlaceholder())
    @php($hits = $searchPlaceholder === null ? [] : $this->catalogSearchHits())

    @if ($searchPlaceholder !== null)
        <div class="max-w-md">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.400ms="catalogSearch"
                    :placeholder="$searchPlaceholder"
                />
            </x-filament::input.wrapper>
        </div>

        @if (count($hits) > 0)
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                    {{ $this->catalogSearchHitsLabel() }}
                </h3>

                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <ul class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($hits as $hit)
                            <li>
                                <a
                                    href="{{ $hit['url'] }}"
                                    class="flex items-center justify-between gap-3 px-4 py-3 transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5"
                                >
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-gray-950 dark:text-white">{{ $hit['title'] }}</span>
                                        @if ($hit['meta'] !== null)
                                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $hit['meta'] }}</span>
                                        @endif
                                    </span>
                                    <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-400" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    @endif

    @php($cards = $this->catalogEntityCards())
    @php($groups = $this->catalogCardGroups())

    @if (count($cards) === 0 && count($hits) === 0)
        <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <x-filament::icon icon="heroicon-o-square-3-stack-3d" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
            <p class="text-sm font-medium text-gray-950 dark:text-white">
                {{ $this->catalogSearchTerm() !== '' ? __('panel.catalog_nav.search_empty') : __('panel.catalog_nav.empty_title') }}
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.catalog_nav.empty_description') }}</p>
        </div>
    @elseif ($groups !== null)
        {{-- Grupat (ex. pe cicluri): 52 de carduri într-o singură grilă nu se citesc. --}}
        @foreach ($groups as $group)
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $group['label'] }}</h3>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($group['cards'] as $card)
                        @include('filament.catalog.partials.catalog-entity-card', ['card' => $card])
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($cards as $card)
                @include('filament.catalog.partials.catalog-entity-card', ['card' => $card])
            @endforeach
        </div>
    @endif
</div>
