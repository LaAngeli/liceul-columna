{{-- Coșul de restaurare: carduri pe tip → lista tipului deschis. Fiecare rând spune ce e, cine
     l-a șters și când, apoi VERDICTUL: ce blochează restaurarea (roșu, butonul stins) și ce nu
     revine odată cu ea (chihlimbar). Ștergerea definitivă cere confirmare în linie și o vede
     doar super-adminul. --}}
<x-filament-panels::page>
    @php($activeType = $this->activeType())

    @if ($activeType === null)
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->typeCards() as $card)
                <button
                    type="button"
                    wire:click="openType('{{ $card['key'] }}')"
                    @class([
                        'group rounded-xl bg-white p-4 text-start shadow-sm ring-1 ring-gray-950/5 transition duration-75 dark:bg-gray-900 dark:ring-white/10',
                        'hover:ring-2 hover:ring-primary-600 dark:hover:ring-primary-500' => $card['count'] > 0,
                        'opacity-60' => $card['count'] === 0,
                    ])
                    @disabled($card['count'] === 0)
                >
                    <span class="flex items-start justify-between gap-2">
                        <span class="flex min-w-0 items-center gap-2">
                            <x-filament::icon :icon="$card['icon']" class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500" />
                            <span class="min-w-0 truncate text-base font-semibold text-gray-950 dark:text-white">
                                {{ $card['label'] }}
                            </span>
                        </span>

                        <x-filament::badge :color="$card['count'] > 0 ? 'warning' : 'gray'" class="shrink-0">
                            {{ $card['count'] }}
                        </x-filament::badge>
                    </span>

                    <span class="mt-2 block text-sm text-gray-500 dark:text-gray-400">
                        {{ $card['description'] }}
                    </span>

                    @if ($card['last'] !== null)
                        <span class="mt-2 block text-xs text-gray-400 dark:text-gray-500">
                            {{ __('panel.restore.last_deleted', ['moment' => $card['last']]) }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>

        @if (array_sum(array_column($this->typeCards(), 'count')) === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('panel.restore.empty_all') }}
            </p>
        @endif
    @else
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button color="gray" size="sm" icon="heroicon-o-arrow-left" wire:click="leaveType">
                    {{ __('panel.restore.back') }}
                </x-filament::button>

                <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ $activeType->label() }}
                </h2>
            </div>

            @php($rows = $this->records())

            @if ($rows === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('panel.restore.empty_type') }}
                </p>
            @else
                <div class="space-y-3">
                    @foreach ($rows as $row)
                        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $row['title'] }}
                                    </p>

                                    @if ($row['subtitle'] !== null && $row['subtitle'] !== '')
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row['subtitle'] }}
                                        </p>
                                    @endif

                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                        {{ $row['deleted_by'] !== null
                                            ? __('panel.restore.deleted_by', ['name' => $row['deleted_by'], 'moment' => $row['deleted_at']])
                                            : __('panel.restore.deleted_at', ['moment' => $row['deleted_at']]) }}
                                    </p>
                                </div>

                                <div class="flex shrink-0 flex-wrap items-center gap-2">
                                    <x-filament::button
                                        size="sm"
                                        icon="heroicon-o-arrow-uturn-left"
                                        wire:click="restore({{ $row['id'] }})"
                                        :disabled="$row['blocking'] !== []"
                                    >
                                        {{ __('panel.restore.restore') }}
                                    </x-filament::button>

                                    @if ($this->canPurge())
                                        <x-filament::button
                                            size="sm"
                                            color="danger"
                                            icon="heroicon-o-trash"
                                            wire:click="askPurge({{ $row['id'] }})"
                                        >
                                            {{ __('panel.restore.purge') }}
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>

                            @if ($row['blocking'] !== [])
                                <ul class="mt-3 space-y-1">
                                    @foreach ($row['blocking'] as $reason)
                                        <li class="flex items-start gap-1.5 text-xs text-danger-600 dark:text-danger-400">
                                            <x-filament::icon icon="heroicon-o-no-symbol" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                            {{ $reason }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($row['warnings'] !== [])
                                <ul class="mt-2 space-y-1">
                                    @foreach ($row['warnings'] as $warning)
                                        <li class="flex items-start gap-1.5 text-xs text-warning-600 dark:text-warning-400">
                                            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                            {{ $warning }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($this->confirmingPurge === $row['id'])
                                <div class="mt-3 rounded-lg bg-danger-50 p-3 ring-1 ring-danger-600/20 dark:bg-danger-400/10 dark:ring-danger-400/30">
                                    <p class="text-xs text-danger-700 dark:text-danger-300">
                                        {{ __('panel.restore.purge_warning') }}
                                    </p>

                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <x-filament::button size="sm" color="danger" wire:click="purge({{ $row['id'] }})">
                                            {{ __('panel.restore.purge_confirm') }}
                                        </x-filament::button>

                                        <x-filament::button size="sm" color="gray" wire:click="cancelPurge">
                                            {{ __('panel.restore.cancel') }}
                                        </x-filament::button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
