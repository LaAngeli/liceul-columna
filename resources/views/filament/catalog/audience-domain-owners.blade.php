{{-- RESPONSABILII DE DOMENIU: cele două domenii, cine le ține, ce lipsește — întrebarea școlii,
     pusă într-un singur loc. Vezi motivarea din App\Filament\Pages\AudienceDomainOwners. --}}
<x-filament-panels::page>
    <div class="space-y-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('panel.audience_domains.intro') }}
        </p>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($this->domains() as $domain)
                <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $domain['label'] }}</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $domain['description'] }}</p>
                        </div>

                        {{-- Starea, ca pastilă: acoperit / descoperit / mai mulți responsabili. --}}
                        <span @class([
                            'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset',
                            'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30' => $domain['state'] === 'covered',
                            'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30' => $domain['state'] === 'uncovered',
                            'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30' => $domain['state'] === 'multiple',
                        ])>
                            {{ __('panel.audience_domains.states.'.$domain['state']) }}
                        </span>
                    </div>

                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                        @if ($domain['owners'] === [])
                            {{-- Descoperit NU e o eroare: audiențele merg pe fallback la conducere.
                                 Se spune explicit, ca să nu pară că ceva e stricat. --}}
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('panel.audience_domains.uncovered_note') }}
                            </p>
                        @else
                            <ul class="space-y-1">
                                @foreach ($domain['owners'] as $owner)
                                    <li class="flex items-baseline justify-between gap-3 text-sm">
                                        <span class="truncate font-medium text-gray-950 dark:text-white">{{ $owner['name'] }}</span>
                                        <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $owner['role'] }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            @if ($domain['state'] === 'multiple')
                                <p class="mt-2 text-xs text-danger-600 dark:text-danger-400">
                                    {{ __('panel.audience_domains.multiple_note') }}
                                </p>
                            @endif
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        {{ ($this->assignAction)(['domain' => $domain['value']]) }}

                        @if ($domain['owners'] !== [])
                            {{ ($this->clearAction)(['domain' => $domain['value']]) }}
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
