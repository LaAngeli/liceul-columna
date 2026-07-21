{{--
    Banda de IDENTITATE din fișa elevului (secțiunea „Date personale"): avatar cu inițiale pe
    navy-ul de brand + numele proeminent + reperele de registru (clasa, nr. matricol) ca chip-uri.
    Înlocuiește rândurile seci etichetă/valoare pentru identitate — restul câmpurilor rămân în
    grila de sub bandă. Read-only, fără linkuri (suntem deja pe fișă).
--}}
@php
    /** @var \App\Models\Student $record */
    $record = $getRecord();
    $class = $record->currentSchoolClass();

    $initials = collect([$record->last_name, $record->first_name])
        ->filter()
        ->map(fn (string $part): string => mb_strtoupper(mb_substr(trim($part), 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<div class="flex items-center gap-4">
    <span
        class="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary-600 text-lg font-semibold tracking-wide text-white ring-4 ring-primary-600/15 select-none dark:ring-primary-400/20"
        aria-hidden="true"
    >
        {{ $initials !== '' ? $initials : '·' }}
    </span>

    <span class="min-w-0">
        <span class="block truncate text-lg font-bold text-gray-950 dark:text-white">
            {{ $record->full_name }}
        </span>

        <span class="mt-1.5 flex flex-wrap items-center gap-1.5">
            @if ($class !== null)
                <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/30">
                    <x-filament::icon icon="heroicon-o-academic-cap" class="h-3.5 w-3.5" />
                    {{ __('panel.fields.class') }} {{ trim($class->name.' '.($class->section ?? '')) }}
                </span>
            @endif

            <span class="inline-flex items-center gap-1 rounded-full bg-gray-50 px-2.5 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                <x-filament::icon icon="heroicon-o-hashtag" class="h-3.5 w-3.5" />
                {{ __('panel.fields.register_number') }}: {{ $record->register_number ?? __('panel.common.dash') }}
            </span>
        </span>
    </span>
</div>
