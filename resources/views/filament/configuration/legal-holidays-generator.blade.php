{{-- Generatorul de sărbători legale (Codul muncii, art. 111) pentru anul ales.
     Două registre separate: „de adăugat" (cu bifă — singurele acționabile) și „deja în calendar"
     (informație, fără controale). Pagina, nu modal — vezi comentariul din LegalHolidaysGenerator. --}}
<x-filament-panels::page>
    @php
        $pending = $this->pendingRows();
        $existing = $this->existingRows();
    @endphp

    <div class="space-y-6">
        <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">
            {{ __('panel.holiday_planner.generator.description') }}
        </p>

        {{-- Comutatorul de an: fără el, un an cu toate sărbătorile deja introduse te lăsa în fața
             unei pagini fără nicio acțiune posibilă și fără cale de ieșire. --}}
        @php($pills = $this->yearPills())
        @if (count($pills) > 1)
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($pills as $pill)
                    <button
                        type="button"
                        wire:click="openYear({{ $pill['id'] }})"
                        wire:loading.attr="disabled"
                        @class([
                            'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition',
                            'bg-primary-600 text-white' => $pill['active'],
                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' => ! $pill['active'],
                        ])
                    >
                        {{ $pill['label'] }}

                        @if ($pill['pending'] > 0)
                            <span @class([
                                'rounded-md px-1.5 text-xs font-semibold',
                                'bg-white/20' => $pill['active'],
                                'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300' => ! $pill['active'],
                            ])>{{ $pill['pending'] }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        @endif

        @if (count($pending) > 0)
            <form wire:submit="create" class="space-y-6">
                <div>
                    <h3 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">
                        {{ trans_choice('panel.holiday_planner.generator.pending_heading', count($pending), ['count' => count($pending)]) }}
                    </h3>

                    {{ $this->form }}
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-filament::button type="submit" icon="heroicon-o-plus">
                        {{ __('panel.holiday_planner.generator.submit') }}
                    </x-filament::button>

                    <x-filament::link
                        :href="\App\Filament\Resources\Holidays\HolidayResource::getUrl(parameters: array_filter(['an' => $this->activeYear()?->id]))"
                        color="gray"
                    >
                        {{ __('panel.holiday_planner.generator.back') }}
                    </x-filament::link>
                </div>
            </form>
        @else
            {{-- Starea „nimic de adăugat": înainte, aici stăteau 10 bife dezactivate care nu
                 răspundeau la click — de-asta părea pagina stricată. --}}
            <div class="rounded-xl border border-success-500/30 bg-success-50/60 p-4 dark:border-success-400/30 dark:bg-success-500/10">
                <div class="flex items-start gap-3">
                    <x-filament::icon icon="heroicon-o-check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-success-600 dark:text-success-400" />
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('panel.holiday_planner.generator.all_present_title') }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('panel.holiday_planner.generator.all_present_body') }}
                        </p>
                    </div>
                </div>
            </div>

            <x-filament::link
                :href="\App\Filament\Resources\Holidays\HolidayResource::getUrl(parameters: array_filter(['an' => $this->activeYear()?->id]))"
                color="gray"
            >
                {{ __('panel.holiday_planner.generator.back') }}
            </x-filament::link>
        @endif

        {{-- Sărbătorile deja prezente: informație curată, fără bife moarte. --}}
        @if (count($existing) > 0)
            <div class="space-y-3 border-t border-gray-200 pt-6 dark:border-white/10">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ trans_choice('panel.holiday_planner.generator.existing_heading', count($existing), ['count' => count($existing)]) }}
                </h3>

                <ul class="grid gap-2 md:grid-cols-2">
                    @foreach ($existing as $row)
                        <li class="flex items-baseline justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                            <span class="min-w-0 truncate text-sm text-gray-700 dark:text-gray-300">{{ $row['label'] }}</span>
                            <span class="shrink-0 text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $row['range'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-filament-panels::page>
