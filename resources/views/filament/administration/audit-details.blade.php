{{-- Fișa unei intrări de jurnal: investigarea COMPLETĂ pe loc — cine (actor + rol) / când
     (ora școlii + UTC) / ce (eveniment + severitate + obiect descris) / diff-ul vechi→nou /
     contextul tehnic (IP, dispozitiv, URL ca TEXT). Fără niciun link spre alte module:
     jurnalul e instrument de trasabilitate, nu meniu de navigare. Lipsurile = n/a. --}}
<x-filament-panels::page>
    @php($record = $this->record)
    @php($actor = $this->actor())
    @php($moment = $this->moment())
    @php($subject = $this->subject())
    @php($changes = $this->changes())
    @php($access = $this->accessContext())
    @php($technical = $this->technical())
    @php($severity = $record->severity())

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- CE s-a întâmplat: eveniment + severitate + obiect. --}}
            <section
                class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                aria-label="{{ __('panel.audit_view.what') }}"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::badge :color="match ($record->event) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted', 'forceDeleted' => 'danger',
                        'viewed', 'exported' => 'info',
                        default => 'gray',
                    }">
                        {{ $record->eventLabel() }}
                    </x-filament::badge>

                    <x-filament::badge :color="$severity" size="sm">
                        {{ $this->severityLabel() }}
                    </x-filament::badge>

                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $subject['category'] }} · {{ $subject['type'] }}
                    </span>
                </div>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.audit_view.object') }}</dt>
                        <dd class="text-right font-medium text-gray-950 dark:text-white">
                            {{ $subject['type'] }} #{{ $subject['id'] }}
                            @if ($subject['label'] !== null)
                                <span class="block text-sm font-normal text-gray-500 dark:text-gray-400">{{ $subject['label'] }}</span>
                            @elseif ($subject['gone'])
                                <span class="block text-xs font-normal text-gray-400 dark:text-gray-500">{{ __('panel.audit_view.object_gone') }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.audit_view.result') }}</dt>
                        <dd class="text-right text-gray-950 dark:text-white">{{ $record->resultLabel() }}</dd>
                    </div>
                </dl>
            </section>

            {{-- CONTEXTUL accesului (viewed/exported) sau DIFF-ul vechi→nou. --}}
            @if ($access !== null)
                <section
                    class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                    aria-label="{{ __('panel.audit_view.access_context') }}"
                >
                    <h2 class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ __('panel.audit_view.access_context') }}
                    </h2>
                    <p class="mt-1.5 text-sm text-gray-950 dark:text-white">{{ $access }}</p>
                </section>
            @endif

            <section
                class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                aria-label="{{ __('panel.audit_view.changes') }}"
            >
                <h2 class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    {{ __('panel.audit_view.changes') }}
                </h2>

                @if ($changes === [])
                    <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">
                        {{ __('panel.audit_view.no_changes') }}
                    </p>
                @else
                    {{-- Tabelul diff: lat pe desktop, scroll propriu pe mobil. --}}
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full min-w-[28rem] text-start text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400 dark:border-white/10 dark:text-gray-500">
                                    <th class="py-1.5 pe-3 text-start font-medium">{{ __('panel.audit_view.field') }}</th>
                                    <th class="py-1.5 pe-3 text-start font-medium">{{ __('panel.forms.audit.old_values') }}</th>
                                    <th class="py-1.5 text-start font-medium">{{ __('panel.forms.audit.new_values') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-white/5">
                                @foreach ($changes as $change)
                                    <tr class="align-top">
                                        <td class="py-2 pe-3 font-medium text-gray-950 dark:text-white">
                                            {{ str_replace('_', ' ', $change['field']) }}
                                        </td>
                                        <td class="py-2 pe-3">
                                            @if ($change['old'] === null)
                                                <span class="text-gray-300 dark:text-gray-600">—</span>
                                            @else
                                                <span class="whitespace-pre-wrap break-all font-mono text-xs text-danger-700 dark:text-danger-400">{{ $change['old'] }}</span>
                                            @endif
                                        </td>
                                        <td class="py-2">
                                            @if ($change['new'] === null)
                                                <span class="text-gray-300 dark:text-gray-600">—</span>
                                            @else
                                                <span class="whitespace-pre-wrap break-all font-mono text-xs text-success-700 dark:text-success-400">{{ $change['new'] }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Contextul TEHNIC — text, nu navigație. --}}
            <section
                class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                aria-label="{{ __('panel.audit_view.technical') }}"
            >
                <h2 class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    {{ __('panel.audit_view.technical') }}
                </h2>

                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.forms.consent.ip') }}</dt>
                        <dd class="text-right font-mono text-xs text-gray-950 dark:text-white">{{ $technical['ip'] }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.audit_view.device') }}</dt>
                        <dd class="text-right text-gray-950 dark:text-white">{{ $technical['device'] }}</dd>
                    </div>
                    @if ($technical['agent'] !== null)
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('panel.audit_view.user_agent') }}</dt>
                            <dd class="mt-0.5 break-all font-mono text-xs text-gray-500 dark:text-gray-400">{{ $technical['agent'] }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">URL</dt>
                        <dd class="mt-0.5 break-all font-mono text-xs text-gray-500 dark:text-gray-400">{{ $technical['url'] }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        {{-- CINE + CÂND. --}}
        <aside class="space-y-6">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('panel.audit_view.who') }}
                </h2>

                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.fields.author') }}</dt>
                        <dd class="text-right font-medium text-gray-950 dark:text-white">{{ $actor['name'] }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.audit_view.role') }}</dt>
                        <dd class="text-right text-gray-950 dark:text-white">{{ $actor['role'] ?? 'n/a' }}</dd>
                    </div>
                </dl>

                @if ($actor['deleted'])
                    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                        {{ __('panel.audit_view.deleted_user_hint') }}
                    </p>
                @endif
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('panel.audit_view.when') }}
                </h2>

                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.audit_view.school_time') }}</dt>
                        <dd class="text-right tabular-nums font-medium text-gray-950 dark:text-white">{{ $moment['local'] }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">UTC</dt>
                        <dd class="text-right tabular-nums text-xs text-gray-500 dark:text-gray-400">{{ $moment['utc'] }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.audit_view.entry_id') }}</dt>
                        <dd class="text-right tabular-nums text-xs text-gray-500 dark:text-gray-400">#{{ $record->getKey() }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Nota de imuabilitate: fișa spune și CE GARANTEAZĂ jurnalul. --}}
            <p class="flex items-start gap-1.5 rounded-lg bg-gray-50 p-2.5 text-xs text-gray-500 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
                <x-filament::icon icon="heroicon-o-lock-closed" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                {{ __('panel.audit_view.immutable_note') }}
            </p>

            <a
                href="{{ \App\Filament\Resources\Audits\AuditResource::getUrl('index', array_filter(['categorie' => \App\Support\AuditCategories::categoryOf((string) $record->auditable_type)])) }}"
                class="inline-flex min-h-9 items-center gap-1 text-sm font-medium text-gray-500 hover:text-primary-600 hover:underline dark:text-gray-400 dark:hover:text-primary-400"
            >
                <x-filament::icon icon="heroicon-o-arrow-uturn-left" class="h-4 w-4" />
                {{ __('panel.audit_view.back') }}
            </a>
        </aside>
    </div>
</x-filament-panels::page>
