{{-- Banda „Acțiuni rapide" — Section nativă Filament (gestionează light/dark) + butoane native. --}}
<x-filament::section
    :heading="__('panel.widgets.quick_actions.heading')"
    icon="heroicon-o-bolt"
    compact
>
    <div class="fi-quick-actions__row">
        @foreach ($actions as $action)
            <x-filament::button
                tag="a"
                :href="$action['url']"
                :icon="$action['icon']"
                :color="$action['primary'] ? 'primary' : 'gray'"
                size="sm"
            >
                {{ $action['label'] }}
            </x-filament::button>
        @endforeach
    </div>
</x-filament::section>
