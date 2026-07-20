{{-- Generatorul de sărbători legale: lista propunerilor din Codul muncii (art. 111) pentru anul
     școlar activ, bifă cu bifă — cele existente sunt marcate și blocate, nimic nebifat nu se
     creează. Pagina, nu modal — vezi comentariul din LegalHolidaysGenerator. --}}
<x-filament-panels::page>
    <div class="space-y-6">
        <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">
            {{ __('panel.holiday_planner.generator.description') }}
        </p>

        <form wire:submit="create" class="space-y-6">
            {{ $this->form }}

            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button type="submit" icon="heroicon-o-plus">
                    {{ __('panel.holiday_planner.generator.submit') }}
                </x-filament::button>

                <x-filament::link
                    :href="\App\Filament\Resources\Holidays\HolidayResource::getUrl()"
                    color="gray"
                >
                    {{ __('panel.holiday_planner.generator.back') }}
                </x-filament::link>
            </div>
        </form>
    </div>
</x-filament-panels::page>
