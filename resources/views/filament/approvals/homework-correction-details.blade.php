{{-- Fișa cererii de corecție a temei: motivul INTEGRAL → propunerea vechi → nou pe fiecare câmp →
     cronologia procesului; în lateral, starea + contextul temei vizate (cu conținutul ei curent).
     Judecata (Aprobă / Respinge cu motiv obligatoriu) stă în antetul paginii — decizia se ia aici,
     cu tot contextul în față, nu din listă. --}}
<x-filament-panels::page>
    @php($record = $this->record)
    @php($homework = $record->homeworkAssignment)
    @php($changes = $this->proposedChanges())
    @php($timeline = $this->timeline())

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Motivul solicitării, fără trunchiere. --}}
            <section
                class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                aria-label="{{ __('panel.fields.reason') }}"
            >
                <h2 class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    {{ __('panel.fields.reason') }}
                </h2>
                <p class="mt-2 max-w-prose whitespace-pre-line text-sm leading-relaxed text-gray-950 dark:text-white">{{ $record->reason }}</p>

                <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-400 dark:text-gray-500">
                    <span>{{ __('panel.homework_correction_view.by_author', ['name' => $record->requestedBy?->name ?? '—']) }}</span>
                    <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">·</span>
                    <span class="tabular-nums">{{ $record->created_at->translatedFormat('d.m.Y H:i') }}</span>
                </div>
            </section>

            {{-- Propunerea: vechi → nou, câmp cu câmp. --}}
            <section aria-label="{{ __('panel.homework_correction_view.changes') }}">
                <h2 class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    {{ __('panel.homework_correction_view.changes') }}
                </h2>

                <div class="mt-2 space-y-3">
                    @forelse ($changes as $change)
                        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $change['label'] }}</p>

                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                                    <p class="text-[0.65rem] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                        {{ $record->status === \App\Enums\CorrectionStatus::Approved
                                            ? __('panel.homework_correction_view.before')
                                            : __('panel.homework_correction_view.current') }}
                                    </p>
                                    <p class="mt-1 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $change['old'] ?? '—' }}</p>
                                </div>
                                <div class="rounded-lg bg-primary-50 p-3 ring-1 ring-primary-600/20 dark:bg-primary-500/10 dark:ring-primary-500/30">
                                    <p class="text-[0.65rem] font-medium uppercase tracking-wide text-primary-600 dark:text-primary-400">
                                        {{ $record->status === \App\Enums\CorrectionStatus::Approved
                                            ? __('panel.homework_correction_view.applied')
                                            : __('panel.homework_correction_view.proposed') }}
                                    </p>
                                    <p class="mt-1 whitespace-pre-line text-sm font-medium text-gray-950 dark:text-white">{{ $change['new'] }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('panel.common.dash') }}</p>
                    @endforelse
                </div>
            </section>

            {{-- Cronologia procesului. --}}
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

        {{-- Starea + tema vizată. --}}
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
                    {{ __('panel.homework_correction_view.homework') }}
                </h2>

                @if ($homework === null)
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('panel.common.dash') }}</p>
                @else
                    @if ($homework->trashed())
                        <p class="mt-3 flex items-start gap-1.5 rounded-lg bg-warning-50 p-2.5 text-xs text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-300 dark:ring-warning-500/30">
                            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            {{ __('panel.homework_correction_view.homework_deleted') }}
                        </p>
                    @endif

                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.fields.subject') }}</dt>
                            <dd class="text-right font-medium text-gray-950 dark:text-white">{{ \App\Support\ContentTranslator::subject((string) $homework->subject_name) }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.fields.class') }}</dt>
                            <dd class="text-right font-medium tabular-nums text-gray-950 dark:text-white">{{ trim($homework->grade_level.' '.($homework->section ?? '')) }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.forms.homework.assigned_on') }}</dt>
                            <dd class="text-right tabular-nums text-gray-950 dark:text-white">{{ $homework->assigned_on->translatedFormat('d.m.Y') }}</dd>
                        </div>
                        @if ($homework->due_on !== null)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.forms.homework.due_on') }}</dt>
                                <dd class="text-right tabular-nums text-gray-950 dark:text-white">{{ $homework->due_on->translatedFormat('d.m.Y') }}</dd>
                            </div>
                        @endif
                        @if ($homework->author_name !== null)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ __('panel.homework_correction_view.homework_author') }}</dt>
                                <dd class="text-right text-gray-950 dark:text-white">{{ $homework->author_name }}</dd>
                            </div>
                        @endif
                    </dl>

                    <h3 class="mt-4 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ __('panel.homework_correction_view.current_content') }}
                    </h3>
                    <dl class="mt-2 space-y-2.5 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('panel.forms.homework.topic') }}</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-gray-950 dark:text-white">{{ $homework->topic ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('panel.forms.homework.required_task') }}</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-gray-950 dark:text-white">{{ $homework->required_task ?? '—' }}</dd>
                        </div>
                        @if ($homework->optional_task !== null)
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('panel.forms.homework.optional_task') }}</dt>
                                <dd class="mt-0.5 whitespace-pre-line text-gray-950 dark:text-white">{{ $homework->optional_task }}</dd>
                            </div>
                        @endif
                    </dl>
                @endif
            </div>

            <a
                href="{{ \App\Filament\Resources\HomeworkCorrections\HomeworkCorrectionResource::getUrl() }}"
                class="inline-flex min-h-9 items-center gap-1 text-sm font-medium text-gray-500 hover:text-primary-600 hover:underline dark:text-gray-400 dark:hover:text-primary-400"
            >
                <x-filament::icon icon="heroicon-o-arrow-uturn-left" class="h-4 w-4" />
                {{ __('panel.homework_correction_view.back') }}
            </a>
        </aside>
    </div>
</x-filament-panels::page>
