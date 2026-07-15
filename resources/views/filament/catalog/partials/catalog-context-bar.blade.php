{{-- Bara de context: înapoi + titlul entității + comutator de entități-surori + chips de sub-navigare. --}}
@php
    $chips = $this->catalogChips();
    $activeChipId = $this->catalogActiveChipId();
    $siblings = $this->catalogSiblingOptions();
    $primaryId = $this->catalogPrimaryModel()?->getKey();
@endphp

<div class="mb-2 space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <x-filament::icon-button
            icon="heroicon-o-arrow-uturn-left"
            color="gray"
            wire:click="leaveCatalogContext"
            :label="__('panel.catalog_nav.back')"
            :tooltip="__('panel.catalog_nav.back')"
        />

        <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                {{ $this->catalogDimensions()[$this->catalogActiveDimension()] ?? '' }}
            </p>

            <div class="flex items-center gap-2">
                <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                    {{ $this->catalogContextTitle() }}
                </h2>

                @if ($this->catalogContextSubtitle() !== null)
                    <span class="hidden truncate text-sm text-gray-500 dark:text-gray-400 sm:inline">
                        · {{ $this->catalogContextSubtitle() }}
                    </span>
                @endif
            </div>
        </div>

        @if (count($siblings) > 1)
            <div class="ms-auto w-full sm:w-64">
                <label class="sr-only" for="catalog-sibling-switch">{{ __('panel.catalog_nav.switch') }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select
                        id="catalog-sibling-switch"
                        wire:change="openCatalogEntity($event.target.value)"
                    >
                        @foreach ($siblings as $siblingId => $siblingLabel)
                            <option value="{{ $siblingId }}" @selected((string) $siblingId === (string) $primaryId)>
                                {{ $siblingLabel }}
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        @endif
    </div>

    @if (count($chips) > 1)
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                {{ $this->catalogChipsLabel() }}
            </span>

            <button
                type="button"
                wire:click="setCatalogChip(null)"
                @class([
                    'rounded-full px-3 py-1 text-sm font-medium ring-1 transition duration-75 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600',
                    'bg-primary-600 text-white ring-primary-600' => $activeChipId === null,
                    'bg-white text-gray-700 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10' => $activeChipId !== null,
                ])
            >
                {{ __('panel.catalog_nav.all') }}
            </button>

            @foreach ($chips as $chip)
                <button
                    type="button"
                    wire:click="setCatalogChip({{ $chip['id'] }})"
                    @class([
                        'rounded-full px-3 py-1 text-sm font-medium ring-1 transition duration-75 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600',
                        'bg-primary-600 text-white ring-primary-600' => $activeChipId === $chip['id'],
                        'bg-white text-gray-700 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10' => $activeChipId !== $chip['id'],
                    ])
                >
                    {{ $chip['label'] }}
                </button>
            @endforeach
        </div>
    @endif
</div>
