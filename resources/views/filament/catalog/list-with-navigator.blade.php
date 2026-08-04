{{-- Pagină de listare cu navigator de catalog: meniu drill-down când nu există context,
     bară de context + tabel când s-a ales o entitate. Reutilizabilă (Note, Absențe, Teme).
     Paginile cu HasTimeNavigator primesc automat și bara temporală (Zi/Săptămână/Lună). --}}
<x-filament-panels::page>
    @if (! $this->hasCatalogContext())
        @include('filament.catalog.partials.catalog-navigator')
    @else
        @include('filament.catalog.partials.catalog-context-bar')

        @if (method_exists($this, 'timePills'))
            @include('filament.catalog.partials.time-bar')
        @endif

        @if (method_exists($this, 'showsAbsenceMap') && $this->showsAbsenceMap())
            {{-- Absențe, în context de clasă: harta elevi × zile în locul listei rând-per-absență. --}}
            @include('filament.catalog.partials.absence-map')
        @else
            @if (method_exists($this, 'showsAbsenceMap') && $this->catalogClassIdInContext() !== null)
                {{-- În modul listă, drumul înapoi spre hartă stă la vedere, deasupra tabelului. --}}
                <div class="flex justify-end">
                    <button
                        type="button"
                        wire:click="setAbsenceView(null)"
                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-700 ring-1 ring-gray-950/10 transition hover:bg-gray-50 dark:text-gray-200 dark:ring-white/20 dark:hover:bg-white/5"
                    >
                        <x-filament::icon icon="heroicon-o-table-cells" class="h-4 w-4" />
                        {{ __('absence_map.switch_to_map') }}
                    </button>
                </div>
            @endif

            {{ $this->table }}
        @endif
    @endif

    {{-- Pe ATERIZARE tabelul nu se randează, iar `x-filament-panels::page` lasă modalele în seama
         lui (pagina e o listare) — deci o acțiune de antet cu formular nu s-ar deschide deloc.
         În context, tabelul îl randează primul, iar linia asta devine no-op (Filament ține un
         flag `hasActionsModalRendered`), deci nu apar modale duplicate. --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
