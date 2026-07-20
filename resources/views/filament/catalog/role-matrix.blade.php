{{-- Matricea de capabilități, generată prin reflecție peste User. Tabelul e lat prin natura lui
     (o coloană per rol) → derulare pe orizontală ÎN interiorul cardului, nu pe pagină. --}}
<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl bg-gray-50 p-4 text-sm text-gray-600 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
            <div class="flex items-start gap-3">
                <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 h-5 w-5 shrink-0 text-gray-400" />
                <p>{{ __('panel.role_matrix.generated_notice') }}</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-white/10">
                        <th class="sticky left-0 z-10 bg-white px-4 py-3 text-left font-semibold text-gray-950 dark:bg-gray-900 dark:text-white">
                            {{ __('panel.role_matrix.capability') }}
                        </th>
                        @foreach ($this->roles() as $role)
                            <th class="whitespace-nowrap px-3 py-3 text-center font-medium text-gray-500 dark:text-gray-400">
                                {{ $role['label'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($this->capabilities() as $capability)
                        <tr>
                            <td class="sticky left-0 z-10 bg-white px-4 py-3 text-gray-950 dark:bg-gray-900 dark:text-white">
                                {{ $capability['label'] }}
                            </td>
                            @foreach ($this->roles() as $role)
                                <td class="px-3 py-3 text-center">
                                    @if ($capability['roles'][$role['value']] ?? false)
                                        <x-filament::icon
                                            icon="heroicon-s-check-circle"
                                            class="mx-auto h-5 w-5 text-success-600 dark:text-success-400"
                                        />
                                        <span class="sr-only">{{ __('panel.role_matrix.allowed') }}</span>
                                    @else
                                        <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">—</span>
                                        <span class="sr-only">{{ __('panel.role_matrix.denied') }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
