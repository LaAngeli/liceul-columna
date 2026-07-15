{{-- Foaie matricolă: navigator cu carduri — clase → elevii clasei → foaia matricolă a elevului
     ca DOCUMENT (trepte romane + ciclu, disciplină × Sem. I / Sem. II / Media anuală, calificative,
     media anuală a disciplinelor afișate). Arhiva (toți elevii, căutabili) = doar administrația. --}}
<x-filament-panels::page>
    @php($student = $this->activeStudent())

    @if ($student !== null)
        {{-- ── Foaia matricolă a elevului ─────────────────────────────────────────────── --}}
        <div class="space-y-6">
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::icon-button
                    icon="heroicon-o-arrow-uturn-left"
                    color="gray"
                    wire:click="leaveStudent"
                    :label="__('panel.catalog_nav.back')"
                    :tooltip="__('panel.catalog_nav.back')"
                />

                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ __('panel.resources.academic_records.label') }}
                    </p>
                    <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $student->full_name }}
                    </h2>
                </div>
            </div>

            @php($summary = $this->transcriptSummary())

            @if (count($summary) > 0)
                <p class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                    @foreach ($summary as $item)
                        <span>{{ $item }}</span>
                    @endforeach
                </p>
            @endif

            @php($levels = $this->transcriptLevels())

            @if (count($levels) === 0)
                <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-rectangle-stack" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.empty.academic_records.heading') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.empty.academic_records.description') }}</p>
                </div>
            @else
                @foreach ($levels as $level)
                    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-gray-950/5 px-4 py-3 dark:border-white/10">
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ __('panel.catalog_nav.transcript_level', ['grade' => $level['roman']]) }}
                                <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $level['cycle'] }}</span>
                            </h3>

                            @if ($level['average'] !== null)
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('panel.catalog_nav.transcript_avg', ['value' => $level['average']]) }}
                                </p>
                            @endif
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-start text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                        <th class="px-4 py-2 text-start font-medium">{{ __('panel.fields.subject') }}</th>
                                        <th class="w-28 px-4 py-2 text-end font-medium">{{ __('enums.academic_record_period.1') }}</th>
                                        <th class="w-28 px-4 py-2 text-end font-medium">{{ __('enums.academic_record_period.2') }}</th>
                                        <th class="w-28 px-4 py-2 text-end font-medium">{{ __('enums.academic_record_period.3') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                                    @foreach ($level['rows'] as $row)
                                        <tr>
                                            <td class="px-4 py-2 text-gray-950 dark:text-white">{{ $row['subject'] }}</td>
                                            <td class="px-4 py-2 text-end tabular-nums text-gray-700 dark:text-gray-200">{{ $row['sem1'] ?? __('panel.common.dash') }}</td>
                                            <td class="px-4 py-2 text-end tabular-nums text-gray-700 dark:text-gray-200">{{ $row['sem2'] ?? __('panel.common.dash') }}</td>
                                            <td class="px-4 py-2 text-end font-medium tabular-nums text-gray-950 dark:text-white">{{ $row['annual'] ?? __('panel.common.dash') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    @elseif ($this->isArchiveMode())
        {{-- ── Arhiva: toți elevii cu foaie matricolă (căutabili) ─────────────────────── --}}
        <div class="space-y-6">
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::icon-button
                    icon="heroicon-o-arrow-uturn-left"
                    color="gray"
                    wire:click="leaveArchive"
                    :label="__('panel.catalog_nav.back')"
                    :tooltip="__('panel.catalog_nav.back')"
                />

                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ __('panel.resources.academic_records.label') }}
                    </p>
                    <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                        {{ __('panel.catalog_nav.students_archive') }}
                    </h2>
                </div>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('panel.catalog_nav.records_archive_hint') }}
            </p>

            <div class="max-w-xs">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.400ms="archiveSearch"
                        :placeholder="__('panel.catalog_nav.search_students')"
                    />
                </x-filament::input.wrapper>
            </div>

            @php($cards = $this->archiveStudentCards())

            @if (count($cards) === 0)
                <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-rectangle-stack" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.catalog_nav.empty_title') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.catalog_nav.empty_description') }}</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($cards as $card)
                        @include('filament.catalog.partials.transcript-student-card', ['card' => $card])
                    @endforeach
                </div>
            @endif
        </div>
    @elseif ($this->activeClass() !== null)
        {{-- ── Elevii clasei active ───────────────────────────────────────────────────── --}}
        @php($class = $this->activeClass())

        <div class="space-y-6">
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::icon-button
                    icon="heroicon-o-arrow-uturn-left"
                    color="gray"
                    wire:click="leaveClass"
                    :label="__('panel.catalog_nav.back')"
                    :tooltip="__('panel.catalog_nav.back')"
                />

                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ __('panel.fields.class') }}
                    </p>
                    <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                        {{ trim($class->name.' '.($class->section ?? '')) }}
                        @if ($class->homeroomTeacher !== null)
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">· {{ $class->homeroomTeacher->full_name }}</span>
                        @endif
                    </h2>
                </div>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('panel.catalog_nav.records_class_hint') }}
            </p>

            @php($cards = $this->studentCards())

            @if (count($cards) === 0)
                <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-rectangle-stack" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.catalog_nav.empty_title') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.catalog_nav.empty_description') }}</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($cards as $card)
                        @include('filament.catalog.partials.transcript-student-card', ['card' => $card])
                    @endforeach
                </div>
            @endif
        </div>
    @else
        {{-- ── Aterizare: cardurile claselor ──────────────────────────────────────────── --}}
        <div class="space-y-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $this->recordsHint() }}
            </p>

            @php($cards = $this->classCards())

            @if (count($cards) === 0)
                <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-rectangle-stack" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.catalog_nav.empty_title') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.catalog_nav.empty_description') }}</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($cards as $card)
                        <button
                            type="button"
                            wire:click="openClass({{ $card['id'] }})"
                            wire:loading.attr="disabled"
                            class="group rounded-xl bg-white p-4 text-start shadow-sm ring-1 ring-gray-950/5 transition duration-75 hover:ring-2 hover:ring-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 disabled:pointer-events-none disabled:opacity-70 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-500"
                        >
                            <span class="block truncate text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                {{ $card['title'] }}
                            </span>

                            @if ($card['subtitle'] !== null)
                                <span class="mt-0.5 block truncate text-sm text-gray-500 dark:text-gray-400">
                                    {{ $card['subtitle'] }}
                                </span>
                            @endif

                            <span class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                @foreach ($card['stats'] as $stat)
                                    <span>{{ $stat }}</span>
                                @endforeach
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
