{{-- Regulile de calcul al mediilor, citite din aceleași constante folosite la calcul. Pagina e
     deliberat READ-ONLY: formula e legislație implementată în cod, nu o setare de panou. --}}
<x-filament-panels::page>
    <div class="space-y-8">
        <div class="rounded-xl bg-primary-50 p-4 text-sm text-primary-900 ring-1 ring-primary-600/10 dark:bg-primary-500/10 dark:text-primary-200 dark:ring-primary-400/20">
            <div class="flex items-start gap-3">
                <x-filament::icon icon="heroicon-o-lock-closed" class="mt-0.5 h-5 w-5 shrink-0" />
                <p>{{ __('panel.grading_rules.locked_notice') }}</p>
            </div>
        </div>

        <section class="space-y-3">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ __('panel.grading_rules.by_cycle') }}
            </h2>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->cycleRules() as $rule)
                    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-base font-semibold text-gray-950 dark:text-white">{{ $rule['cycle'] }}</span>
                            <x-filament::badge color="gray">{{ $rule['grades'] }}</x-filament::badge>
                        </div>
                        <p class="mt-3 font-mono text-sm text-primary-700 dark:text-primary-300">{{ $rule['formula'] }}</p>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $rule['note'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ __('panel.grading_rules.evaluation_types') }}
            </h2>

            <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($this->evaluationTypes() as $type)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $type['label'] }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $type['role'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ __('panel.grading_rules.common_rules') }}
            </h2>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->commonRules() as $rule)
                    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $rule['title'] }}</p>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $rule['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
