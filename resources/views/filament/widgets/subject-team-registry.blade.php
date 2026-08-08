{{-- REGISTRUL ECHIPEI disciplinei (v3) — consultativ: file de ani + un rând per profesor cu
     clasele lui ca pastile. Datele vin gata-așezate din BuildSubjectTeamRegistry; aici doar
     se așază. Fără nicio acțiune de scriere — echipa se modifică în formularul de deasupra.

     Filele stau în CORPUL secțiunii, nu în afterHeader (review 08.08.2026): antetul secțiunii
     Filament nu se înfășoară, iar la 390px coloana de titlu era strivită la ~69px. În corp,
     rândul de file se înfășoară liber și crește cu anii fără să sufoce nimic. Se arată și cu
     UN singur an — altfel anul afișat nu era numit nicăieri, iar o echipă veche (disciplină
     fără alocări în anul curent) trecea drept actuală. --}}
@php
    $registru = $this->registru();
    $ani = $registru['years'];
    $anul = $registru['selected'];
    $profesori = $registru['teachers'];
    $stats = $registru['stats'];
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('panel.resources.teaching_assignments.registry') }}</x-slot>
        <x-slot name="description">{{ __('panel.resources.teaching_assignments.registry_v3_hint') }}</x-slot>

        @if ($anul === null)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('panel.resources.teaching_assignments.registry_no_years') }}
            </p>
        @else
            {{-- FILELE de ani — la vedere, nu după pâlnie: un click = alt an. Anul curent își
                 poartă marcajul; fila activă e plină și o spune și cititorului de ecran. --}}
            <div class="mb-3 flex flex-wrap items-center gap-1.5">
                @foreach ($ani as $an)
                    <button
                        type="button"
                        wire:click="alegeAnul({{ $an['id'] }})"
                        wire:loading.attr="disabled"
                        aria-pressed="{{ $an['id'] === $anul['id'] ? 'true' : 'false' }}"
                        @class([
                            'inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-xs font-semibold transition',
                            'bg-primary-600 text-white' => $an['id'] === $anul['id'],
                            'bg-white text-gray-700 ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/15 dark:hover:bg-white/10' => $an['id'] !== $anul['id'],
                        ])
                    >
                        {{ $an['name'] }}
                        @if ($an['is_current'])
                            <span @class([
                                'rounded-full px-1.5 py-0.5 text-[10px] font-medium leading-none',
                                'bg-white/20 text-white' => $an['id'] === $anul['id'],
                                'bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-300' => $an['id'] !== $anul['id'],
                            ])>{{ __('panel.resources.teaching_assignments.registry_current_year') }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- REZUMATUL anului ales — o singură linie: oameni DISTINCȚI și clase DISTINCTE
                 (nu rânduri/alocări — la engleză, două grupe rămân un singur profesor). --}}
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                {{ trans_choice('panel.resources.teaching_assignments.registry_stats_teachers', $stats['teachers'], ['count' => $stats['teachers']]) }}
                · {{ trans_choice('panel.resources.teaching_assignments.registry_stats_classes', $stats['active'], ['count' => $stats['active']]) }}@if ($stats['withdrawn'] > 0) · {{ trans_choice('panel.resources.teaching_assignments.registry_stats_withdrawn', $stats['withdrawn'], ['count' => $stats['withdrawn']]) }}@endif
            </p>

            @if ($profesori === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('panel.resources.teaching_assignments.registry_empty') }}
                </p>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($profesori as $profesor)
                        <li class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0 md:flex-row md:items-start md:gap-4" wire:key="registru-{{ $anul['id'] }}-{{ $loop->index }}">
                            {{-- Identitatea: nume + grupa de engleză + fișa arhivată, pe o coloană fixă
                                 la lat, ca pastilele să se alinieze între rânduri. --}}
                            <div class="shrink-0 md:w-64">
                                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $profesor['name'] }}</p>
                                @if ($profesor['group'] !== null || $profesor['archived'])
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        @if ($profesor['group'] !== null){{ __('panel.resources.teaching_assignments.registry_group', ['group' => $profesor['group']]) }}@endif
                                        @if ($profesor['group'] !== null && $profesor['archived']) · @endif
                                        @if ($profesor['archived']){{ __('panel.forms.subject.teacher_archived') }}@endif
                                    </p>
                                @endif
                            </div>

                            {{-- Clasele, ca pastile: verde = activă; retrasă = gri, cu starea spusă
                                 în etichetă (culoarea doar o subliniază). --}}
                            <div class="flex flex-1 flex-wrap items-center gap-1.5">
                                @foreach ($profesor['classes'] as $clasa)
                                    <span @class([
                                        'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1',
                                        'bg-green-100 text-green-800 ring-green-600/30 dark:bg-green-400/10 dark:text-green-300 dark:ring-green-400/30' => ! $clasa['withdrawn'],
                                        'bg-gray-100 text-gray-600 ring-gray-950/10 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10' => $clasa['withdrawn'],
                                    ])>
                                        {{ $clasa['label'] }}@if ($clasa['withdrawn'])<span class="ms-1 font-normal">{{ __('panel.resources.teaching_assignments.withdrawn_suffix') }}</span>@endif
                                    </span>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Pe anul CURENT, puntea spre locul unde chiar se modifică echipa. --}}
            @if ($anul['is_current'])
                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('panel.resources.teaching_assignments.registry_edit_hint') }}
                </p>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
