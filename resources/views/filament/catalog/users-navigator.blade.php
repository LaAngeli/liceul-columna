{{-- Utilizatori: navigator pe ROLURI — carduri cu descrierea rolului + numărul de conturi și
     semnalele de atenție (suspendate / parole temporare) → lista contextului. --}}
<x-filament-panels::page>
    @php($role = $this->activeRole())

    @if ($role !== null)
        {{-- ── Conturile rolului activ ────────────────────────────────────────────────── --}}
        <div class="space-y-6">
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::icon-button
                    icon="heroicon-o-arrow-uturn-left"
                    color="gray"
                    wire:click="leaveRole"
                    :label="__('panel.catalog_nav.back')"
                    :tooltip="__('panel.catalog_nav.back')"
                />

                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ __('panel.forms.user.role') }}
                    </p>
                    <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $this->activeRoleLabel() }}
                    </h2>
                </div>
            </div>

            {{ $this->table }}
        </div>
    @else
        {{-- ── Aterizare: cardurile rolurilor ─────────────────────────────────────────── --}}
        <div class="space-y-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $this->usersHint() }}
            </p>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->roleCards() as $card)
                    <button
                        type="button"
                        wire:click="openRole('{{ $card['id'] }}')"
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

                        <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">
                            {{ $card['subtitle'] }}
                        </span>

                        <span class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            @foreach ($card['stats'] as $stat)
                                <span>{{ $stat }}</span>
                            @endforeach
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>
