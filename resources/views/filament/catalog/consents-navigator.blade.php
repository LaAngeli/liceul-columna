{{-- Consimțăminte = acoperirea notei de informare (L133 §7): carduri pe segment (Elevi/Părinți)
     cu „X din Y au confirmat versiunea curentă" → context cu vederile Dovezi (tabelul
     confirmărilor) / De confirmat (conturile active fără versiunea curentă, cu căutare). --}}
<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $this->consentHint() }}
        </p>

        @if ($this->activeRole() !== null)
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
                        {{ __('panel.consent_nav.eyebrow') }}
                    </p>
                    <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $this->contextTitle() }}
                    </h2>
                </div>
            </div>

            <x-filament::tabs :label="__('panel.consent_nav.aria')">
                @foreach ($this->consentViewPills() as $pill)
                    <x-filament::tabs.item
                        :active="$pill['active']"
                        :badge="$pill['count']"
                        wire:click="setConsentView('{{ $pill['key'] }}')"
                    >
                        {{ $pill['label'] }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>

            @if ($this->isMissingView())
                @php($missing = $this->missingUsers())

                <div class="space-y-3">
                    <div class="max-w-md">
                        <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                            <x-filament::input
                                type="search"
                                wire:model.live.debounce.400ms="missingSearch"
                                :placeholder="__('panel.consent_nav.search_placeholder')"
                            />
                        </x-filament::input.wrapper>
                    </div>

                    @if ($missing['total'] === 0)
                        <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <x-filament::icon icon="heroicon-o-check-circle" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                            <p class="text-sm font-medium text-gray-950 dark:text-white">
                                {{ __('panel.consent_nav.missing_empty_title') }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('panel.consent_nav.missing_empty_description') }}
                            </p>
                        </div>
                    @else
                        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                                @foreach ($missing['users'] as $user)
                                    <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-medium text-gray-950 dark:text-white">
                                                {{ $user['name'] }}
                                            </span>
                                            @if ($user['username'] !== null)
                                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $user['username'] }}</span>
                                            @endif
                                        </span>
                                        <x-filament::badge :color="$user['previous'] === null ? 'danger' : 'warning'" size="sm">
                                            {{ $user['previous'] === null
                                                ? __('panel.consent_nav.never_confirmed')
                                                : __('panel.consent_nav.old_version', ['version' => $user['previous']]) }}
                                        </x-filament::badge>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        @if ($missing['total'] > count($missing['users']))
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ __('panel.consent_nav.more_missing', ['count' => $missing['total'] - count($missing['users'])]) }}
                            </p>
                        @endif
                    @endif
                </div>
            @else
                {{ $this->table }}
            @endif
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($this->roleCards() as $card)
                    <button
                        type="button"
                        wire:click="openRole('{{ $card['id'] }}')"
                        wire:loading.attr="disabled"
                        class="group rounded-xl bg-white p-4 text-start shadow-sm ring-1 ring-gray-950/5 transition duration-75 hover:ring-2 hover:ring-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 disabled:pointer-events-none disabled:opacity-70 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-500"
                    >
                        <span class="flex items-start justify-between gap-2">
                            <span class="min-w-0 truncate text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                {{ $card['title'] }}
                            </span>

                            @if ($card['badge'] !== null)
                                <x-filament::badge color="warning" size="sm">
                                    {{ $card['badge'] }}
                                </x-filament::badge>
                            @endif
                        </span>

                        {{-- Bara de acoperire: procentul segmentului care a confirmat versiunea curentă. --}}
                        <span class="mt-3 block h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                            <span class="block h-full rounded-full bg-primary-600 dark:bg-primary-500" style="width: {{ $card['percent'] }}%"></span>
                        </span>

                        <span class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
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
