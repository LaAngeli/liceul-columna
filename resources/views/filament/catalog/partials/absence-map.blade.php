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
    // Pistele coloanei Total, în ordinea legendei. Definite o dată, nu pe fiecare rând: doar
    // numărul diferă de la elev la elev, restul (semn, culoare, etichetă citită de cititorul de
    // ecran) e același peste tot.
    $totalTracks = [
        ['key' => 'motivated', 'icon' => '✓', 'tone' => 'text-green-600 dark:text-green-400', 'label' => \App\Enums\AbsenceStatus::Motivated->label()],
        ['key' => 'unmotivated', 'icon' => '✗', 'tone' => 'text-red-600 dark:text-red-400', 'label' => \App\Enums\AbsenceStatus::Unmotivated->label()],
        ['key' => 'pending', 'icon' => '?', 'tone' => 'text-amber-600 dark:text-amber-400', 'label' => \App\Enums\AbsenceStatus::Pending->label()],
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

    @if ($map['days'] === [])
        <div class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">
            {{ __('absence_map.empty_period') }}
        </div>
    @else
        {{-- ZONA ZILELOR derulează orizontal între cele două coloane ANCORATE (numele — sticky
             stânga, totalurile — sticky dreapta). Când zilele nu încap, la capetele zonei apar
             săgeți de carusel (cerința beneficiarului, 04.08.2026); pasul de derulare = fereastra
             vizibilă a zilelor, deci o apăsare = o „pagină" de zile. Săgețile stau DOAR peste zona
             zilelor: offseturile lor vin din lățimile reale ale coloanelor ancorate, măsurate la
             sync() — nu din numere fixate în cod. --}}
        <div
            x-data="{
                canLeft: false,
                canRight: false,
                nameW: 0,
                totalW: 0,
                sync() {
                    const el = this.$refs.scroller;

                    if (! el) {
                        return;
                    }

                    this.canLeft = el.scrollLeft > 1;
                    this.canRight = el.scrollLeft + el.clientWidth < el.scrollWidth - 1;
                    this.nameW = this.$refs.nameTh?.offsetWidth ?? 0;
                    this.totalW = this.$refs.totalTh?.offsetWidth ?? 0;
                },
                nudge(direction) {
                    const el = this.$refs.scroller;
                    const step = Math.max(120, el.clientWidth - this.nameW - this.totalW - 48);

                    el.scrollBy({ left: direction * step, behavior: 'smooth' });
                },
            }"
            {{-- Sync dublu la pornire: browserul poate restaura scrollLeft DUPĂ primul tick (fără
                 eveniment de scroll), iar starea săgeților ar rămâne cea din momentul greșit. --}}
            x-init="$nextTick(() => sync()); setTimeout(() => sync(), 300)"
            x-on:resize.window.debounce.150ms="sync()"
            class="relative"
        >
            <div x-ref="scroller" x-on:scroll.passive.debounce.50ms="sync()" class="overflow-x-auto">
            {{-- Lățimea urmează CONȚINUTUL (min-w-max, fără w-full): un tabel întins la container ar
                 avea surplus de împărțit, iar el se scurgea în ultima coloană — de acolo golul dintre
                 separatorul Total și cifre. Așa, blocul de totaluri stă lipit de grila zilelor.
                 ⚠️ `w-full` pe o CELULĂ nu e alternativă: cu `min-w-max`, procentul intră în ciclu cu
                 dimensionarea max-content și tabelul sare la ~10⁶ px (măsurat, 04.08.2026). --}}
            <table class="min-w-max border-separate border-spacing-0 text-sm">
                <thead>
                    <tr>
                        <th x-ref="nameTh" class="sticky left-0 z-10 border-b border-gray-200 bg-white px-4 py-2 text-start text-xs font-semibold text-gray-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-400">
                            {{ __('absence_map.student') }}
                        </th>
                        @foreach ($map['days'] as $day)
                            <th class="border-b border-gray-200 px-2 py-2 text-center text-xs font-semibold text-gray-500 dark:border-white/10 dark:text-gray-400"
                                title="{{ trans_choice('absence_map.day_count', $day['count'], ['count' => $day['count']]) }}">
                                <span class="block tabular-nums">{{ $day['day'] }}</span>
                                <span class="block text-[10px] font-normal text-gray-400 dark:text-gray-500">{{ $day['weekday'] }}</span>
                            </th>
                        @endforeach
                        {{-- Antetul acoperă TOATE cele 4 piste. Nu-i mai fixăm lățimea: coloana e
                             deja exact cât grupul din celule, iar `block text-center` îl centrează
                             pe toată lățimea ei — fără un număr magic de ținut sincron cu suma
                             pistelor. Padding-ul trebuie să fie ACELAȘI ca la celule, altfel
                             centrarea se decalează. --}}
                        {{-- ANCORAT la dreapta (sticky), ca perechea lui din stânga: totalurile
                             rămân vizibile oricâte zile ar derula dedesubt. Fundal opac obligatoriu
                             — altfel coloanele zilelor s-ar vedea prin el. --}}
                        <th x-ref="totalTh" class="sticky right-0 z-10 border-b border-s border-gray-200 bg-white ps-2 pe-4 py-2 dark:border-white/10 dark:bg-gray-900">
                            <span class="block text-center text-[0.8625rem] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                {{ __('absence_map.totals') }}
                            </span>
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
                            <td class="sticky right-0 z-10 whitespace-nowrap border-b border-s border-gray-100 border-s-gray-200 bg-white ps-2 pe-4 py-2 text-[0.8625rem] tabular-nums group-hover:bg-gray-50 dark:border-white/5 dark:border-s-white/10 dark:bg-gray-900 dark:group-hover:bg-white/5">
                                <span class="flex items-center justify-end gap-1">
                                    <span class="w-6 text-start font-semibold text-gray-950 dark:text-white">{{ $row['totals']['total'] }}</span>

                                    @foreach ($totalTracks as $track)
                                        {{-- Pistă de lățime fixă, randată și când e goală: locul liber
                                             păstrează alinierea pe verticală. Cifra și semnul stau la
                                             o distanță vizibilă (gap-1.5), nu lipite. --}}
                                        <span class="flex w-10 items-center justify-end gap-1.5 {{ $track['tone'] }}">
                                            @if ($row['totals'][$track['key']] > 0)
                                                <span>{{ $row['totals'][$track['key']] }}</span>
                                                <span aria-hidden="true">{{ $track['icon'] }}</span>
                                                <span class="sr-only">{{ $track['label'] }}</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            {{-- SĂGEȚILE CARUSELULUI — doar în interiorul zonei zilelor (offseturile = lățimile
                 coloanelor ancorate, măsurate la sync) și doar când există mai multe zile decât
                 încap: `canLeft` / `canRight` se sting singure la capete, deci pe un tabel care
                 încape nu apar deloc.

                 De ce ȘINE sticky, nu butoane absolute la top-1/2: pe o clasă mare tabelul trece
                 de 1300px, iar mijlocul LUI cade sub marginea ecranului — săgețile existau dar nu
                 se vedeau (măsurat, 04.08.2026). Șina acoperă toată înălțimea zonei, iar butonul
                 sticky din ea rămâne la mijlocul FERESTREI cât timp harta e pe ecran. --}}
            <div class="pointer-events-none absolute inset-y-0 z-20" x-bind:style="'left: ' + (nameW + 8) + 'px'">
                <button
                    type="button"
                    x-cloak
                    x-show="canLeft"
                    x-transition.opacity
                    x-on:click="nudge(-1)"
                    aria-label="{{ __('absence_map.scroll_left') }}"
                    title="{{ __('absence_map.scroll_left') }}"
                    class="pointer-events-auto sticky top-[45vh] flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-600 shadow-md ring-1 ring-gray-950/10 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/20 dark:hover:bg-gray-700"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" width="16" height="16" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
            </div>

            <div class="pointer-events-none absolute inset-y-0 z-20" x-bind:style="'right: ' + (totalW + 8) + 'px'">
                <button
                    type="button"
                    x-cloak
                    x-show="canRight"
                    x-transition.opacity
                    x-on:click="nudge(1)"
                    aria-label="{{ __('absence_map.scroll_right') }}"
                    title="{{ __('absence_map.scroll_right') }}"
                    class="pointer-events-auto sticky top-[45vh] flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-600 shadow-md ring-1 ring-gray-950/10 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/20 dark:hover:bg-gray-700"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" width="16" height="16" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>
        </div>
    @endif
</section>
