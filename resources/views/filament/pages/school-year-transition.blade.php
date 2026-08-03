{{-- Trecerea în anul nou: un singur ecran pentru toată operațiunea — anul, semestrele, structura
     care urcă o treaptă, promoția care termină și elevii. Previzualizarea de sus se recalculează
     la fiecare schimbare, iar sub buton stă lista a ce rămâne de făcut manual. --}}
<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('panel.pages.year_transition.hint') }}
        </p>

        {{-- RAPORTUL ultimei execuții: rămâne pe ecran, ca operatorul să vadă ce s-a întâmplat
             fără să caute prin notificări. --}}
        @if ($this->report !== null)
            <div class="rounded-xl bg-success-50 p-4 ring-1 ring-success-600/20 dark:bg-success-400/10 dark:ring-success-400/30">
                <p class="text-sm font-semibold text-success-800 dark:text-success-300">
                    {{ __('panel.pages.year_transition.done', ['year' => $this->report['year'] ?? '']) }}
                </p>
                <ul class="mt-2 space-y-1 text-sm text-success-800/90 dark:text-success-300/90">
                    <li>{{ __('panel.pages.year_transition.preview_terms') }}: {{ $this->report['terms'] }}</li>
                    <li>{{ __('panel.pages.year_transition.preview_classes') }}: {{ $this->report['classes'] }}</li>
                    <li>{{ __('panel.pages.year_transition.preview_assignments') }}: {{ $this->report['assignments'] }}</li>
                    <li>{{ __('panel.pages.year_transition.preview_graduates') }}: {{ $this->report['graduates'] }}</li>
                    <li>{{ __('panel.pages.year_transition.preview_students') }}: {{ $this->report['students'] }}</li>
                </ul>
            </div>
        @endif

        <form wire:submit="start" class="space-y-6">
            {{ $this->form }}

            {{-- PREVIZUALIZAREA e decizia: se calculează din aceeași sursă ca execuția, deci nu
                 poate promite altceva decât se va scrie. --}}
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('panel.pages.year_transition.preview') }}
                </h3>

                <dl class="mt-3 divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($this->previewRows() as $row)
                        <div class="flex items-center justify-between gap-3 py-2">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">{{ $row['label'] }}</dt>
                            <dd @class([
                                'text-sm font-medium',
                                'text-gray-950 dark:text-white' => $row['tone'] === 'primary',
                                'text-success-700 dark:text-success-400' => $row['tone'] === 'success',
                                'text-warning-700 dark:text-warning-400' => $row['tone'] === 'warning',
                                'text-danger-700 dark:text-danger-400' => $row['tone'] === 'danger',
                            ])>{{ $row['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>

                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    <span class="font-medium">{{ __('panel.pages.year_transition.remaining') }}</span><br>
                    {{ $this->remainingSteps() }}
                </p>
            </div>

            <x-filament::button type="submit" size="lg" icon="heroicon-o-arrow-path-rounded-square">
                {{ __('panel.pages.year_transition.submit') }}
            </x-filament::button>
        </form>
    </div>

    {{-- Pagina nu e o listare, dar containerul de modale nu strică: acțiunile de câmp
         (ex. selectoarele cu creare rapidă) își găsesc unde randa. --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
