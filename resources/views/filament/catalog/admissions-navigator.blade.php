{{-- Coada de admitere: vederi pe starea cererii („De procesat" / „Arhivă") → carduri pe TIPUL
     cererii (vizită / înmatriculare) → tabelul în context. Cererile se nasc pe site-ul public;
     panoul doar le procesează, cu urmă (cine, când, cu ce notă). --}}
<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::tabs :label="__('panel.admission_nav.aria')">
            @foreach ($this->admissionViewPills() as $pill)
                <x-filament::tabs.item
                    :active="$pill['active']"
                    :badge="$pill['count']"
                    wire:click="setAdmissionView('{{ $pill['key'] }}')"
                >
                    {{ $pill['label'] }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $this->admissionHint() }}
        </p>

        @if ($this->activeType() !== null)
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::icon-button
                    icon="heroicon-o-arrow-uturn-left"
                    color="gray"
                    wire:click="leaveType"
                    :label="__('panel.catalog_nav.back')"
                    :tooltip="__('panel.catalog_nav.back')"
                />

                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ $this->contextEyebrow() }}
                    </p>
                    <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $this->contextTitle() }}
                        @if ($this->contextSubtitle() !== null)
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">· {{ $this->contextSubtitle() }}</span>
                        @endif
                    </h2>
                </div>
            </div>

            {{ $this->table }}
        @elseif ($this->queueIsEmpty())
            <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                <p class="text-sm font-medium text-gray-950 dark:text-white">
                    {{ __('panel.admission_nav.queue_empty_title') }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('panel.admission_nav.queue_empty_description') }}
                </p>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($this->typeCards() as $card)
                    <button
                        type="button"
                        wire:click="openType('{{ $card['id'] }}')"
                        wire:loading.attr="disabled"
                        class="group min-w-0 rounded-xl bg-white p-4 text-start shadow-sm ring-1 ring-gray-950/5 transition duration-75 hover:ring-2 hover:ring-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 disabled:pointer-events-none disabled:opacity-70 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-500"
                    >
                        <span class="flex items-start justify-between gap-2">
                            <span class="min-w-0 truncate text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                {{ $card['title'] }}
                            </span>

                            @if ($card['badge'] !== null)
                                <x-filament::badge color="danger" size="sm">
                                    {{ $card['badge'] }}
                                </x-filament::badge>
                            @endif
                        </span>

                        <span class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            @foreach ($card['stats'] as $stat)
                                <span>{{ $stat }}</span>
                            @endforeach
                        </span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
