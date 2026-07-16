{{-- Secțiune de configurare legată de anul școlar: pastile pe ani (anul curent implicit) →
     tabelul restrâns la anul activ. --}}
<x-filament-panels::page>
    <div class="space-y-6">
        @php($years = $this->yearPills())

        @if (count($years) > 1)
            <x-filament::tabs :label="__('panel.fields.academic_year')">
                @foreach ($years as $year)
                    <x-filament::tabs.item
                        :active="$this->activeYearId() === $year['id']"
                        :badge="$year['count']"
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

        {{ $this->table }}
    </div>
</x-filament-panels::page>
