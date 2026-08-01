{{-- Planificatorul meniului cu axa temporală standard (Toate / Zi / Săptămână / Lună /
     Personalizat — aceeași bară ca la Teme/Note/Absențe). Zi/Săptămână/Lună = planificare (grila
     completă, cu zilele goale și comenzile AO); Toate/Personalizat = consultare (doar zilele cu
     meniu, pe săptămâni, recente întâi). Cititorii văd totul fără comenzi. --}}
<x-filament-panels::page>
    @php
        $canManage = $this::canManage();
        $planning = $this->planningWeeks();
        $archive = $this->archiveWeeks();
    @endphp

    <div class="space-y-6">
        @include('filament.catalog.partials.time-bar')

        @if ($planning !== null)
            {{-- PLANIFICARE: zi (un card), săptămâna (o grilă), luna (o grilă per săptămână). --}}
            <div class="space-y-8">
                @foreach ($planning as $week)
                    <div class="space-y-3">
                        @if ($week['label'] !== null)
                            <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $week['label'] }}</h2>
                        @endif

                        <div @class([
                            'grid gap-4',
                            'md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5' => count($week['days']) > 1,
                            'mx-auto w-full max-w-xl' => count($week['days']) === 1,
                        ])>
                            @foreach ($week['days'] as $day)
                                @include('filament.canteen.partials.day-card', ['day' => $day, 'canManage' => $canManage])
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- CONSULTARE: arhiva zilelor publicate, grupată pe săptămâni. --}}
            @forelse ($archive ?? [] as $week)
                <div class="space-y-3">
                    <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $week['label'] }}</h2>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
                        @foreach ($week['days'] as $day)
                            @include('filament.canteen.partials.day-card', ['day' => $day, 'canManage' => $canManage])
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    {{ $canManage ? __('panel.forms.canteen.planner_none_yet') : __('panel.forms.canteen.empty_reader') }}
                </p>
            @endforelse
        @endif
    </div>
</x-filament-panels::page>
