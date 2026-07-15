{{-- Cardul unui elev în navigatorul Foii matricole: click → foaia lui. --}}
<button
    type="button"
    wire:click="openStudent({{ $card['id'] }})"
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
