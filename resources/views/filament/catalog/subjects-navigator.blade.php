{{-- Discipline: administrația = tabelul de nomenclator; profesorul/dirigintele = navigator cu
     carduri (disciplinele MELE → clasele în care EU le predau, cu sărituri în Note/Absențe/Teme). --}}
<x-filament-panels::page>
    @if (! $this->isTeacherView())
        {{ $this->table }}
    @else
        @php($subject = $this->activeSubject())

        @if ($subject === null)
            <div class="space-y-6">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('panel.catalog_nav.subjects_hint') }}
                </p>

                @php($cards = $this->subjectCards())

                @if (count($cards) === 0)
                    <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <x-filament::icon icon="heroicon-o-book-open" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                        <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.catalog_nav.empty_title') }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.catalog_nav.empty_description') }}</p>
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        @foreach ($cards as $card)
                            <button
                                type="button"
                                wire:click="openSubject({{ $card['id'] }})"
                                wire:loading.attr="disabled"
                                class="group rounded-xl bg-white p-4 text-start shadow-sm ring-1 ring-gray-950/5 transition duration-75 hover:ring-2 hover:ring-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 disabled:pointer-events-none disabled:opacity-70 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-500"
                            >
                                <span class="block truncate text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                    {{ $card['title'] }}
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
        @else
            {{-- Contextul disciplinei: clasele MELE pentru ea. --}}
            <div class="space-y-6">
                <div class="flex flex-wrap items-center gap-3">
                    <x-filament::icon-button
                        icon="heroicon-o-arrow-uturn-left"
                        color="gray"
                        wire:click="leaveSubject"
                        :label="__('panel.catalog_nav.back')"
                        :tooltip="__('panel.catalog_nav.back')"
                    />

                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            {{ __('panel.resources.subjects.single') }}
                        </p>
                        <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                            {{ \App\Support\ContentTranslator::subject($subject->name) }}
                        </h2>
                    </div>
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('panel.catalog_nav.subject_classes_hint') }}
                </p>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($this->classCards() as $card)
                        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <span class="block truncate text-base font-semibold text-gray-950 dark:text-white">
                                {{ $card['title'] }}
                            </span>

                            @if ($card['subtitle'] !== null)
                                <span class="mt-0.5 block truncate text-sm text-gray-500 dark:text-gray-400">
                                    {{ $card['subtitle'] }}
                                </span>
                            @endif

                            <span class="mt-2 block text-xs text-gray-500 dark:text-gray-400">
                                {{ $card['students'] }}
                            </span>

                            <span class="mt-3 flex flex-wrap gap-2">
                                @foreach ($card['links'] as $label => $url)
                                    <a
                                        href="{{ $url }}"
                                        class="rounded-full bg-white px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-950/10 transition duration-75 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10"
                                    >
                                        {{ $label }}
                                    </a>
                                @endforeach
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</x-filament-panels::page>
