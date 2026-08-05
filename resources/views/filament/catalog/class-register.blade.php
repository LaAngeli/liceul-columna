{{-- CATALOGUL CLASEI (borderou): toată clasa pe un ecran — note, medii, absențe — plus coloana
     de introducere rapidă (tastezi, Enter → elevul următor; bifezi absenții; un buton salvează
     tot). Lățimea reală a tabelului trăiește într-un container cu scroll orizontal propriu, cu
     numele elevului lipit la stânga — pe mobil nimic nu se taie (regula dashboard mobile-first). --}}
<x-filament-panels::page>
    @php
        $classes = $this->classOptions();
        $subjects = $this->subjectOptions();
        $terms = $this->termOptions();
        $activeClass = $this->activeClass();
        $activeSubject = $this->activeSubject();
        $activeTerm = $this->activeTerm();
        $rows = $this->rows();
        // Ancorele din DREAPTA (Media + Absente) si coloana elevului: latimi FIXE —
        // geometria tabelului nu depinde de continut (cerinta beneficiarului, 05.08.2026).
        $wMedia = 80; $wAbs = 128;
        $offAbsCol = 0;
        $offMedia = $wAbs;
        $rightAnchorsWidth = $offMedia + $wMedia;
        $studentColWidth = 240;
    @endphp

    <div class="space-y-4">
        {{-- ── Contextul: clasa / disciplina / semestrul ─────────────────────────────── --}}
        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2 overflow-x-auto pb-1">
                <span class="shrink-0 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    {{ __('panel.fields.class') }}
                </span>

                @foreach ($classes as $class)
                    <button
                        type="button"
                        wire:click="openClass({{ $class->id }})"
                        @class([
                            'shrink-0 rounded-full px-3 py-1 text-sm font-medium ring-1 transition duration-75 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600',
                            'bg-primary-600 text-white ring-primary-600' => $activeClass?->id === $class->id,
                            'bg-white text-gray-700 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10' => $activeClass?->id !== $class->id,
                        ])
                    >
                        {{ trim($class->name.' '.($class->section ?? '')) }}
                    </button>
                @endforeach
            </div>

            @if (count($subjects) > 0)
                <div class="flex flex-wrap items-center gap-2 overflow-x-auto pb-1">
                    <span class="shrink-0 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ __('panel.fields.subject') }}
                    </span>

                    @foreach ($subjects as $subject)
                        <button
                            type="button"
                            wire:click="openSubject({{ $subject['id'] }})"
                            @class([
                                'inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium ring-1 transition duration-75 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600',
                                'bg-primary-600 text-white ring-primary-600' => $activeSubject?->getKey() === $subject['id'],
                                'bg-white text-gray-700 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10' => $activeSubject?->getKey() !== $subject['id'],
                            ])
                        >
                            {{ $subject['name'] }}

                            {{-- Disciplina altcuiva: o vezi (diriginte/administrație), nu notezi la ea. --}}
                            @unless ($subject['mine'] || $this->viewer()?->canAdministerCatalog())
                                <x-filament::icon icon="heroicon-m-lock-closed" class="h-3.5 w-3.5 opacity-60" />
                            @endunless
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($activeClass === null || $activeSubject === null)
            <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <x-filament::icon icon="heroicon-o-table-cells" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('panel.class_register.empty_heading') }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.class_register.empty_description') }}</p>
            </div>
        @else
            <div class="space-y-4">
                {{-- ── Filtrele de CITIRE ────────────────────────────────────────────────────
                     Separate deliberat de controalele de INTRODUCERE de mai sus: acolo alegi unde
                     SCRII, aici alegi ce CITEȘTI. Confuzia dintre ele ar fi cea mai scumpă din
                     ecranul ăsta (o notă pusă pe altă zi decât cea din cap). --}}
                @php($gradeColumns = $this->gradeColumns())
                @php($aligned = $this->gradesAlignedByDate())
                <div class="space-y-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            {{ __('panel.class_register.filters_label') }}
                        </span>

                        {{-- Tipul: mereu UNUL (fără „toate"), fiindcă amestecul de curente, ESI și
                             teze pe același rând e chiar starea din care nu se putea citi nimic. --}}
                        <div class="w-48">
                            <label for="borderou-filtru-tip" class="sr-only">{{ __('panel.fields.evaluation_type') }}</label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select id="borderou-filtru-tip" wire:model.live="gradeTypeFilter">
                                    @foreach ($this->gradeTypeOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>

                    </div>

                    {{-- Perioada: EXACT bara din Note/Absențe/Teme (același partial, aceeași stare
                         în URL) — profesorul învață un singur mecanism pentru tot catalogul. --}}
                    @include('filament.catalog.partials.time-bar')
                </div>

                {{-- ── Borderoul ─────────────────────────────────────────────────────────── --}}
                @if ($rows === [])
                    <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <x-filament::icon icon="heroicon-o-user-group" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.class_register.no_students') }}</p>
                    </div>
                @else
                    {{-- ZONA ZILELOR derulează între ancorele din stânga (elevul) și dreapta
                         (media / absențe / introducerea). Săgeți în rândul antetului, pași pe
                         PASUL FIX al grilei de zile (36px), scrollbar ascuns — aceeași mecanică
                         validată în hărți; capcanele ei sunt documentate acolo. --}}
                    <div
                        x-data="{
                            canLeft: false,
                            canRight: false,
                            leftW: 0,
                            arrowTop: 8,
                            step: 36,
                            sync() {
                                const el = this.$refs.scroller;

                                if (! el) {
                                    return;
                                }

                                this.canLeft = el.scrollLeft > 1;
                                this.canRight = el.scrollLeft + el.clientWidth < el.scrollWidth - 1;
                                this.leftW = this.$refs.studentTh?.offsetWidth ?? 0;

                                const zone = el.clientWidth - this.leftW - {{ $rightAnchorsWidth }};
                                this.step = Math.max(36, Math.floor(zone / 36) * 36);

                                const headH = el.querySelector('thead')?.offsetHeight ?? 0;
                                this.arrowTop = Math.max(6, Math.round((headH - 28) / 2));
                            },
                            nudge(direction) {
                                const el = this.$refs.scroller;
                                const target = Math.round((el.scrollLeft + direction * this.step) / 36) * 36;

                                el.scrollTo({ left: Math.max(0, target), behavior: 'smooth' });
                            },
                        }"
                        x-init="
                            $nextTick(() => sync());
                            setTimeout(() => sync(), 300);
                            new MutationObserver(() => { clearTimeout(window.__borderouSyncT); window.__borderouSyncT = setTimeout(() => sync(), 80); })
                                .observe($refs.scroller, { childList: true, subtree: true, characterData: true });
                        "
                        x-on:resize.window.debounce.150ms="sync()"
                        class="relative overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                    >
                        <div
                            x-ref="scroller"
                            x-on:scroll.passive.debounce.50ms="sync()"
                            class="overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                        >
                            <table class="w-full text-start text-sm">
                                <thead>
                                    <tr class="border-b border-gray-950/5 dark:border-white/10">
                                        <th x-ref="studentTh" class="sticky left-0 z-[2] border-e border-gray-200 bg-white px-4 py-3 text-start font-semibold text-gray-950 dark:border-white/10 dark:bg-gray-900 dark:text-white" style="width: {{ $studentColWidth }}px; min-width: {{ $studentColWidth }}px; max-width: {{ $studentColWidth }}px">
                                            {{ __('panel.fields.student') }}
                                        </th>
                                        {{-- RIGLA de zile — uniunea notelor și absențelor. Un click pe o
                                             zi mută DATA de introducere acolo („selectarea directă a
                                             zilei", 05.08.2026); ziua activă poartă inelul. --}}
                                        <th class="px-4 py-3 text-start font-semibold text-gray-950 dark:text-white" style="width: 99%">
                                            @if ($aligned)
                                                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                                    {{ __('panel.class_register.days_column') }}
                                                </span>
                                                <span class="grid gap-1" style="grid-template-columns: repeat({{ count($gradeColumns) }}, 2rem);">
                                                    @foreach ($gradeColumns as $column)
                                                        <span class="flex flex-col items-center leading-tight" title="{{ $column['iso'] }}">
                                                            <span class="text-[11px] font-semibold tabular-nums text-gray-600 dark:text-gray-300">{{ $column['label'] }}</span>
                                                            <span class="text-[9px] font-normal uppercase text-gray-400 dark:text-gray-500">{{ $column['weekday'] }}</span>
                                                        </span>
                                                    @endforeach
                                                </span>
                                            @else
                                                {{ __('panel.class_register.days_column') }}
                                            @endif
                                        </th>
                                        <th class="sticky z-[2] border-s border-gray-200 bg-white px-3 py-3 text-center font-semibold text-gray-950 dark:border-white/10 dark:bg-gray-900 dark:text-white" style="right: {{ $offMedia }}px; width: {{ $wMedia }}px; min-width: {{ $wMedia }}px">
                                            {{ __('panel.class_register.average_column') }}
                                        </th>
                                        <th class="sticky z-[2] bg-white px-3 py-3 text-center font-semibold text-gray-950 dark:bg-gray-900 dark:text-white" style="right: {{ $offAbsCol }}px; width: {{ $wAbs }}px; min-width: {{ $wAbs }}px">
                                            {{ __('panel.class_register.absences_column') }}
                                        </th>

                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                                    @foreach ($rows as $index => $row)
                                        @php($studentId = $row['student']->getKey())
                                        <tr wire:key="borderou-{{ $studentId }}" class="align-middle hover:bg-gray-50 dark:hover:bg-white/5">
                                            {{-- Elevul — lipit la stânga, numerotat (ordinea din catalogul de hârtie). --}}
                                            <td class="sticky left-0 z-[1] h-12 border-e border-gray-200 bg-white px-4 py-2 align-middle dark:border-white/10 dark:bg-gray-900" style="width: {{ $studentColWidth }}px; min-width: {{ $studentColWidth }}px; max-width: {{ $studentColWidth }}px">
                                                <div class="flex items-baseline gap-2">
                                                    <span class="w-5 shrink-0 text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ $index + 1 }}</span>
                                                    <span class="truncate font-medium text-gray-950 dark:text-white">{{ $row['student']->full_name }}</span>
                                                </div>

                                            </td>

                                            {{-- ZILELE elevului: note + absențe, pe coloane de dată.
                                                 Fiecare celulă e un BUTON — click deschide PANOUL ZILEI
                                                 (modal cu notele, absențele pe ore și acțiunile permise
                                                 privitorului). Celula goală rămâne clickabilă pentru cine
                                                 poate scrie: acolo se consemnează absența unei zile fără
                                                 activitate. --}}
                                            <td class="h-12 px-4 py-2 align-middle" style="width: 99%">
                                                @if ($aligned)
                                                    <div class="grid gap-1" style="grid-template-columns: repeat({{ count($gradeColumns) }}, 2rem);">
                                                        @foreach ($gradeColumns as $column)
                                                            @php($dayGrades = $row['gradesByDate'][$column['iso']] ?? [])
                                                            @php($dayAbsences = $row['absencesByDate'][$column['iso']] ?? [])
                                                            @php($hasContent = $dayGrades !== [] || $dayAbsences !== [])
                                                            <button
                                                                type="button"
                                                                wire:click="mountAction('dayPanel', { student: {{ $studentId }}, iso: '{{ $column['iso'] }}' })"
                                                                title="{{ $row['student']->full_name }} · {{ $column['label'] }}"
                                                                aria-label="{{ __('panel.class_register.day_panel.open_cell', ['student' => $row['student']->full_name, 'date' => $column['label']]) }}"
                                                                @class([
                                                                    'flex min-h-9 flex-col items-center justify-center gap-0.5 rounded-md py-0.5 transition',
                                                                    'hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 dark:hover:bg-primary-400/10' => true,
                                                                    'cursor-pointer' => true,
                                                                ])
                                                            >
                                                                @foreach ($dayGrades as $grade)
                                                                    <span
                                                                        title="{{ $grade['tooltip'] }}"
                                                                        @class([
                                                                            'inline-flex h-6 w-8 items-center justify-center rounded text-xs font-semibold tabular-nums',
                                                                            'bg-primary-50 text-primary-700 ring-1 ring-primary-600/30 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/30' => $grade['weighted'],
                                                                            'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200' => ! $grade['weighted'],
                                                                        ])
                                                                    >
                                                                        {{ $grade['value'] }}
                                                                        @if ($grade['pending'])
                                                                            <span class="-me-1 ms-0.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" title="{{ __('panel.tables.grades.pending_correction_tooltip') }}"></span>
                                                                        @endif
                                                                    </span>
                                                                @endforeach

                                                                {{-- Absențele zilei: pastilă îngustă pe culoarea
                                                                     statutului, cu ORA când e precizată — două ore
                                                                     consecutive = două pastile. --}}
                                                                @foreach ($dayAbsences as $absence)
                                                                    <span
                                                                        title="{{ $absence['status_label'] }}{{ $absence['lesson'] !== null ? ' · '.__('panel.forms.absence.lesson_option', ['number' => $absence['lesson']]) : '' }}"
                                                                        @class([
                                                                            'inline-flex h-3.5 w-8 items-center justify-center rounded-sm text-[9px] font-bold uppercase leading-none ring-1',
                                                                            'bg-amber-100 text-amber-800 ring-amber-600/30 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30' => $absence['color'] === 'warning',
                                                                            'bg-green-100 text-green-800 ring-green-600/30 dark:bg-green-400/10 dark:text-green-300 dark:ring-green-400/30' => $absence['color'] === 'success',
                                                                            'bg-red-100 text-red-800 ring-red-600/30 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/30' => $absence['color'] === 'danger',
                                                                        ])
                                                                    >{{ $absence['lesson'] !== null ? 'a'.$absence['lesson'] : 'a' }}</span>
                                                                @endforeach

                                                                @unless ($hasContent)
                                                                    <span class="inline-flex h-6 w-8 items-center justify-center text-xs text-gray-200 dark:text-gray-700" aria-hidden>·</span>
                                                                @endunless
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    {{-- Fără nimic în perioada+tipul ales (doar atunci
                                                         $aligned e fals): liniuță, nu un șir alternativ. --}}
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                @endif
                                            </td>

                                            {{-- Media semestrială OFICIALĂ (MS) — sub 5 = semnal de corigență.
                                                 ⚠️ Ancorele din dreapta sunt sticky ȘI PE CORP, cu ACELEAȘI
                                                 offseturi/lățimi ca antetul: fără asta, celulele corpului
                                                 rămâneau în flux și coloanele „respirau" după conținut
                                                 (raportat pe rolul profesor, 05.08.2026). --}}
                                            <td class="sticky z-[1] h-12 border-s border-gray-200 bg-white px-3 py-2 text-center align-middle dark:border-white/10 dark:bg-gray-900" style="right: {{ $offMedia }}px; width: {{ $wMedia }}px; min-width: {{ $wMedia }}px">
                                                @if ($row['average'] !== null)
                                                    <span @class([
                                                        'font-semibold tabular-nums',
                                                        'text-danger-600 dark:text-danger-400' => (float) str_replace(',', '.', $row['average']) < 5,
                                                        'text-gray-950 dark:text-white' => (float) str_replace(',', '.', $row['average']) >= 5,
                                                    ])>{{ $row['average'] }}</span>
                                                @else
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                @endif
                                            </td>

                                            {{-- Absențele la disciplină: total + câte nemotivate + câte încă fără statut;
                                                 datele la survol (✓ motivată, ? fără statut). --}}
                                            <td class="sticky z-[1] h-12 bg-white px-3 py-2 text-center align-middle dark:bg-gray-900" style="right: {{ $offAbsCol }}px; width: {{ $wAbs }}px; min-width: {{ $wAbs }}px" title="{{ $row['absences']['dates'] }}">
                                                @if ($row['absences']['total'] === 0)
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                @else
                                                    <span class="tabular-nums text-gray-700 dark:text-gray-200">{{ $row['absences']['total'] }}</span>
                                                    @if ($row['absences']['unmotivated'] > 0)
                                                        <span class="ms-1 text-xs font-medium text-danger-600 dark:text-danger-400">
                                                            ({{ $row['absences']['unmotivated'] }} {{ __('panel.class_register.unmotivated_short') }})
                                                        </span>
                                                    @endif
                                                    @if ($row['absences']['pending'] > 0)
                                                        <span class="ms-1 text-xs font-medium text-warning-600 dark:text-warning-400">
                                                            ({{ $row['absences']['pending'] }} {{ __('panel.class_register.pending_short') }})
                                                        </span>
                                                    @endif
                                                @endif
                                            </td>

                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- SĂGEȚILE de derulare — aceeași mecanică validată în hărți: la capetele
                             zonei zilelor (stânga după coloana elevului, dreapta înaintea ancorelor),
                             vizibile doar când există surplus; scrollbar-ul de jos e ASCUNS, ele sunt
                             singura afordanță — plus derularea nativă (trackpad, shift+rotiță, touch). --}}
                        <button
                            type="button"
                            style="display: none"
                            x-on:click="nudge(-1)"
                            aria-label="{{ __('absence_map.scroll_left') }}"
                            title="{{ __('absence_map.scroll_left') }}"
                            class="absolute z-20 flex h-7 w-7 items-center justify-center rounded-full bg-white text-gray-600 shadow-md ring-1 ring-gray-950/10 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/20 dark:hover:bg-gray-700"
                            x-bind:style="'left: ' + (leftW - 14) + 'px; top: ' + arrowTop + 'px; display: ' + (canLeft ? 'flex' : 'none')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" width="15" height="15" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                        </button>

                        <button
                            type="button"
                            style="display: none"
                            x-on:click="nudge(1)"
                            aria-label="{{ __('absence_map.scroll_right') }}"
                            title="{{ __('absence_map.scroll_right') }}"
                            class="absolute z-20 flex h-7 w-7 items-center justify-center rounded-full bg-white text-gray-600 shadow-md ring-1 ring-gray-950/10 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/20 dark:hover:bg-gray-700"
                            x-bind:style="'right: ' + ({{ $rightAnchorsWidth }} - 14) + 'px; top: ' + arrowTop + 'px; display: ' + (canRight ? 'flex' : 'none')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" width="15" height="15" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>

                        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-950/5 px-4 py-2.5 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <span>{{ trans_choice('panel.class_register.students_count', count($rows), ['count' => count($rows)]) }}</span>
                            <span>{{ __('panel.class_register.cell_hint') }}</span>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
