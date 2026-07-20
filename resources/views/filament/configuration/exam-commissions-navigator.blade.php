{{-- Comisiile de examen ca navigator de acoperire: pastile pe ani → barometrul (discipline cu
     examene vs. acoperite) → coada „de acoperit" (creare pre-completată) → cardurile comisiilor
     cu componența nominală (președinte + membri) și stările care cer atenție. --}}
<x-filament-panels::page>
    <div class="space-y-6">
        @php($years = $this->yearPills())
        @php($coverage = $this->coverage())
        @php($cards = $this->commissionCards())

        @if (count($years) > 1)
            <x-filament::tabs :label="__('panel.fields.academic_year')">
                @foreach ($years as $year)
                    <x-filament::tabs.item
                        :active="$this->activeYearId() === $year['id']"
                        :badge="$year['count'] > 0 ? $year['count'] : null"
                        wire:click="openYear({{ $year['id'] }})"
                    >
                        {{ $year['label'] }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
        @endif

        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $this->configHint() }}
        </p>

        {{-- Barometrul acoperirii — doar când anul are examene de corigență. --}}
        @if ($coverage['subjects_with_exams'] > 0)
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ trans_choice('panel.exam_commissions.subjects_with_exams', $coverage['subjects_with_exams'], ['count' => $coverage['subjects_with_exams']]) }}
                </span>
                <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">·</span>
                <span @class([
                    'text-sm font-medium',
                    'text-success-600 dark:text-success-400' => $coverage['uncovered'] === [],
                    'text-gray-950 dark:text-white' => $coverage['uncovered'] !== [],
                ])>
                    {{ __('panel.exam_commissions.covered', ['count' => $coverage['covered']]) }}
                </span>

                @if ($coverage['uncovered'] !== [])
                    <x-filament::badge color="danger" size="sm">
                        {{ trans_choice('panel.exam_commissions.uncovered_badge', count($coverage['uncovered']), ['count' => count($coverage['uncovered'])]) }}
                    </x-filament::badge>
                @endif

                @if ($coverage['unassigned_exams'] > 0)
                    <x-filament::badge color="warning" size="sm">
                        {{ trans_choice('panel.exam_commissions.unassigned_exams', $coverage['unassigned_exams'], ['count' => $coverage['unassigned_exams']]) }}
                    </x-filament::badge>
                @endif
            </div>
        @endif

        {{-- Coada „de acoperit": disciplinele cu examene dar fără comisie. --}}
        @if ($coverage['uncovered'] !== [])
            <section aria-label="{{ __('panel.exam_commissions.to_cover') }}">
                <h2 class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    {{ __('panel.exam_commissions.to_cover') }}
                </h2>

                <div class="mt-2 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($coverage['uncovered'] as $gap)
                        <a
                            href="{{ $gap['create_url'] }}"
                            class="group flex items-center justify-between gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-danger-300 transition duration-75 hover:ring-2 hover:ring-danger-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger-500 dark:bg-gray-900 dark:ring-danger-500/40"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $gap['name'] }}</span>
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                    {{ trans_choice('panel.exam_commissions.gap_exams', $gap['exams'], ['count' => $gap['exams']]) }}
                                </span>
                            </span>
                            <span class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-danger-600 group-hover:underline dark:text-danger-400">
                                <x-filament::icon icon="heroicon-o-plus" class="h-4 w-4" />
                                {{ __('panel.exam_commissions.create_commission') }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Cardurile comisiilor (incomplete întâi). --}}
        @if ($cards === [])
            <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <x-filament::icon icon="heroicon-o-user-group" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.exam_commissions.empty_title') }}</p>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">{{ __('panel.exam_commissions.empty_description') }}</p>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                @foreach ($cards as $card)
                    <a
                        href="{{ $card['edit_url'] }}"
                        @class([
                            'group flex flex-col rounded-xl bg-white p-4 shadow-sm ring-1 transition duration-75 hover:ring-2 hover:ring-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:bg-gray-900 dark:hover:ring-primary-500',
                            'ring-warning-400 dark:ring-warning-500/50' => ! $card['complete'],
                            'ring-gray-950/5 dark:ring-white/10' => $card['complete'],
                        ])
                    >
                        <span class="flex items-start justify-between gap-2">
                            <span class="min-w-0">
                                <span class="block truncate text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                    {{ $card['subject'] }}
                                </span>
                                <span class="mt-0.5 block truncate text-xs text-gray-400 dark:text-gray-500">{{ $card['name'] }}</span>
                            </span>

                            <span class="flex shrink-0 flex-col items-end gap-1">
                                @if ($card['president'] === null)
                                    <x-filament::badge color="danger" size="sm">{{ __('panel.exam_commissions.no_president') }}</x-filament::badge>
                                @endif
                                @if ($card['persons'] < \App\Filament\Resources\ExamCommissions\Pages\ListExamCommissions::MIN_PERSONS)
                                    <x-filament::badge color="warning" size="sm">
                                        {{ __('panel.exam_commissions.thin', ['count' => $card['persons']]) }}
                                    </x-filament::badge>
                                @endif
                            </span>
                        </span>

                        <span class="mt-3 space-y-1.5 text-sm">
                            @if ($card['president'] !== null)
                                <span class="flex items-center gap-2">
                                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary-600 text-[0.6rem] font-bold text-white dark:bg-primary-500" title="{{ __('panel.forms.exam_commission.president') }}">P</span>
                                    <span class="truncate font-medium text-gray-950 dark:text-white">{{ $card['president'] }}</span>
                                </span>
                            @endif
                            @foreach ($card['members'] as $member)
                                <span class="flex items-center gap-2">
                                    <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-gray-300 dark:bg-gray-600" aria-hidden="true"></span>
                                    <span class="truncate text-gray-600 dark:text-gray-300">{{ $member }}</span>
                                </span>
                            @endforeach
                            @if ($card['president'] === null && $card['members'] === [])
                                <span class="text-gray-400 dark:text-gray-500">{{ __('panel.exam_commissions.no_composition') }}</span>
                            @endif
                        </span>

                        <span class="mt-3 border-t border-gray-100 pt-2 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                            @if ($card['exams'] > 0)
                                {{ trans_choice('panel.exam_commissions.exams_assigned', $card['exams'], ['count' => $card['exams']]) }}
                            @else
                                {{ __('panel.exam_commissions.no_exams_yet') }}
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
