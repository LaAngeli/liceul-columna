{{-- Registrul corpului didactic: vederi-segmente + căutare → CARDURI de profesor → fișa
     profesorului în context (identitate, alocări cu clase-chips, punți, editare).
     Stare în URL: ?vedere= & ?profesor= & ?q=. --}}
<x-filament-panels::page>
    @php($profile = $this->teacherProfile())

    @if ($profile === null)
        <div class="space-y-6">
            <x-filament::tabs :label="__('panel.teachers_registry.function')">
                @foreach ($this->viewPills() as $pill)
                    <x-filament::tabs.item
                        :active="$this->activeView() === $pill['key']"
                        :badge="$pill['count']"
                        :badge-color="$pill['attention'] ? 'warning' : 'gray'"
                        wire:click="openView('{{ $pill['key'] }}')"
                    >
                        {{ $pill['label'] }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>

            <div class="flex flex-wrap items-center gap-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->registryHint() }}
                </p>

                <div class="ms-auto w-full sm:w-72">
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input
                            type="search"
                            wire:model.live.debounce.400ms="search"
                            :placeholder="__('panel.catalog_nav.search_students')"
                        />
                    </x-filament::input.wrapper>
                </div>
            </div>

            @php($cards = $this->teacherCards())

            @if (count($cards) === 0)
                <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-user-group" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.catalog_nav.empty_title') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.teachers_registry.empty_description') }}</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($cards as $card)
                        <button
                            type="button"
                            wire:click="openTeacher({{ $card['id'] }})"
                            wire:loading.attr="disabled"
                            class="group min-w-0 rounded-xl bg-white p-4 text-start shadow-sm ring-1 ring-gray-950/5 transition duration-75 hover:ring-2 hover:ring-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 disabled:pointer-events-none disabled:opacity-70 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-500"
                        >
                            <span class="flex items-start justify-between gap-2">
                                <span class="block min-w-0 truncate text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                    {{ $card['name'] }}
                                </span>

                                @if ($card['archived'])
                                    <x-filament::badge color="gray">{{ __('panel.teachers_registry.views.arhiva') }}</x-filament::badge>
                                @elseif ($card['homeroom'] !== null)
                                    <x-filament::badge color="primary">{{ $card['homeroom'] }}</x-filament::badge>
                                @endif
                            </span>

                            <span class="mt-1 block text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                {{ $card['homeroom'] !== null ? __('panel.teachers_registry.function_homeroom') : __('panel.teachers_registry.function_teacher') }}
                            </span>

                            <span class="mt-3 block truncate text-sm text-gray-600 dark:text-gray-300">
                                {{ $card['subjects'] ?? __('panel.teachers_registry.no_assignments') }}
                            </span>

                            <span class="mt-2 block text-xs {{ $card['account'] === null ? 'font-medium text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">
                                {{ $card['account'] ?? __('panel.teachers_registry.no_account') }}
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        {{-- FIȘA profesorului: toate informațiile + acțiunile lui, fără a părăsi secțiunea. --}}
        <div class="space-y-6">
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::icon-button
                    icon="heroicon-o-arrow-uturn-left"
                    color="gray"
                    wire:click="leaveTeacher"
                    :label="__('panel.catalog_nav.back')"
                    :tooltip="__('panel.catalog_nav.back')"
                />

                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ __('panel.resources.teachers.single') }}
                    </p>
                    <h2 class="flex flex-wrap items-center gap-2 text-lg font-semibold text-gray-950 dark:text-white">
                        <span class="truncate">{{ $profile['name'] }}</span>

                        @if ($profile['archived'])
                            <x-filament::badge color="gray">{{ __('panel.teachers_registry.views.arhiva') }}</x-filament::badge>
                        @elseif ($profile['homeroom'] !== null)
                            <x-filament::badge color="primary">{{ __('panel.teachers_registry.homeroom_of_value', ['class' => $profile['homeroom']]) }}</x-filament::badge>
                        @else
                            <x-filament::badge color="gray">{{ __('panel.teachers_registry.function_teacher') }}</x-filament::badge>
                        @endif
                    </h2>
                </div>

                @if ($profile['editUrl'] !== null)
                    <x-filament::button
                        tag="a"
                        :href="$profile['editUrl']"
                        color="gray"
                        icon="heroicon-o-pencil-square"
                        class="ms-auto"
                    >
                        {{ __('panel.teachers_registry.edit_profile') }}
                    </x-filament::button>
                @endif
            </div>

            {{-- Identitate + cont + punți în catalog. --}}
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ __('panel.teachers_registry.identity') }}
                    </p>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('panel.fields.email') }}</dt>
                            <dd class="truncate text-gray-950 dark:text-white">{{ $profile['email'] ?? __('panel.common.dash') }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('panel.fields.sex') }}</dt>
                            <dd class="text-gray-950 dark:text-white">{{ $profile['sex'] ?? __('panel.common.dash') }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ __('panel.forms.student.account_short') }}
                    </p>
                    <p class="mt-3 text-sm {{ $profile['account'] === null ? 'font-medium text-amber-600 dark:text-amber-400' : 'text-gray-950 dark:text-white' }}">
                        {{ $profile['account'] ?? __('panel.teachers_registry.no_account_hint') }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 md:col-span-2 xl:col-span-1 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ __('panel.teachers_registry.catalog_links') }}
                    </p>
                    <span class="mt-3 flex flex-wrap gap-2">
                        @foreach ($profile['links'] as $label => $url)
                            <a
                                href="{{ $url }}"
                                class="rounded-full bg-white px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-950/10 transition duration-75 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10"
                            >
                                {{ $label }}
                            </a>
                        @endforeach
                    </span>
                </div>
            </div>

            {{-- Alocările: disciplinele predate, fiecare cu clasele ei (chip = catalogul clasei). --}}
            <div>
                <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('panel.teachers_registry.assignments_hint') }}
                </p>

                @if (count($profile['assignments']) === 0)
                    <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-10 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <x-filament::icon icon="heroicon-o-book-open" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.teachers_registry.no_assignments_hint') }}</p>
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($profile['assignments'] as $assignment)
                            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                                <span class="block truncate text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $assignment['subject'] }}
                                </span>

                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                    {{ trans_choice('panel.catalog_nav.classes', count($assignment['classes']), ['count' => count($assignment['classes'])]) }}
                                </span>

                                <span class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($assignment['classes'] as $class)
                                        <a
                                            href="{{ $class['url'] }}"
                                            class="rounded-full bg-white px-3 py-1 text-sm font-medium text-gray-700 ring-1 ring-gray-950/10 transition duration-75 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10"
                                        >
                                            {{ $class['label'] }}
                                        </a>
                                    @endforeach
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
