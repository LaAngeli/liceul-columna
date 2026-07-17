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
</x-filament-panels::page>
