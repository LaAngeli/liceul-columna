{{-- Fluxul anunțurilor: pastile Publicate/Ciorne → carduri cronologice. Cardul publicat poartă
     bara de progres a citirii (X din Y · Z%); ciorna poartă marcaj + scurtătură de editare.
     Clic pe card → fișa anunțului (acolo stau conținutul integral, pâlnia și „Publică"). --}}
<x-filament-panels::page>
    <div class="space-y-6">
        @php($counts = $this->counts())
        @php($cards = $this->cards())
        @php($drafts = $this->showDrafts())

        <x-filament::tabs :label="__('panel.announcements.states')">
            <x-filament::tabs.item
                :active="! $drafts"
                icon="heroicon-o-megaphone"
                :badge="$counts['published'] > 0 ? $counts['published'] : null"
                wire:click="openPublished"
            >
                {{ __('panel.announcements.published') }}
            </x-filament::tabs.item>
            <x-filament::tabs.item
                :active="$drafts"
                icon="heroicon-o-pencil-square"
                :badge="$counts['drafts'] > 0 ? $counts['drafts'] : null"
                badge-color="warning"
                wire:click="openDrafts"
            >
                {{ __('panel.announcements.drafts') }}
            </x-filament::tabs.item>
        </x-filament::tabs>

        @if ($cards === [])
            <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <x-filament::icon icon="heroicon-o-megaphone" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                <p class="text-sm font-medium text-gray-950 dark:text-white">
                    {{ $drafts ? __('panel.announcements.empty_drafts_title') : __('panel.announcements.empty_published_title') }}
                </p>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                    {{ $drafts ? __('panel.announcements.empty_drafts_description') : __('panel.announcements.empty_published_description') }}
                </p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($cards as $card)
                    <div class="relative rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition duration-75 hover:ring-2 hover:ring-primary-600 focus-within:ring-2 focus-within:ring-primary-600 sm:p-5 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-500">
                        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                            <h3 class="min-w-0 text-base font-semibold text-gray-950 dark:text-white">
                                {{-- Link întins pe tot cardul (::after inset) — DOM valid, fără <a> imbricate. --}}
                                <a
                                    href="{{ $card['view_url'] }}"
                                    class="after:absolute after:inset-0 after:rounded-xl focus-visible:outline-none"
                                >{{ $card['title'] }}</a>
                            </h3>

                            <span class="flex shrink-0 items-center gap-2">
                                @if (! $card['published'])
                                    <x-filament::badge color="warning" size="sm">
                                        {{ __('panel.forms.announcement.draft') }}
                                    </x-filament::badge>
                                @elseif ($card['delivering'])
                                    <x-filament::badge color="info" size="sm">
                                        {{ __('panel.announcements.delivering_badge') }}
                                    </x-filament::badge>
                                @endif
                            </span>
                        </div>

                        <p class="mt-1 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ $card['preview'] }}
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400 dark:text-gray-500">
                            <span class="tabular-nums">{{ $card['date'] }}</span>
                            @if ($card['author'] !== null)
                                <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">·</span>
                                <span>{{ __('panel.announcements.by_author', ['name' => $card['author']]) }}</span>
                            @endif

                            @if ($card['edit_url'] !== null)
                                <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">·</span>
                                {{-- z-10: deasupra linkului întins al cardului. --}}
                                <a
                                    href="{{ $card['edit_url'] }}"
                                    class="relative z-10 inline-flex min-h-6 items-center gap-1 font-medium text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    <x-filament::icon icon="heroicon-o-pencil-square" class="h-3.5 w-3.5" />
                                    {{ __('panel.announcements.edit') }}
                                </a>
                            @endif
                        </div>

                        @if ($card['published'])
                            <div class="mt-3">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 text-xs">
                                    <span class="text-gray-500 dark:text-gray-400">
                                        {{ __('panel.announcements.read_progress', ['read' => $card['read'], 'total' => $card['recipients']]) }}
                                    </span>
                                    @if ($card['percent'] !== null)
                                        <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $card['percent'] }}%</span>
                                    @endif
                                </div>
                                <div
                                    class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10"
                                    role="progressbar"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="{{ $card['percent'] ?? 0 }}"
                                    aria-label="{{ __('panel.announcements.read_progress', ['read' => $card['read'], 'total' => $card['recipients']]) }}"
                                >
                                    <div
                                        class="h-full rounded-full bg-primary-600 transition-all dark:bg-primary-500"
                                        style="width: {{ min(100, $card['percent'] ?? 0) }}%"
                                    ></div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
