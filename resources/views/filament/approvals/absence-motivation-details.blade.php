{{-- Fișa cererii de motivare: perioada + IMPACTUL (absențele pe care validarea le va marca
     motivate) → motivul integral → justificativul (previzualizare + descărcare, servit doar
     prin ruta autentificată — PII de minor) → cronologia; în lateral, starea (+ tip + termen
     de validare) și contextul elevului. Judecata stă în antet; respingerea cere motiv. --}}
<x-filament-panels::page>
    @php($record = $this->record)
    @php($impact = $this->absenceImpact())
    @php($document = $this->documentMeta())
    @php($context = $this->studentContext())
    @php($timeline = $this->timeline())
    @php($pending = $record->isPending())
    @php($approved = $record->status === \App\Enums\RequestStatus::Approved)
    @php($days = (int) $record->period_start->diffInDays($record->period_end) + 1)

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Perioada + impactul, dintr-o privire. --}}
            <section
                class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                aria-label="{{ __('panel.fields.period') }}"
            >
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-lg bg-gray-50 p-4 text-center dark:bg-white/5">
                        <p class="text-[0.65rem] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            {{ __('panel.fields.period') }}
                        </p>
                        <p class="mt-1 text-lg font-bold tabular-nums leading-snug text-gray-950 dark:text-white">
                            {{ $record->period_start->translatedFormat('d.m.Y') }}<span class="text-gray-400 dark:text-gray-500"> – </span>{{ $record->period_end->translatedFormat('d.m.Y') }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                            {{ trans_choice('panel.absence_motivation_view.days', $days, ['count' => $days]) }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-primary-50 p-4 text-center ring-1 ring-primary-600/20 dark:bg-primary-500/10 dark:ring-primary-500/30">
                        <p class="text-[0.65rem] font-medium uppercase tracking-wide text-primary-600 dark:text-primary-400">
                            {{ __('panel.absence_motivation_view.absences_in_period') }}
                        </p>
                        <p class="mt-1 text-3xl font-bold tabular-nums text-gray-950 dark:text-white">
                            {{ $impact['total'] }}
                        </p>
                    </div>
                </div>

                {{-- Efectul validării — ce va atinge (sau a atins) aprobarea. --}}
                @if ($pending && $impact['unmotivated'] > 0)
                    <p class="mt-3 rounded-lg bg-primary-50 p-2.5 text-sm text-primary-700 ring-1 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30">
                        {{ trans_choice('panel.absence_motivation_view.will_motivate', $impact['unmotivated'], ['count' => $impact['unmotivated']]) }}
                    </p>
                @elseif ($pending)
                    <p class="mt-3 flex items-start gap-1.5 rounded-lg bg-warning-50 p-2.5 text-sm text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-300 dark:ring-warning-500/30">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                        {{ __('panel.absence_motivation_view.no_object') }}
                    </p>
                @elseif ($approved)
                    <p class="mt-3 rounded-lg bg-success-50 p-2.5 text-sm text-success-700 ring-1 ring-success-600/20 dark:bg-success-500/10 dark:text-success-300 dark:ring-success-500/30">
                        {{ __('panel.absence_motivation_view.approved_effect') }}
                    </p>
                @endif

                @if ($impact['items'] === [])
                    <p class="mt-3 text-sm text-gray-400 dark:text-gray-500">
                        {{ __('panel.absence_motivation_view.no_absences') }}
                    </p>
                @else
                    <ul class="mt-3 divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($impact['items'] as $item)
                            <li class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2 text-sm">
                                <span class="flex min-w-0 items-baseline gap-2">
                                    <span class="shrink-0 tabular-nums font-medium text-gray-950 dark:text-white">{{ $item['date'] }}</span>
                                    <span class="truncate text-gray-500 dark:text-gray-400">{{ $item['subject'] }}</span>
                                </span>
                                <x-filament::badge :color="$item['motivated'] ? 'success' : 'warning'" size="sm">
                                    {{ $item['motivated'] ? __('panel.absence_motivation_view.motivated') : __('panel.absence_motivation_view.unmotivated') }}
                                </x-filament::badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Motivul solicitării, integral. --}}
            <section
                class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                aria-label="{{ __('panel.fields.reason') }}"
            >
                <h2 class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    {{ __('panel.fields.reason') }}
                </h2>
                <p class="mt-1.5 max-w-prose whitespace-pre-line text-sm leading-relaxed text-gray-950 dark:text-white">{{ $record->reason }}</p>

                <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-400 dark:text-gray-500">
                    <span>{{ __('panel.homework_correction_view.by_author', ['name' => $record->requestedBy?->name ?? '—']) }}</span>
                    <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">·</span>
                    <span class="tabular-nums">{{ \App\Support\SchoolCalendar::local($record->created_at)?->translatedFormat('d.m.Y H:i') ?? '—' }}</span>
                </div>
            </section>

            {{-- Justificativul: previzualizare + descărcare. Absența lui e o informație de
                 judecată (cerere fără dovadă), deci secțiunea apare mereu. --}}
            <section
                class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                aria-label="{{ __('panel.absence_motivation_view.document') }}"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ __('panel.absence_motivation_view.document') }}
                    </h2>
                    @if ($document !== null && ! $document['missing'])
                        <x-filament::button
                            tag="a"
                            :href="$document['download_url']"
                            color="gray"
                            size="sm"
                            icon="heroicon-o-arrow-down-tray"
                        >
                            {{ __('panel.absence_motivation_view.document_download') }}
                        </x-filament::button>
                    @endif
                </div>

                @if ($document === null)
                    <p class="mt-2 flex items-start gap-1.5 text-sm text-gray-400 dark:text-gray-500">
                        <x-filament::icon icon="heroicon-o-paper-clip" class="mt-0.5 h-4 w-4 shrink-0" />
                        {{ __('panel.absence_motivation_view.document_none') }}
                    </p>
                @elseif ($document['missing'])
                    <p class="mt-2 flex items-start gap-1.5 rounded-lg bg-danger-50 p-2.5 text-sm text-danger-700 ring-1 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-300 dark:ring-danger-500/30">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                        {{ __('panel.absence_motivation_view.document_missing') }}
                    </p>
                @elseif ($document['is_image'])
                    <a href="{{ $document['inline_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-3 block">
                        <img
                            src="{{ $document['inline_url'] }}"
                            alt="{{ __('panel.absence_motivation_view.document') }}"
                            class="max-h-96 w-auto max-w-full rounded-lg ring-1 ring-gray-950/10 dark:ring-white/10"
                        />
                    </a>
                @elseif ($document['is_pdf'])
                    <iframe
                        src="{{ $document['inline_url'] }}"
                        title="{{ __('panel.absence_motivation_view.document') }}"
                        class="mt-3 h-96 w-full rounded-lg ring-1 ring-gray-950/10 dark:ring-white/10"
                    ></iframe>
                @else
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('panel.absence_motivation_view.document_no_preview') }}
                    </p>
                @endif
            </section>

            {{-- Cronologia cererii. --}}
            <section aria-label="{{ __('panel.homework_correction_view.timeline') }}">
                <h2 class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    {{ __('panel.homework_correction_view.timeline') }}
                </h2>

                <ol class="mt-2 space-y-0">
                    @foreach ($timeline as $entry)
                        <li class="relative flex gap-3 pb-5 last:pb-0">
                            @unless ($loop->last)
                                <span class="absolute left-[5px] top-4 h-full w-px bg-gray-200 dark:bg-white/10" aria-hidden="true"></span>
                            @endunless
                            <span class="relative mt-1.5 h-[11px] w-[11px] shrink-0 rounded-full {{ $entry['color'] }}" aria-hidden="true"></span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $entry['label'] }}
                                    @if ($entry['actor'] !== null)
                                        <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $entry['actor'] }}</span>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ $entry['at'] }}</p>
                                @if ($entry['note'] !== null && $entry['note'] !== '')
                                    <p class="mt-1.5 max-w-prose whitespace-pre-line rounded-lg bg-gray-50 p-2.5 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $entry['note'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        {{-- Starea + termenul + contextul elevului. --}}
        <aside class="space-y-6">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ __('panel.fields.status') }}
                    </h2>
                    <span class="flex items-center gap-1.5">
                        @if ($record->is_exception)
                            <x-filament::badge color="warning">
                                {{ __('panel.tables.absence_motivations.type_exception') }}
                            </x-filament::badge>
                        @endif
                        <x-filament::badge :color="$record->status->color()">
                            {{ $record->status->getLabel() }}
                        </x-filament::badge>
                    </span>
                </div>

                @if ($pending)
                    <dl class="mt-3 flex items-baseline justify-between gap-3 text-sm">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.tables.absence_motivations.validation_deadline') }}</dt>
                        <dd class="text-right tabular-nums {{ $record->isOverdue() ? 'font-semibold text-danger-600 dark:text-danger-400' : 'text-gray-950 dark:text-white' }}">
                            {{ $record->validationDeadline()?->translatedFormat('d.m.Y') ?? '—' }}
                            @if ($record->isOverdue())
                                <span class="block text-xs font-medium">{{ __('panel.absence_motivation_view.overdue') }}</span>
                            @endif
                        </dd>
                    </dl>

                    @if ($this->canJudge())
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('panel.absence_motivation_view.pending_hint') }}
                        </p>
                    @elseif ($record->is_exception)
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('panel.absence_motivation_view.exception_only_hint') }}
                        </p>
                    @endif
                @endif
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('panel.fields.student') }}
                </h2>

                @if ($context === null)
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.common.dash') }}</p>
                @else
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.fields.student') }}</dt>
                            <dd class="text-right font-medium">
                                @if ($context['url'] !== null)
                                    <a href="{{ $context['url'] }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $context['name'] }}</a>
                                @else
                                    <span class="text-gray-950 dark:text-white">{{ $context['name'] }}</span>
                                @endif
                                @if ($context['archived'])
                                    <span class="block text-xs font-normal text-gray-400 dark:text-gray-500">{{ __('panel.absence_motivation_view.student_archived') }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.fields.class') }}</dt>
                            <dd class="text-right text-gray-950 dark:text-white">{{ $context['class'] ?? '—' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.absence_motivation_view.homeroom') }}</dt>
                            <dd class="text-right text-gray-950 dark:text-white">{{ $context['homeroom'] ?? '—' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.fields.requested_by') }}</dt>
                            <dd class="text-right text-gray-950 dark:text-white">{{ $record->requestedBy?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.fields.submitted_at') }}</dt>
                            <dd class="text-right tabular-nums text-gray-950 dark:text-white">{{ \App\Support\SchoolCalendar::local($record->created_at)?->translatedFormat('d.m.Y H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                @endif
            </div>

            <a
                href="{{ \App\Filament\Resources\AbsenceMotivations\AbsenceMotivationResource::getUrl() }}"
                class="inline-flex min-h-9 items-center gap-1 text-sm font-medium text-gray-500 hover:text-primary-600 hover:underline dark:text-gray-400 dark:hover:text-primary-400"
            >
                <x-filament::icon icon="heroicon-o-arrow-uturn-left" class="h-4 w-4" />
                {{ __('panel.absence_motivation_view.back') }}
            </a>
        </aside>
    </div>
</x-filament-panels::page>
