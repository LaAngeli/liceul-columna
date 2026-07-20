{{-- Fișa cererii de corecție de notă: valorile FAȚĂ ÎN FAȚĂ (actuală vs. propusă — modificarea
     dintr-o privire) → motivul integral → istoricul notei (corecții anterioare + jurnalul de
     audit) → cronologia cererii; în lateral, starea + contextul notei (elev / disciplină /
     profesor / tip) + contestația-sursă. Judecata stă în antet; respingerea cere motiv. --}}
<x-filament-panels::page>
    @php($record = $this->record)
    @php($context = $this->gradeContext())
    @php($timeline = $this->timeline())
    @php($prior = $this->priorCorrections())
    @php($auditTrail = $this->gradeAuditTrail())
    @php($contestationUrl = $this->contestationUrl())
    @php($approved = $record->status === \App\Enums\CorrectionStatus::Approved)

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Modificarea, dintr-o privire: valoarea actuală față de cea propusă. --}}
            <section
                class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                aria-label="{{ __('panel.tables.grade_corrections.change') }}"
            >
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-lg bg-gray-50 p-4 text-center dark:bg-white/5">
                        <p class="text-[0.65rem] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            {{ $approved ? __('panel.homework_correction_view.before') : __('panel.grade_correction_view.current_value') }}
                        </p>
                        <p class="mt-1 text-3xl font-bold tabular-nums text-gray-500 dark:text-gray-400">
                            {{ $this->displayValue($record->old_value, $record->old_calificativ) }}
                        </p>
                    </div>
                    <div class="relative rounded-lg bg-primary-50 p-4 text-center ring-1 ring-primary-600/20 dark:bg-primary-500/10 dark:ring-primary-500/30">
                        <span class="absolute -left-4 top-1/2 hidden -translate-y-1/2 text-gray-300 sm:block dark:text-gray-600" aria-hidden="true">
                            <x-filament::icon icon="heroicon-o-arrow-right" class="h-5 w-5" />
                        </span>
                        <p class="text-[0.65rem] font-medium uppercase tracking-wide text-primary-600 dark:text-primary-400">
                            {{ $approved ? __('panel.grade_correction_view.applied_value') : __('panel.grade_correction_view.proposed_value') }}
                        </p>
                        <p class="mt-1 text-3xl font-bold tabular-nums text-gray-950 dark:text-white">
                            {{ $this->displayValue($record->new_value, $record->new_calificativ) }}
                        </p>
                    </div>
                </div>

                <h2 class="mt-5 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    {{ __('panel.fields.reason') }}
                </h2>
                <p class="mt-1.5 max-w-prose whitespace-pre-line text-sm leading-relaxed text-gray-950 dark:text-white">{{ $record->reason }}</p>

                <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-400 dark:text-gray-500">
                    <span>{{ __('panel.homework_correction_view.by_author', ['name' => $record->requestedBy?->name ?? '—']) }}</span>
                    <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">·</span>
                    <span class="tabular-nums">{{ $record->created_at->translatedFormat('d.m.Y H:i') }}</span>
                </div>
            </section>

            {{-- Istoricul notei: corecțiile anterioare + jurnalul de modificări (audit). --}}
            <section aria-label="{{ __('panel.grade_correction_view.grade_history') }}">
                <h2 class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    {{ __('panel.grade_correction_view.grade_history') }}
                </h2>

                @if ($prior === [] && $auditTrail === [])
                    <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">
                        {{ __('panel.grade_correction_view.no_history') }}
                    </p>
                @else
                    @if ($prior !== [])
                        <div class="mt-2 space-y-2">
                            @foreach ($prior as $entry)
                                <a
                                    href="{{ $entry['url'] }}"
                                    class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl bg-white px-4 py-2.5 text-sm shadow-sm ring-1 ring-gray-950/5 transition duration-75 hover:ring-2 hover:ring-primary-600 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-500"
                                >
                                    <span class="font-medium tabular-nums text-gray-950 dark:text-white">{{ $entry['change'] }}</span>
                                    <x-filament::badge :color="$entry['status_color']" size="sm">{{ $entry['status_label'] }}</x-filament::badge>
                                    <span class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ $entry['at'] }}@if ($entry['reviewer'] !== null) · {{ $entry['reviewer'] }}@endif
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($auditTrail !== [])
                        <h3 class="mt-4 text-[0.65rem] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            {{ __('panel.grade_correction_view.audit_trail') }}
                        </h3>
                        <ul class="mt-1.5 space-y-1.5">
                            @foreach ($auditTrail as $entry)
                                <li class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 text-sm">
                                    <span class="text-gray-950 dark:text-white">{{ $entry['summary'] }}</span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ $entry['at'] }}@if ($entry['actor'] !== null) · {{ $entry['actor'] }}@endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
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

        {{-- Starea + contextul notei + contestația-sursă. --}}
        <aside class="space-y-6">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ __('panel.fields.status') }}
                    </h2>
                    <x-filament::badge :color="$record->status->color()">
                        {{ $record->status->getLabel() }}
                    </x-filament::badge>
                </div>

                @if ($record->isPending() && $this->canJudge())
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('panel.homework_correction_view.pending_hint') }}
                    </p>
                @endif
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('panel.grade_correction_view.grade') }}
                </h2>

                @if ($context === null)
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.common.dash') }}</p>
                @else
                    @if ($context['annulled'])
                        <p class="mt-3 flex items-start gap-1.5 rounded-lg bg-warning-50 p-2.5 text-xs text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-300 dark:ring-warning-500/30">
                            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            {{ __('panel.grade_correction_view.grade_annulled') }}
                        </p>
                    @endif

                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.fields.student') }}</dt>
                            <dd class="text-right font-medium">
                                @if ($context['student_url'] !== null)
                                    <a href="{{ $context['student_url'] }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $context['student'] ?? '—' }}</a>
                                @else
                                    <span class="text-gray-950 dark:text-white">{{ $context['student'] ?? '—' }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.fields.subject') }}</dt>
                            <dd class="text-right font-medium text-gray-950 dark:text-white">{{ $context['subject'] ?? '—' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.grade_correction_view.graded_by') }}</dt>
                            <dd class="text-right text-gray-950 dark:text-white">{{ $context['teacher'] ?? '—' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.fields.date') }}</dt>
                            <dd class="text-right tabular-nums text-gray-950 dark:text-white">{{ $context['graded_on'] ?? '—' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.grade_correction_view.evaluation_type') }}</dt>
                            <dd class="text-right text-gray-950 dark:text-white">{{ $context['evaluation_type'] ?? '—' }}</dd>
                        </div>
                    </dl>
                @endif
            </div>

            @if ($contestationUrl !== null)
                <a
                    href="{{ $contestationUrl }}"
                    class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-info-500/40 transition duration-75 hover:ring-2 hover:ring-info-500 dark:bg-gray-900"
                >
                    <x-filament::icon icon="heroicon-o-scale" class="h-6 w-6 shrink-0 text-info-500" />
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-gray-950 dark:text-white">
                            {{ __('panel.grade_correction_view.from_contestation') }}
                        </span>
                        <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                            {{ __('panel.grade_correction_view.from_contestation_hint') }}
                        </span>
                    </span>
                </a>
            @endif

            <a
                href="{{ \App\Filament\Resources\GradeCorrections\GradeCorrectionResource::getUrl() }}"
                class="inline-flex min-h-9 items-center gap-1 text-sm font-medium text-gray-500 hover:text-primary-600 hover:underline dark:text-gray-400 dark:hover:text-primary-400"
            >
                <x-filament::icon icon="heroicon-o-arrow-uturn-left" class="h-4 w-4" />
                {{ __('panel.homework_correction_view.back') }}
            </a>
        </aside>
    </div>
</x-filament-panels::page>
