{{-- Cozile de aprobare (Corecții note/teme, Motivări): vederi pe starea cererii („De procesat" /
     „Arhivă") → carduri pe entitate (solicitant / clasă) → tabelul în context, cu acțiunile
     existente. Solicitantul fără drept de procesare primește tabelul plat cu cererile proprii. --}}
<x-filament-panels::page>
    @if (! $this->isQueueManagerView())
        {{ $this->table }}
    @else
        <div class="space-y-6">
            <x-filament::tabs :label="__('panel.approval_nav.aria')">
                @foreach ($this->approvalViewPills() as $pill)
                    <x-filament::tabs.item
                        :active="$pill['active']"
                        :badge="$pill['count']"
                        wire:click="setApprovalView('{{ $pill['key'] }}')"
                    >
                        {{ $pill['label'] }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $this->approvalHint() }}
            </p>

            @php($target = $this->activeTargetId())

            @if ($target !== null)
                <div class="flex flex-wrap items-center gap-3">
                    @unless ($this->isFallbackTarget())
                        <x-filament::icon-button
                            icon="heroicon-o-arrow-uturn-left"
                            color="gray"
                            wire:click="leaveTarget"
                            :label="__('panel.catalog_nav.back')"
                            :tooltip="__('panel.catalog_nav.back')"
                        />
                    @endunless

                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            {{ $this->approvalContextEyebrow() }}
                        </p>
                        <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                            {{ $this->approvalContextTitle() }}
                            @if ($this->approvalContextSubtitle() !== null)
                                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">· {{ $this->approvalContextSubtitle() }}</span>
                            @endif
                        </h2>
                    </div>
                </div>

                {{ $this->table }}
            @else
                @php($cards = $this->approvalCards())

                @if (count($cards) === 0)
                    <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                        <p class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ $this->isArchiveView() ? __('panel.catalog_nav.empty_title') : __('panel.approval_nav.queue_empty_title') }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $this->isArchiveView() ? __('panel.catalog_nav.empty_description') : __('panel.approval_nav.queue_empty_description') }}
                        </p>
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        @foreach ($cards as $card)
                            <button
                                type="button"
                                wire:click="openTarget({{ $card['id'] }})"
                                wire:loading.attr="disabled"
                                class="group rounded-xl bg-white p-4 text-start shadow-sm ring-1 ring-gray-950/5 transition duration-75 hover:ring-2 hover:ring-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 disabled:pointer-events-none disabled:opacity-70 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-500"
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

                                @if ($card['subtitle'] !== null)
                                    <span class="mt-0.5 block truncate text-sm text-gray-500 dark:text-gray-400">
                                        {{ $card['subtitle'] }}
                                    </span>
                                @endif

                                <span class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                    @foreach ($card['stats'] as $stat)
                                        <span>{{ $stat }}</span>
                                    @endforeach
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    @endif
</x-filament-panels::page>
