{{-- Registrul corpului didactic: vederi-segmente (Toți / Diriginți / Fără alocări / Fără cont /
     Arhivă) cu badge-uri → tabelul restrâns la vederea activă. Stare în URL (?vedere=). --}}
<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::tabs :label="__('panel.teachers_registry.function')">
            @foreach ($this->viewPills() as $pill)
                <x-filament::tabs.item
                    :active="$this->activeView() === $pill['key']"
                    :badge="$pill['count']"
                    :badge-color="$pill['attention'] ? 'warning' : 'gray'"
                    wire:click="openView('{{ $pill['key'] }}')"
                >
                    {{ $pill['label'] }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $this->registryHint() }}
        </p>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
