{{-- ISTORICUL MENIURILOR (AO): lunile cu meniu în stânga, luna deschisă ca TABEL LUNAR — aceeași
     structură ca meniul tipărit al școlii (un rând pe zi, dejunul în 4 rubrici, prânzul în 6).
     Lățimea reală trăiește într-un container cu scroll orizontal propriu, cu data lipită la stânga:
     pe telefon nimic nu se taie (regula mobile-first a panoului). --}}
<x-filament-panels::page>
    @php
        $months = $this->months();
        $active = $this->activeMonth();
        $rows = $this->rows();
    @endphp

    @if ($months === [])
        <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <x-filament::icon icon="heroicon-o-building-storefront" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.forms.canteen.history_empty') }}</p>
        </div>
    @else
        <div class="space-y-4">
            {{-- Lunile: pastile derulabile, recente întâi. Starea spune de ce e luna acolo —
                 „în curs" și „urmează" nu sunt arhivă, dar se consultă din același loc. --}}
            <div class="flex flex-wrap items-center gap-2">
                <span class="shrink-0 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    {{ __('panel.forms.canteen.history_months') }}
                </span>

                @foreach ($months as $month)
                    <button
                        type="button"
                        wire:click="openMonth('{{ $month['key'] }}')"
                        wire:loading.attr="disabled"
                        @class([
                            'inline-flex shrink-0 items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium ring-1 transition duration-75 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 disabled:opacity-70',
                            'bg-primary-600 text-white ring-primary-600' => $month['key'] === $active,
                            'bg-white text-gray-700 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/10' => $month['key'] !== $active,
                        ])
                    >
                        {{ $month['label'] }}

                        <span @class([
                            'rounded-full px-1.5 py-0.5 text-xs tabular-nums',
                            'bg-white/20' => $month['key'] === $active,
                            'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400' => $month['key'] !== $active,
                        ])>{{ $month['days'] }}</span>

                        @if ($month['state'] !== 'archived')
                            <span @class([
                                'text-xs font-normal',
                                'text-white/80' => $month['key'] === $active,
                                'text-primary-600 dark:text-primary-400' => $month['key'] !== $active,
                            ])>
                                {{ __('panel.forms.canteen.history_state_'.$month['state']) }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- TABELUL LUNAR — structura meniului tipărit: antet pe două rânduri (Dejun / Prânz
                 grupează rubricile lor), o zi pe rând. --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-950/5 px-4 py-3 dark:border-white/10">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $this->activeMonthLabel() }}</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ trans_choice('panel.forms.canteen.history_days', count($rows), ['count' => count($rows)]) }}
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-max text-start text-sm">
                        <thead>
                            <tr class="border-b border-gray-950/5 dark:border-white/10">
                                <th rowspan="2" class="sticky left-0 z-[1] bg-white px-4 py-2 text-start align-bottom font-semibold text-gray-950 dark:bg-gray-900 dark:text-white">
                                    {{ __('panel.forms.canteen.date') }}
                                </th>
                                <th colspan="4" class="border-s border-gray-950/5 px-3 py-2 text-center font-semibold text-gray-950 dark:border-white/10 dark:text-white">
                                    {{ __('panel.forms.canteen.breakfast') }}
                                </th>
                                <th colspan="6" class="border-s border-gray-950/5 px-3 py-2 text-center font-semibold text-gray-950 dark:border-white/10 dark:text-white">
                                    {{ __('panel.forms.canteen.lunch') }}
                                </th>
                            </tr>
                            <tr class="border-b border-gray-950/5 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                                @foreach (\App\Models\CanteenMenu::breakfastFields() as $index => $field)
                                    <th @class([
                                        'px-3 py-2 text-start font-medium',
                                        'border-s border-gray-950/5 dark:border-white/10' => $index === 0,
                                    ])>{{ __('panel.forms.canteen.'.$field) }}</th>
                                @endforeach

                                @foreach (\App\Models\CanteenMenu::lunchFields() as $index => $field)
                                    <th @class([
                                        'px-3 py-2 text-start font-medium',
                                        'border-s border-gray-950/5 dark:border-white/10' => $index === 0,
                                    ])>{{ __('panel.forms.canteen.'.$field) }}</th>
                                @endforeach
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                            @foreach ($rows as $row)
                                <tr class="align-top hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="sticky left-0 z-[1] bg-white px-4 py-2 dark:bg-gray-900">
                                        <a href="{{ $row['editUrl'] }}" class="font-medium text-primary-600 tabular-nums hover:underline dark:text-primary-400">
                                            {{ $row['date'] }}
                                        </a>
                                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $row['weekday'] }}</p>

                                        @if ($row['notes'])
                                            <p class="mt-1 max-w-40 text-xs text-warning-700 dark:text-warning-400">{{ $row['notes'] }}</p>
                                        @endif
                                    </td>

                                    @foreach ([...$row['breakfast'], ...$row['lunch']] as $index => $value)
                                        <td @class([
                                            'max-w-44 px-3 py-2 text-gray-700 dark:text-gray-200',
                                            'border-s border-gray-950/5 dark:border-white/10' => $index === 0 || $index === 4,
                                        ])>
                                            {{ $value ?: '—' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
