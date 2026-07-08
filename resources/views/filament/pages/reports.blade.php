<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('panel.pages.reports.hint') }}
    </p>

    <form wire:submit="generate" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray">
            {{ __('panel.pages.reports.generate') }}
        </x-filament::button>
    </form>
</x-filament-panels::page>
