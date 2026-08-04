{{-- HARTA ABSENȚELOR (elevi × zile) — vederea implicită a secțiunii Absențe în context de clasă.
     Datele vin din ListAbsences::absenceMap(); interogarea e cea a tabelului, doar așezată altfel.
     Statutul se fixează direct din pastilă (dirigintele clasei / administrația); ceilalți văd
     pastila ca link spre fișă. --}}
@php
    $map = $this->absenceMap();
    $statusChoices = [
        \App\Enums\AbsenceStatus::Motivated,
        \App\Enums\AbsenceStatus::Unmotivated,
        \App\Enums\AbsenceStatus::Pending,
    ];
    $chipPalette = [
        'warning' => 'bg-amber-100 text-amber-800 ring-amber-600/30 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30',
        'success' => 'bg-green-100 text-green-800 ring-green-600/30 dark:bg-green-400/10 dark:text-green-300 dark:ring-green-400/30',
        'danger' => 'bg-red-100 text-red-800 ring-red-600/30 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/30',
    ];
@endphp

<section class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    {{-- Antet: titlu + legendă + comutatorul spre listă --}}
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-gray-200 px-4 py-3 dark:border-white/10">
        <div class="me-auto">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('absence_map.title') }}
            </h3>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                {{ $map['canStatus'] ? __('absence_map.hint_status') : __('absence_map.hint_read') }}
            </p>
        </div>

        <div class="flex items-center gap-3 text-xs text-gray-600 dark:text-gray-300">
            @foreach ($statusChoices as $status)
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-full {{ match ($status->color()) {
                        'success' => 'bg-green-500',
                        'danger' => 'bg-red-500',
                        default => 'bg-amber-400',
                    } }}"></span>
                    {{ $status->label() }}
                </span>
            @endforeach
        </div>

        <button
            type="button"
            wire:click="setAbsenceView('lista')"
            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-700 ring-1 ring-gray-950/10 transition hover:bg-gray-50 dark:text-gray-200 dark:ring-white/20 dark:hover:bg-white/5"
        >
            <x-filament::icon icon="heroicon-o-list-bullet" class="h-4 w-4" />
            {{ __('absence_map.switch_to_list') }}
        </button>
    </div>

    @if ($map['overflow'] !== null)
        {{-- Prea multe zile pentru coloane: aceeași invitație ca la catalogul de note. --}}
        <div class="px-4 py-6 text-sm text-gray-600 dark:text-gray-300">
            {{ __('absence_map.overflow', ['days' => $map['overflow']]) }}
        </div>
    @elseif ($map['days'] === [])
        <div class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">
            {{ __('absence_map.empty_period') }}
        </div>
    @else
        {{-- Tabelul derulează ORIZONTAL în containerul lui; numele elevului rămâne lipit la stânga. --}}
        <div class="overflow-x-auto">
            <table class="w-full min-w-max border-separate border-spacing-0 text-sm">
                <thead>
                    <tr>
                        <th class="sticky left-0 z-10 border-b border-gray-200 bg-white px-4 py-2 text-start text-xs font-semibold text-gray-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-400">
                            {{ __('absence_map.student') }}
                        </th>
                        @foreach ($map['days'] as $day)
                            <th class="border-b border-gray-200 px-2 py-2 text-center text-xs font-semibold text-gray-500 dark:border-white/10 dark:text-gray-400"
                                title="{{ trans_choice('absence_map.day_count', $day['count'], ['count' => $day['count']]) }}">
                                <span class="block tabular-nums">{{ $day['day'] }}</span>
                                <span class="block text-[10px] font-normal text-gray-400 dark:text-gray-500">{{ $day['weekday'] }}</span>
                            </th>
                        @endforeach
                        <th class="border-b border-gray-200 px-4 py-2 text-end text-xs font-semibold text-gray-500 dark:border-white/10 dark:text-gray-400">
                            {{ __('absence_map.totals') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($map['rows'] as $row)
                        <tr class="group">
                            <td class="sticky left-0 z-10 whitespace-nowrap border-b border-gray-100 bg-white px-4 py-2 font-medium text-gray-950 group-hover:bg-gray-50 dark:border-white/5 dark:bg-gray-900 dark:text-white dark:group-hover:bg-white/5">
                                {{ $row['student']->last_name }} {{ $row['student']->first_name }}
                            </td>

                            @foreach ($map['days'] as $day)
                                <td class="border-b border-gray-100 px-1.5 py-1.5 text-center align-middle dark:border-white/5">
                                    @foreach ($row['cells'][$day['iso']] ?? [] as $chip)
                                        @if ($map['canStatus'])
                                            {{-- Pastila deschide alegerea statutului pe loc — fără modal. --}}
                                            <div x-data="{ open: false }" class="relative inline-block">
                                                {{-- Eticheta vizibilă e doar marcajul „A" — numele accesibil poartă
                                                     disciplina și statutul, ca hover-ul. --}}
                                                <button
                                                    type="button"
                                                    x-on:click="open = ! open"
                                                    x-on:click.outside="open = false"
                                                    x-on:keydown.escape.window="open = false"
                                                    title="{{ $chip['title'] }}"
                                                    aria-label="{{ $chip['title'] }}"
                                                    class="m-0.5 inline-flex min-h-7 items-center rounded-md px-2 py-0.5 text-xs font-semibold ring-1 transition hover:brightness-95 {{ $chipPalette[$chip['color']] ?? $chipPalette['warning'] }}"
                                                >
                                                    {{ $chip['label'] }}
                                                </button>

                                                <div
                                                    x-cloak
                                                    x-show="open"
                                                    x-transition.opacity.duration.100ms
                                                    class="absolute start-1/2 z-20 mt-1 w-44 -translate-x-1/2 rounded-lg bg-white p-1 text-start shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-800 dark:ring-white/20"
                                                >
                                                    @foreach ($statusChoices as $status)
                                                        <button
                                                            type="button"
                                                            x-on:click="open = false"
                                                            wire:click="setAbsenceMapStatus({{ $chip['id'] }}, '{{ $status->value }}')"
                                                            @disabled($chip['status'] === $status->value)
                                                            class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-xs text-gray-700 transition hover:bg-gray-100 disabled:opacity-40 dark:text-gray-200 dark:hover:bg-white/10"
                                                        >
                                                            <x-filament::icon :icon="$status->icon()" class="h-4 w-4" />
                                                            {{ $status->label() }}
                                                        </button>
                                                    @endforeach

                                                    <a
                                                        href="{{ $chip['url'] }}"
                                                        class="mt-1 flex w-full items-center gap-2 rounded-md border-t border-gray-100 px-2.5 py-1.5 text-xs text-gray-500 transition hover:bg-gray-100 dark:border-white/10 dark:text-gray-400 dark:hover:bg-white/10"
                                                    >
                                                        <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="h-4 w-4" />
                                                        {{ __('absence_map.open_record') }}
                                                    </a>
                                                </div>
                                            </div>
                                        @else
                                            {{-- Fără drept de statut: pastila duce la fișă (unde politica decide mai departe). --}}
                                            <a
                                                href="{{ $chip['url'] }}"
                                                title="{{ $chip['title'] }}"
                                                aria-label="{{ $chip['title'] }}"
                                                class="m-0.5 inline-flex min-h-7 items-center rounded-md px-2 py-0.5 text-xs font-semibold ring-1 transition hover:brightness-95 {{ $chipPalette[$chip['color']] ?? $chipPalette['warning'] }}"
                                            >
                                                {{ $chip['label'] }}
                                            </a>
                                        @endif
                                    @endforeach
                                </td>
                            @endforeach

                            {{-- Totaluri per elev: se vede dintr-o privire cine acumulează.
                                 PISTE VERTICALE FIXE (raport beneficiar, 04.08.2026): fiecare
                                 categorie — total / motivate / nemotivate / fără statut — are
                                 coloana ei cu lățime fixă, aliniată pe toate rândurile. O
                                 categorie la zero își lasă locul GOL, nu îl cedează vecinei:
                                 altfel „2✓" aluneca în pista lui „✗" și cifrele nu se mai puteau
                                 compara pe verticală. --}}
                            <td class="whitespace-nowrap border-b border-gray-100 px-4 py-2 text-xs tabular-nums dark:border-white/5">
                                <span class="flex items-center justify-end gap-1">
                                    <span class="w-7 text-end font-semibold text-gray-950 dark:text-white">{{ $row['totals']['total'] }}</span>
                                    <span class="w-9 text-end text-green-600 dark:text-green-400">
                                        @if ($row['totals']['motivated'] > 0){{ $row['totals']['motivated'] }}<span aria-hidden="true">✓</span>@endif
                                    </span>
                                    <span class="w-9 text-end text-red-600 dark:text-red-400">
                                        @if ($row['totals']['unmotivated'] > 0){{ $row['totals']['unmotivated'] }}<span aria-hidden="true">✗</span>@endif
                                    </span>
                                    <span class="w-9 text-end text-amber-600 dark:text-amber-400">
                                        @if ($row['totals']['pending'] > 0){{ $row['totals']['pending'] }}<span aria-hidden="true">?</span>@endif
                                    </span>
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
