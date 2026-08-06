{{-- HARTA NOTELOR (elevi × zile) — vederea implicită a secțiunii Note în context de clasă.
     Datele vin din ListGrades::gradeMap(); interogarea e cea a tabelului, restrânsă la notele
     ACTIVE (anulatele trăiesc în listă, gri, cu motiv). Pastila poartă VALOAREA notei; meniul ei
     poartă DOAR pârghiile privitorului — nimic care să ducă în 403.

     Mecanica tabelului (coloane ancorate, coloane de zi fluide, săgeți, snap, umplutor,
     observator de morph) e cea din harta absențelor — capcanele ei sunt notate acolo și în
     memoria proiectului; la a treia hartă se extrage un component comun. --}}
@php
    $map = $this->gradeMap();
    $chipPalette = [
        'below' => 'bg-red-100 text-red-800 ring-red-600/30 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/30',
        'summative' => 'bg-amber-100 text-amber-800 ring-amber-600/30 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30',
        'normal' => 'bg-gray-50 text-gray-800 ring-gray-950/10 dark:bg-white/5 dark:text-gray-200 dark:ring-white/15',
    ];
@endphp

<section class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    {{-- Antet: titlu + legendă + comutatorul spre listă --}}
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-gray-200 px-4 py-3 dark:border-white/10">
        <div class="me-auto">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('grade_map.title') }}
            </h3>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                {{ $map['canAct'] ? __('grade_map.hint_act') : __('grade_map.hint_read') }}
            </p>
        </div>

        {{-- Tipul evaluării: ACELAȘI mecanism ca în Catalogul Electronic (mereu UN tip, fără
             „toate") — coloana unei zile arată atunci „colonița" acelei evaluări, nu un amestec
             de curente, ESI și teze. Media din Total rămâne cea oficială, pe toate tipurile. --}}
        <div class="flex items-center gap-2">
            <span class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                {{ __('grade_map.filter_type_label') }}
            </span>
            <div class="w-48">
                <label for="harta-note-tip" class="sr-only">{{ __('panel.fields.evaluation_type') }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="harta-note-tip" wire:model.live="gradeTypeFilter">
                        @foreach ($this->gradeTypeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        <div class="flex items-center gap-3 text-xs text-gray-600 dark:text-gray-300">
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>
                {{ __('grade_map.legend_below') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                {{ __('grade_map.legend_summative') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <x-filament::icon icon="heroicon-o-clock" class="h-3.5 w-3.5 text-amber-500" />
                {{ __('grade_map.legend_pending') }}
            </span>
        </div>

        <button
            type="button"
            wire:click="setGradeView('lista')"
            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-700 ring-1 ring-gray-950/10 transition hover:bg-gray-50 dark:text-gray-200 dark:ring-white/20 dark:hover:bg-white/5"
        >
            <x-filament::icon icon="heroicon-o-list-bullet" class="h-4 w-4" />
            {{ __('grade_map.switch_to_list') }}
        </button>
    </div>

    @if ($map['days'] === [])
        {{-- Golul își spune AMBELE cauze: tipul ales și perioada. Fără tip în mesaj, o hartă
             goală pe „Teză" în octombrie s-ar fi citit ca „lipsesc datele". --}}
        <div class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">
            {{ __('grade_map.empty_period', ['type' => $this->gradeTypeOptions()[$this->activeGradeType()->value] ?? '']) }}
        </div>
    @else
        {{-- ZONA ZILELOR derulează orizontal între coloanele ANCORATE (numele — stânga,
             totalurile — dreapta); săgețile de carusel stau în rândul antetului, în culoarele
             coloanelor ancorate, și apar doar la surplus. --}}
        <div
            x-data="{
                canLeft: false,
                canRight: false,
                nameW: 0,
                totalW: 0,
                dayW: 80,
                nCols: 1,
                extra: 0,
                fill: 0,
                arrowTop: 6,
                sync() {
                    const el = this.$refs.scroller;

                    if (! el) {
                        return;
                    }

                    this.canLeft = el.scrollLeft > 1;
                    this.canRight = el.scrollLeft + el.clientWidth < el.scrollWidth - 1;
                    this.nameW = this.$refs.nameTh?.offsetWidth ?? 0;

                    // Coloane ÎNTREGI, restul întinde ușor coloanele (80 → max 96px): culoarul
                    // separatorului rămâne fix, granițele cad pe muchie de coloană.
                    {{-- ⚠️ Suntem ÎN INTERIORUL atributului x-data (ghilimele duble) — niciun
                         caracter de ghilimea dublă în comentariile JS, altfel atributul se
                         închide și codul se varsă ca TEXT pe pagină. --}}
                    const totalBase = (this.$refs.totalTh?.offsetWidth ?? 0) - this.extra;
                    const days = el.querySelectorAll('thead th').length - 3; // nume + umplutor + total
                    const avail = el.clientWidth - this.nameW - totalBase;
                    const base = 80;

                    if (avail < base) {
                        this.nCols = 1;
                        this.dayW = base;
                        this.extra = 0;
                        this.fill = 0;
                    } else if (Math.floor(avail / base) >= days) {
                        this.nCols = Math.max(1, days);
                        this.dayW = Math.min(96, avail / Math.max(1, days));
                        this.extra = 0;
                        this.fill = Math.max(0, Math.round(avail - this.nCols * this.dayW));
                    } else {
                        this.nCols = Math.max(1, Math.floor(avail / base));
                        this.dayW = Math.min(96, avail / this.nCols);
                        this.extra = Math.max(0, Math.round(avail - this.nCols * this.dayW));
                        this.fill = 0;
                    }

                    this.totalW = totalBase + this.extra;

                    const headH = el.querySelector('thead')?.offsetHeight ?? 0;
                    this.arrowTop = Math.max(4, Math.round((headH - 28) / 2));
                },
                nudge(direction) {
                    const el = this.$refs.scroller;
                    const current = Math.round(el.scrollLeft / this.dayW);

                    el.scrollTo({ left: (current + direction * this.nCols) * this.dayW, behavior: 'smooth' });
                },
            }"
            x-init="
                $nextTick(() => sync());
                setTimeout(() => sync(), 300);
                new MutationObserver(() => { clearTimeout(window.__gradeMapSyncT); window.__gradeMapSyncT = setTimeout(() => sync(), 80); })
                    .observe($refs.scroller, { childList: true, subtree: true, characterData: true });
            "
            x-on:resize.window.debounce.150ms="sync()"
            class="relative"
            x-bind:style="'--map-extra: ' + extra + 'px; --map-fill: ' + fill + 'px; --map-day: ' + dayW + 'px'"
            wire:key="grade-map-{{ count($map['days']) }}-{{ $map['days'][0]['iso'] ?? 'gol' }}-{{ $map['days'][count($map['days']) - 1]['iso'] ?? '' }}"
        >
            <div
                x-ref="scroller"
                x-on:scroll.passive.debounce.50ms="sync()"
                class="snap-x snap-mandatory overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                x-bind:style="'scroll-padding-left: ' + nameW + 'px'"
            >
            <table class="min-w-max border-separate border-spacing-0 text-sm">
                <thead>
                    <tr>
                        <th x-ref="nameTh" class="sticky left-0 z-10 border-b border-e border-gray-200 bg-white ps-4 pe-9 py-2 text-start text-xs font-semibold text-gray-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-400">
                            {{ __('grade_map.student') }}
                        </th>
                        @foreach ($map['days'] as $day)
                            <th @if ($loop->first) x-ref="firstDayTh" @endif style="width: var(--map-day, 5rem)" class="min-w-20 snap-start border-b border-gray-200 px-2 py-2 text-center text-xs font-semibold text-gray-500 dark:border-white/10 dark:text-gray-400"
                                title="{{ trans_choice('grade_map.day_count', $day['count'], ['count' => $day['count']]) }}">
                                <span class="block tabular-nums">{{ $day['day'] }}</span>
                                <span class="block text-[10px] font-normal text-gray-400 dark:text-gray-500">{{ $day['weekday'] }}</span>
                            </th>
                        @endforeach
                        {{-- Umplutorul elastic din coada zonei zilelor: cu zile puține, consumă
                             restul cardului ca Total să stea la marginea dreaptă. Lățime în px
                             prin variabilă, NU procent (capcana 10⁶ px). --}}
                        <th aria-hidden="true" class="border-b border-gray-200 p-0 dark:border-white/10" style="width: var(--map-fill, 0px)"></th>
                        {{-- Antetul Total = TREI micro-coloane ETICHETATE (redesign 05.08.2026):
                             marcajele criptice (<5, S) au ieșit — fiecare pistă își spune numele,
                             iar cifrele de dedesubt cad exact sub eticheta lor. --}}
                        <th x-ref="totalTh" class="sticky right-0 z-10 border-b border-s border-gray-200 bg-white ps-[calc(2.25rem+var(--map-extra,0px))] pe-2 py-2 dark:border-white/10 dark:bg-gray-900">
                            <span class="flex items-center justify-end gap-2 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                <span class="w-9 text-center">{{ __('grade_map.totals_grades') }}</span>
                                <span class="w-9 text-center text-red-500/80 dark:text-red-400/80">{{ __('grade_map.legend_below') }}</span>
                                <span class="w-12 text-center">{{ __('grade_map.totals_average') }}</span>
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($map['rows'] as $row)
                        <tr class="group">
                            {{-- Hover OPAC pe celulele ancorate (bg-gray-800, nu nuanțe translucide):
                                 altfel pastilele derulate pe dedesubt răzbat prin celulă. --}}
                            <td class="sticky left-0 z-10 whitespace-nowrap border-b border-e border-gray-100 border-e-gray-200 bg-white ps-4 pe-9 py-2 font-medium text-gray-950 group-hover:bg-gray-50 dark:border-white/5 dark:border-e-white/10 dark:bg-gray-900 dark:text-white dark:group-hover:bg-gray-800">
                                {{ $row['student']->last_name }} {{ $row['student']->first_name }}
                            </td>

                            @foreach ($map['days'] as $day)
                                {{-- CELULA ZILEI = buton spre PANOUL doar-note (scrierea din
                                     hartă, 05.08.2026): pastilele rămân vizuale (title/aria), iar
                                     pârghiile fiecărei note trăiesc în panou, cu formulare inline
                                     — ACELEAȘI ca în Catalogul Electronic (concern comun).
                                     Meniul-hover per pastilă a fost retras: două interacțiuni pe
                                     același pixel se călcau pe picioare. --}}
                                <td class="border-b border-gray-100 p-0 text-center align-middle dark:border-white/5">
                                    <button
                                        type="button"
                                        wire:click="mountAction('gradeDayPanel', { student: {{ $row['student']->getKey() }}, iso: '{{ $day['iso'] }}' })"
                                        aria-label="{{ __('panel.class_register.day_panel.open_cell', ['student' => $row['student']->full_name, 'date' => $day['day']]) }}"
                                        class="flex min-h-10 w-full flex-wrap items-center justify-center px-1.5 py-1.5 transition hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-600 dark:hover:bg-primary-400/10"
                                    >
                                        @forelse ($row['cells'][$day['iso']] ?? [] as $chip)
                                            @php
                                                $palette = $chip['below'] ? 'below' : ($chip['summative'] ? 'summative' : 'normal');
                                                // Sumativă SUB prag: fondul roșu spune urgența, inelul
                                                // chihlimbar păstrează natura evaluării.
                                                $ring = $chip['below'] && $chip['summative'] ? ' ring-2 ring-amber-500/70' : '';
                                            @endphp

                                            <span
                                                title="{{ $chip['title'] }}"
                                                class="m-0.5 inline-flex min-h-7 min-w-7 items-center justify-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-semibold ring-1 {{ $chipPalette[$palette] }}{{ $ring }}"
                                            >
                                                {{ $chip['label'] }}
                                                @if ($chip['pending'])
                                                    <x-filament::icon icon="heroicon-o-clock" class="h-3.5 w-3.5 text-amber-500" />
                                                @endif
                                            </span>
                                        @empty
                                            {{-- Zi de lecție fără nicio notă: celula goală e
                                                 informația — și ușa spre scriere. --}}
                                            <span class="text-xs text-gray-200 dark:text-gray-700" aria-hidden>·</span>
                                        @endforelse
                                    </button>
                                </td>
                            @endforeach

                            <td aria-hidden="true" class="border-b border-gray-100 p-0 dark:border-white/5" style="width: var(--map-fill, 0px)"></td>

                            {{-- Totaluri per elev, sub etichetele din antet: câte note în perioadă,
                                 câte sub 5, și MEDIA oficială a semestrului (term_averages — cu
                                 ponderare și trunchiere, nu o medie a perioadei). Golul rămâne gol;
                                 media sub 5 se aprinde roșu. --}}
                            <td class="sticky right-0 z-10 whitespace-nowrap border-b border-s border-gray-100 border-s-gray-200 bg-white ps-[calc(2.25rem+var(--map-extra,0px))] pe-2 py-2 text-[0.8625rem] tabular-nums group-hover:bg-gray-50 dark:border-white/5 dark:border-s-white/10 dark:bg-gray-900 dark:group-hover:bg-gray-800">
                                <span class="flex items-center justify-end gap-2">
                                    <span class="flex w-9 items-center justify-center text-gray-700 dark:text-gray-200">
                                        @if ($row['totals']['total'] > 0){{ $row['totals']['total'] }}@endif
                                        <span class="sr-only">{{ __('grade_map.totals_grades') }}</span>
                                    </span>

                                    <span class="flex w-9 items-center justify-center font-semibold text-red-600 dark:text-red-400">
                                        @if ($row['totals']['below'] > 0){{ $row['totals']['below'] }}@endif
                                        <span class="sr-only">{{ __('grade_map.legend_below') }}</span>
                                    </span>

                                    @php $media = $row['totals']['average']; @endphp
                                    <span @class([
                                        'flex w-12 items-center justify-center font-semibold',
                                        'text-red-600 dark:text-red-400' => $media !== null && (float) $media < 5,
                                        'text-gray-950 dark:text-white' => $media === null || (float) $media >= 5,
                                    ])>
                                        {{ $media ?? '—' }}
                                        <span class="sr-only">{{ __('grade_map.totals_average') }}</span>
                                    </span>
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            {{-- Săgețile caruselului — în rândul antetului, pe marginile coloanelor ancorate
                 (fundal opac, nu calcă peste date); vizibilitatea în ACEEAȘI legătură de stil ca
                 poziția (x-show cu tranziție rămânea blocat la morph). --}}
            <button
                type="button"
                style="display: none"
                x-on:click="nudge(-1)"
                aria-label="{{ __('grade_map.scroll_left') }}"
                title="{{ __('grade_map.scroll_left') }}"
                class="absolute z-20 flex h-7 w-7 items-center justify-center rounded-full bg-white text-gray-600 shadow-md ring-1 ring-gray-950/10 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/20 dark:hover:bg-gray-700"
                x-bind:style="'left: ' + (nameW - 32) + 'px; top: ' + arrowTop + 'px; display: ' + (canLeft ? 'flex' : 'none')"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" width="15" height="15" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
            </button>

            <button
                type="button"
                style="display: none"
                x-on:click="nudge(1)"
                aria-label="{{ __('grade_map.scroll_right') }}"
                title="{{ __('grade_map.scroll_right') }}"
                class="absolute z-20 flex h-7 w-7 items-center justify-center rounded-full bg-white text-gray-600 shadow-md ring-1 ring-gray-950/10 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/20 dark:hover:bg-gray-700"
                x-bind:style="'right: ' + (totalW - 32) + 'px; top: ' + arrowTop + 'px; display: ' + (canRight ? 'flex' : 'none')"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" width="15" height="15" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </button>
        </div>
    @endif
</section>
