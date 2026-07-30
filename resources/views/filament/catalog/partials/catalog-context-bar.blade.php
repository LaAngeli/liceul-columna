{{-- Bara de context: înapoi + titlul entității + comutator de entități-surori + chips de sub-navigare. --}}
@php
    $chips = $this->catalogChips();
    $activeChipId = $this->catalogActiveChipId();
    $siblings = $this->catalogSiblingOptions();
    $primaryId = $this->catalogPrimaryModel()?->getKey();
    $capacity = $this->catalogCapacityNotice();
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

            {{-- ÎN CE CALITATE: perimetrul se derivă din desemnarea de dirigenție, PER CLASĂ —
                 aceeași persoană vede altceva la clasa unde e diriginte față de una unde doar predă. --}}
            @if ($capacity !== null)
                {{-- Pe mobil rămâne doar ETICHETA (semnalul); explicația intră de la `sm`, ca
                     subtitlul de mai sus — altfel trei rânduri de text preced tabelul la 390px. --}}
                <p class="mt-1 flex flex-wrap items-baseline gap-x-1.5 text-xs">
                    <span class="font-semibold text-primary-600 dark:text-primary-400">{{ $capacity['label'] }}</span>
                    <span class="hidden text-gray-500 dark:text-gray-400 sm:inline">{{ $capacity['detail'] }}</span>
                </p>
            @endif
        </div>

        <div class="ms-auto flex w-full flex-wrap items-center gap-2 sm:w-auto">
            {{-- PUNTEA spre borderou: din contextul Note/Absențe, aceeași clasă + disciplină se
                 deschid în Catalogul clasei — introducerea în masă e la un click, nu la o căutare. --}}
            @if (($this instanceof \App\Filament\Resources\Grades\Pages\ListGrades
                    || $this instanceof \App\Filament\Resources\Absences\Pages\ListAbsences)
                && \App\Filament\Pages\ClassRegister::canAccess()
                && $this->catalogClassIdInContext() !== null)
                <x-filament::button
                    tag="a"
                    size="sm"
                    color="gray"
                    icon="heroicon-m-table-cells"
                    :href="\App\Filament\Pages\ClassRegister::getUrl(array_filter([
                        'clasa' => $this->catalogClassIdInContext(),
                        'disciplina' => $this->catalogSubjectIdInContext(),
                    ]))"
                >
                    {{ __('panel.class_register.title') }}
                </x-filament::button>
            @endif

            @if (count($siblings) > 1)
                <div class="w-full sm:w-64">
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
