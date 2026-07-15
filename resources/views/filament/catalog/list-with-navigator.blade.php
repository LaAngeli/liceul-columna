{{-- Pagină de listare cu navigator de catalog: meniu drill-down când nu există context,
     bară de context + tabel când s-a ales o entitate. Reutilizabilă (Note, apoi Absențe). --}}
<x-filament-panels::page>
    @if (! $this->hasCatalogContext())
        @include('filament.catalog.partials.catalog-navigator')
    @else
        @include('filament.catalog.partials.catalog-context-bar')

        {{ $this->table }}
    @endif
</x-filament-panels::page>
