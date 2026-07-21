{{-- Generare rapoarte: categorii → cardurile rapoartelor → parametri + generare. Utilizatorul
     vede DOAR categoriile/rapoartele rolului lui; PDF-urile ies instituționale (antet, subsol,
     numerotare), gata de tipărit sau arhivat. --}}
<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('panel.pages.reports.hint') }}
        </p>

        @if ($this->activeCategory() !== null)
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::icon-button
                    icon="heroicon-o-arrow-uturn-left"
                    color="gray"
                    wire:click="leaveCategory"
                    :label="__('panel.catalog_nav.back')"
                    :tooltip="__('panel.catalog_nav.back')"
                />

                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ __('panel.pages.reports.title') }}
                    </p>
                    <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $this->activeCategory()?->label() }}
                    </h2>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->reportCards() as $card)
                    <button
                        type="button"
                        wire:click="openReport('{{ $card['id'] }}')"
                        wire:loading.attr="disabled"
                        @class([
                            'group rounded-xl bg-white p-4 text-start shadow-sm ring-1 transition duration-75 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 disabled:pointer-events-none disabled:opacity-70 dark:bg-gray-900',
                            'ring-2 ring-primary-600 dark:ring-primary-500' => $card['active'],
                            'ring-gray-950/5 hover:ring-2 hover:ring-primary-600 dark:ring-white/10 dark:hover:ring-primary-500' => ! $card['active'],
                        ])
                    >
                        <span class="flex items-start justify-between gap-2">
                            <span class="flex min-w-0 items-center gap-2">
                                <x-filament::icon :icon="$card['icon']" class="h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400" />
                                <span class="min-w-0 truncate text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                    {{ $card['title'] }}
                                </span>
                            </span>
                            <x-filament::badge color="gray" size="sm">
                                {{ $card['format'] }}
                            </x-filament::badge>
                        </span>

                        <span class="mt-1.5 block text-sm text-gray-500 dark:text-gray-400">
                            {{ $card['description'] }}
                        </span>
                    </button>
                @endforeach
            </div>

            @if ($this->activeReport() !== null)
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="mb-1 text-base font-semibold text-gray-950 dark:text-white">
                        {{ $this->activeReport()?->getLabel() }}
                    </h3>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        {{ $this->activeReport()?->description() }}
                    </p>

                    <form wire:submit="generate" class="space-y-4">
                        @if ($this->reportNeedsParameters())
                            {{ $this->form }}
                        @endif

                        <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray">
                            {{ __('panel.pages.reports.generate') }}
                        </x-filament::button>
                    </form>
                </div>
            @endif
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->categoryCards() as $card)
                    <button
                        type="button"
                        wire:click="openCategory('{{ $card['id'] }}')"
                        wire:loading.attr="disabled"
                        class="group min-w-0 rounded-xl bg-white p-4 text-start shadow-sm ring-1 ring-gray-950/5 transition duration-75 hover:ring-2 hover:ring-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 disabled:pointer-events-none disabled:opacity-70 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-500"
                    >
                        <span class="flex items-start justify-between gap-2">
                            <span class="flex min-w-0 items-center gap-2">
                                <x-filament::icon :icon="$card['icon']" class="h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400" />
                                <span class="min-w-0 truncate text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                    {{ $card['title'] }}
                                </span>
                            </span>
                            <x-filament::badge color="gray" size="sm">
                                {{ trans_choice('panel.reports_nav.reports_count', $card['count'], ['count' => $card['count']]) }}
                            </x-filament::badge>
                        </span>

                        <span class="mt-1.5 block text-sm text-gray-500 dark:text-gray-400">
                            {{ $card['description'] }}
                        </span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
