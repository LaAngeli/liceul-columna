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

        {{ $this->table }}
    @endif

    {{-- Pe ATERIZARE tabelul nu se randează, iar `x-filament-panels::page` lasă modalele în seama
         lui (pagina e o listare) — deci o acțiune de antet cu formular nu s-ar deschide deloc.
         În context, tabelul îl randează primul, iar linia asta devine no-op (Filament ține un
         flag `hasActionsModalRendered`), deci nu apar modale duplicate. --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
