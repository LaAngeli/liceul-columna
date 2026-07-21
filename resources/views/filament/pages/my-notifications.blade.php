{{-- Notificările mele (personal): inboxul complet pe două file — Recente (active) + Arhivă
     (istoricul de retenție: căutare, tip, interval, sens cronologic, grupare pe luni). FĂRĂ nicio
     ștergere: notificările citite trec automat în arhivă după perioada configurată; singurele
     acțiuni sunt „deschide" (citit + navigare, atomic) și „marchează citit(e)". --}}
<x-filament-panels::page>
    @php($counts = $this->counts())
    @php($items = $this->items())
    @php($isArchive = $this->isArchiveTab())
    @php($unread = $this->unreadCount())

    <div class="space-y-5">
        {{-- Filele + acțiunea de citire în masă. --}}
        <div class="flex flex-wrap items-center gap-2">
            <nav class="flex flex-wrap items-center gap-2" aria-label="{{ $this->getTitle() }}">
                <button
                    type="button"
                    wire:click="$set('tab', 'recente')"
                    @class([
                        'inline-flex min-h-9 items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-medium transition',
                        'bg-primary-600 text-white' => ! $isArchive,
                        'bg-white text-gray-600 ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/10' => $isArchive,
                    ])
                    @if (! $isArchive) aria-current="page" @endif
                >
                    <x-filament::icon icon="heroicon-o-bell" class="h-4 w-4" />
                    {{ __('panel.my_notifications.tab_recent') }}
                    <span class="text-xs tabular-nums opacity-80">{{ $counts['active'] }}</span>
                </button>
                <button
                    type="button"
                    wire:click="$set('tab', 'arhiva')"
                    @class([
                        'inline-flex min-h-9 items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-medium transition',
                        'bg-primary-600 text-white' => $isArchive,
                        'bg-white text-gray-600 ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/10' => ! $isArchive,
                    ])
                    @if ($isArchive) aria-current="page" @endif
                >
                    <x-filament::icon icon="heroicon-o-archive-box" class="h-4 w-4" />
                    {{ __('panel.my_notifications.tab_archive') }}
                    <span class="text-xs tabular-nums opacity-80">{{ $counts['archived'] }}</span>
                </button>
            </nav>

            @if (! $isArchive && $unread > 0)
                <x-filament::button
                    wire:click="markAllRead"
                    color="primary"
                    size="sm"
                    icon="heroicon-o-check"
                    class="ms-auto"
                >
                    {{ __('panel.my_notifications.mark_all') }}
                </x-filament::button>
            @endif
        </div>

        @if ($isArchive)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ str_replace(':zile', (string) $this->archiveDays(), __('panel.my_notifications.archive_hint')) }}
            </p>

            {{-- Bara de investigare a arhivei. --}}
            <div class="flex flex-wrap items-center gap-2">
                <input
                    type="search"
                    wire:model.live.debounce.400ms="q"
                    placeholder="{{ __('panel.my_notifications.search') }}"
                    aria-label="{{ __('panel.my_notifications.search') }}"
                    class="fi-input min-h-9 w-full rounded-lg border-none bg-white px-3 py-1.5 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 sm:w-64 dark:bg-white/5 dark:text-white dark:ring-white/20"
                />
                <select
                    wire:model.live="tip"
                    aria-label="{{ __('panel.my_notifications.filter_type') }}"
                    class="fi-select-input min-h-9 rounded-lg border-none bg-white px-3 py-1.5 pe-8 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                >
                    <option value="">{{ __('panel.my_notifications.all_types') }}</option>
                    @foreach ($this->typeOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('panel.my_notifications.from') }}
                    <input
                        type="date"
                        wire:model.live="deLa"
                        class="min-h-9 rounded-lg border-none bg-white px-3 py-1.5 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                    />
                </label>
                <label class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('panel.my_notifications.until') }}
                    <input
                        type="date"
                        wire:model.live="panaLa"
                        class="min-h-9 rounded-lg border-none bg-white px-3 py-1.5 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                    />
                </label>
                <button
                    type="button"
                    wire:click="$set('sort', '{{ $this->sort === 'vechi' ? 'recente' : 'vechi' }}')"
                    aria-pressed="{{ $this->sort === 'vechi' ? 'true' : 'false' }}"
                    class="inline-flex min-h-9 items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/20 dark:hover:bg-white/10"
                >
                    <x-filament::icon
                        :icon="$this->sort === 'vechi' ? 'heroicon-o-bars-arrow-up' : 'heroicon-o-bars-arrow-down'"
                        class="h-4 w-4"
                    />
                    {{ $this->sort === 'vechi' ? __('panel.my_notifications.sort_old') : __('panel.my_notifications.sort_new') }}
                </button>
                @if ($this->hasFilters())
                    <button
                        type="button"
                        wire:click="resetFilters"
                        class="min-h-9 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                    >
                        {{ __('panel.my_notifications.reset') }}
                    </button>
                @endif
            </div>
        @endif

        @if ($items->isEmpty())
            <div class="flex flex-col items-center gap-2 rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <x-filament::icon
                    :icon="$isArchive ? 'heroicon-o-archive-box' : 'heroicon-o-bell-slash'"
                    class="h-8 w-8 text-gray-300 dark:text-gray-600"
                />
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    @if ($isArchive)
                        {{ $this->hasFilters() ? __('panel.my_notifications.empty_filtered') : __('panel.my_notifications.empty_archive') }}
                    @else
                        {{ __('panel.my_notifications.empty_active') }}
                    @endif
                </p>
            </div>
        @else
            @php($previousMonth = null)
            <div class="space-y-2">
                @foreach ($items as $notification)
                    @php($month = $this->monthLabel($notification))
                    @if ($isArchive && $month !== null && $month !== $previousMonth)
                        <h2 class="{{ $loop->first ? '' : 'mt-5' }} text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            {{ $month }}
                        </h2>
                    @endif
                    @php($previousMonth = $month)

                    @php($data = $notification->data)
                    @php($url = is_string($data['url'] ?? null) ? $data['url'] : null)
                    @php($isUnread = $notification->read_at === null)

                    <div
                        @class([
                            'flex items-start gap-3 rounded-xl px-4 py-3 shadow-sm transition',
                            'border border-dashed border-gray-300 bg-gray-50 dark:border-white/15 dark:bg-white/5' => $isArchive,
                            'bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10' => ! $isArchive && ! $isUnread,
                            'bg-primary-50 ring-1 ring-primary-600/30 dark:bg-primary-400/10 dark:ring-primary-400/30' => ! $isArchive && $isUnread,
                        ])
                    >
                        <span
                            @class([
                                'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg',
                                'bg-gray-100 text-gray-400 dark:bg-white/10 dark:text-gray-500' => $isArchive,
                                'bg-primary-100 text-primary-700 dark:bg-primary-400/20 dark:text-primary-300' => ! $isArchive,
                            ])
                            aria-hidden="true"
                        >
                            <x-filament::icon :icon="is_string($data['icon'] ?? null) ? $data['icon'] : 'heroicon-o-bell'" class="h-4 w-4" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p @class([
                                'flex items-center gap-2 text-sm font-medium',
                                'text-gray-500 dark:text-gray-400' => $isArchive,
                                'text-gray-950 dark:text-white' => ! $isArchive,
                            ])>
                                @if ($isUnread && ! $isArchive)
                                    <span class="h-2 w-2 shrink-0 rounded-full bg-primary-600 dark:bg-primary-400" aria-hidden="true"></span>
                                @endif
                                <span class="truncate">{{ $data['title'] ?? '' }}</span>
                            </p>
                            @if (($data['body'] ?? '') !== '')
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $data['body'] }}</p>
                            @endif

                            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400 dark:text-gray-500">
                                <span class="tabular-nums">{{ $this->localTime($notification->created_at) }}</span>
                                @if ($isArchive && $notification->archived_at !== null)
                                    <span class="inline-flex items-center gap-1">
                                        <x-filament::icon icon="heroicon-o-archive-box" class="h-3 w-3" />
                                        {{ __('panel.my_notifications.archived_on') }} {{ $this->localTime($notification->archived_at, 'd.m.Y') }}
                                    </span>
                                @endif
                                @if ($url !== null)
                                    <button
                                        type="button"
                                        wire:click="open('{{ $notification->getKey() }}')"
                                        class="min-h-7 font-medium text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ __('panel.my_notifications.open') }}
                                    </button>
                                @endif
                                @if ($isUnread)
                                    <button
                                        type="button"
                                        wire:click="markRead('{{ $notification->getKey() }}')"
                                        class="min-h-7 font-medium text-gray-500 hover:underline dark:text-gray-400"
                                    >
                                        {{ __('panel.my_notifications.mark_read') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <x-filament::pagination :paginator="$items" />
        @endif
    </div>
</x-filament-panels::page>
