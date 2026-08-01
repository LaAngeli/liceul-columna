{{-- Planificatorul săptămânal al meniului: grila zilelor (azi evidențiat), navigare pe săptămâni.
     Administratorul operațional lucrează direct pe carduri („Adaugă"/„Modifică"/„Preia de
     săptămâna trecută"); cititorii văd aceeași grilă fără comenzi. Rubricile goale se sar. --}}
<x-filament-panels::page>
    @php
        $week = $this->week();
        $canManage = $this::canManage();
        $sections = [
            ['title' => __('panel.forms.canteen.breakfast'), 'icon' => 'heroicon-o-sun', 'fields' => \App\Models\CanteenMenu::breakfastFields()],
            ['title' => __('panel.forms.canteen.lunch'), 'icon' => 'heroicon-o-fire', 'fields' => \App\Models\CanteenMenu::lunchFields()],
        ];
    @endphp

    <div class="space-y-6">
        {{-- Navigarea pe săptămâni --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-filament::button
                tag="a"
                color="gray"
                icon="heroicon-o-chevron-left"
                :href="$this->weekUrl($week['monday']->copy()->subWeek()->toDateString())"
            >
                <span class="hidden sm:inline">{{ __('panel.forms.canteen.planner_prev') }}</span>
            </x-filament::button>

            <div class="text-center">
                <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $week['label'] }}</p>
                @if (! $week['isCurrent'])
                    <a
                        href="{{ $this->weekUrl($week['today']->toDateString()) }}"
                        class="text-sm font-medium text-primary-600 underline underline-offset-2 dark:text-primary-400"
                    >
                        {{ __('panel.forms.canteen.planner_current') }}
                    </a>
                @endif
            </div>

            <x-filament::button
                tag="a"
                color="gray"
                icon="heroicon-o-chevron-right"
                icon-position="after"
                :href="$this->weekUrl($week['monday']->copy()->addWeek()->toDateString())"
            >
                <span class="hidden sm:inline">{{ __('panel.forms.canteen.planner_next') }}</span>
            </x-filament::button>
        </div>

        {{-- Grila zilelor --}}
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
            @foreach ($week['days'] as $day)
                @php($menu = $day['menu'])

                <section @class([
                    'flex flex-col rounded-xl bg-white p-4 shadow-sm ring-1 dark:bg-gray-900',
                    'ring-primary-500/60' => $day['isToday'],
                    'ring-gray-950/5 dark:ring-white/10' => ! $day['isToday'],
                ])>
                    <header class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $day['label'] }}</h2>
                        @if ($day['isToday'])
                            <x-filament::badge color="success">{{ __('panel.forms.canteen.planner_today') }}</x-filament::badge>
                        @endif
                    </header>

                    @if ($menu === null)
                        <div class="flex flex-1 flex-col items-center justify-center gap-3 py-6">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('panel.forms.canteen.planner_not_published') }}
                            </p>

                            @if ($canManage)
                                <x-filament::button tag="a" size="sm" icon="heroicon-o-plus" :href="$this->createUrl($day['date'])">
                                    {{ __('panel.forms.canteen.planner_add') }}
                                </x-filament::button>

                                @php($source = $this->previousWeekMenu($day['date']))
                                @if ($source !== null)
                                    <x-filament::button
                                        tag="a"
                                        size="sm"
                                        color="gray"
                                        icon="heroicon-o-document-duplicate"
                                        :href="$this->createUrl($day['date'], $source->id)"
                                    >
                                        {{ __('panel.forms.canteen.planner_take_prev') }}
                                    </x-filament::button>
                                @endif
                            @endif
                        </div>
                    @else
                        <div class="flex-1 space-y-4">
                            @foreach ($sections as $section)
                                @php($filled = collect($section['fields'])->filter(fn (string $field): bool => filled($menu->{$field})))

                                @if ($filled->isNotEmpty())
                                    <div>
                                        <h3 class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                                            <x-filament::icon :icon="$section['icon']" class="h-4 w-4" />
                                            {{ $section['title'] }}
                                        </h3>
                                        <dl class="divide-y divide-gray-100 dark:divide-white/10">
                                            @foreach ($filled as $field)
                                                <div class="flex items-baseline justify-between gap-3 py-1.5">
                                                    <dt class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                                        {{ __('panel.forms.canteen.'.$field) }}
                                                    </dt>
                                                    <dd class="text-right text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $menu->{$field} }}
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </div>
                                @endif
                            @endforeach

                            @if (filled($menu->notes))
                                <p class="rounded-lg bg-gray-50 p-2.5 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">
                                    {{ $menu->notes }}
                                </p>
                            @endif
                        </div>

                        @if ($canManage)
                            <footer class="mt-4 border-t border-gray-100 pt-3 dark:border-white/10">
                                <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-o-pencil-square" :href="$this->editUrl($menu)">
                                    {{ __('panel.forms.canteen.planner_edit') }}
                                </x-filament::button>
                            </footer>
                        @endif
                    @endif
                </section>
            @endforeach
        </div>

        {{-- Săptămână complet goală, văzută de un cititor: explicație, nu carduri mute. --}}
        @if (! $canManage && collect($week['days'])->every(fn (array $day): bool => $day['menu'] === null))
            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                {{ __('panel.forms.canteen.empty_reader') }}
            </p>
        @endif
    </div>
</x-filament-panels::page>
