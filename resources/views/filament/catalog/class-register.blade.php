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
        $canGrade = $this->canEnterGrades();
        $canAbsent = $this->canRecordAbsences();
        $numeric = $this->gradingType() === \App\Enums\GradingType::Numeric;
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
            <div
                x-data="{
                    filled: 0,
                    recount() {
                        const inputs = Array.from($el.querySelectorAll('[data-quick-input]')).filter((i) => i.value.trim() !== '').length;
                        const checks = $el.querySelectorAll('[data-quick-check]:checked').length;
                        this.filled = inputs + checks;
                    },
                    focusNext(current) {
                        const inputs = Array.from($el.querySelectorAll('[data-quick-input]'));
                        const next = inputs[inputs.indexOf(current) + 1];
                        if (next) { next.focus(); next.select(); }
                    },
                }"
                x-on:input.debounce.150ms="recount()"
                x-on:change="recount()"
                {{-- Primul input primește focus la deschidere: profesorul tastează direct, fără click. --}}
                x-init="$nextTick(() => $el.querySelector('[data-quick-input]')?.focus())"
                class="space-y-4"
            >
                {{-- ── Bara de introducere rapidă (o singură dată pentru tot batch-ul) ──── --}}
                @if ($canGrade || $canAbsent)
                    <div class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        {{-- DATA e sursa UNICĂ: decide și semestrul în care intră totul, și pe cel
                             afișat în borderou. De aceea nu mai există un selector separat de
                             semestru — arătăm doar ce a rezultat din dată. --}}
                        <div class="w-40">
                            <label for="borderou-date" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ __('panel.fields.date') }}
                            </label>
                            <x-filament::input.wrapper>
                                <x-filament::input
                                    id="borderou-date"
                                    type="date"
                                    wire:model.live="entryDate"
                                    max="{{ \Illuminate\Support\Carbon::today()->toDateString() }}"
                                />
                            </x-filament::input.wrapper>

                            @if ($activeTerm !== null)
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $activeTerm->name }}</p>
                            @endif
                        </div>

                        @if ($canGrade && $numeric)
                            <div class="w-44">
                                <label for="borderou-type" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                    {{ __('panel.fields.evaluation_type') }}
                                </label>
                                <x-filament::input.wrapper>
                                    <x-filament::input.select id="borderou-type" wire:model="entryType">
                                        @foreach (\App\Enums\EvaluationType::cases() as $type)
                                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            </div>
                        @endif

                        @if ($canAbsent)
                            <label class="flex h-9 cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input
                                    type="checkbox"
                                    wire:model="entryMotivated"
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600 dark:border-white/20 dark:bg-white/5"
                                />
                                {{ __('panel.class_register.absences_motivated') }}
                            </label>
                        @endif

                        <div class="ms-auto flex items-center gap-3">
                            <span class="text-xs text-gray-400 dark:text-gray-500" x-show="filled > 0" x-cloak>
                                <span x-text="filled"></span> {{ __('panel.class_register.pending_count') }}
                            </span>

                            <x-filament::button
                                wire:click="saveEntries"
                                wire:loading.attr="disabled"
                                icon="heroicon-m-check"
                            >
                                {{ __('panel.class_register.save_all') }}
                            </x-filament::button>
                        </div>

                        <p class="w-full text-xs text-gray-400 dark:text-gray-500">
                            {{ __('panel.class_register.entry_hint') }}
                        </p>
                    </div>
                @else
                    <div class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                        {{ __('panel.class_register.read_only_hint') }}
                    </div>
                @endif

                {{-- ── Borderoul ─────────────────────────────────────────────────────────── --}}
                @if ($rows === [])
                    <div class="flex flex-col items-center gap-3 rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <x-filament::icon icon="heroicon-o-user-group" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('panel.class_register.no_students') }}</p>
                    </div>
                @else
                    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-max text-start text-sm">
                                <thead>
                                    <tr class="border-b border-gray-950/5 dark:border-white/10">
                                        <th class="sticky left-0 z-[1] bg-white px-4 py-3 text-start font-semibold text-gray-950 dark:bg-gray-900 dark:text-white">
                                            {{ __('panel.fields.student') }}
                                        </th>
                                        <th class="px-4 py-3 text-start font-semibold text-gray-950 dark:text-white">
                                            {{ __('panel.class_register.grades_column') }}
                                        </th>
                                        <th class="px-3 py-3 text-center font-semibold text-gray-950 dark:text-white">
                                            {{ __('panel.class_register.average_column') }}
                                        </th>
                                        <th class="px-3 py-3 text-center font-semibold text-gray-950 dark:text-white">
                                            {{ __('panel.class_register.absences_column') }}
                                        </th>

                                        @if ($canGrade)
                                            <th class="w-24 px-3 py-3 text-center font-semibold text-primary-600 dark:text-primary-400">
                                                {{ __('panel.class_register.new_grade_column') }}
                                            </th>
                                        @endif

                                        @if ($canAbsent)
                                            <th class="w-20 px-3 py-3 text-center font-semibold text-primary-600 dark:text-primary-400">
                                                {{ __('panel.class_register.absent_column') }}
                                            </th>
                                        @endif
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                                    @foreach ($rows as $index => $row)
                                        @php($studentId = $row['student']->getKey())
                                        <tr wire:key="borderou-{{ $studentId }}" class="hover:bg-gray-50 dark:hover:bg-white/5">
                                            {{-- Elevul — lipit la stânga, numerotat (ordinea din catalogul de hârtie). --}}
                                            <td class="sticky left-0 z-[1] max-w-56 bg-white px-4 py-2 dark:bg-gray-900">
                                                <div class="flex items-baseline gap-2">
                                                    <span class="w-5 shrink-0 text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ $index + 1 }}</span>
                                                    <span class="truncate font-medium text-gray-950 dark:text-white">{{ $row['student']->full_name }}</span>
                                                </div>

                                                @error('entries.'.$studentId)
                                                    <p class="mt-0.5 ps-7 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                                @enderror
                                            </td>

                                            {{-- Notele semestrului, cronologic; teza/ESI evidențiate. --}}
                                            <td class="px-4 py-2">
                                                @if ($row['grades'] === [])
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                @else
                                                    <div class="flex max-w-md flex-wrap gap-1">
                                                        {{-- Fără tooltip: „Curentă 20.07" la survol arăta ca o valoare
                                                             în plus și inducea în eroare (raportat 2026-07-30).
                                                             Tipul rămâne vizibil unde contează — teza/ESI, prin culoare. --}}
                                                        @foreach ($row['grades'] as $grade)
                                                            <span
                                                                @class([
                                                                    'inline-flex h-6 min-w-6 items-center justify-center rounded px-1 text-xs font-semibold tabular-nums',
                                                                    'bg-primary-50 text-primary-700 ring-1 ring-primary-600/30 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/30' => $grade['weighted'],
                                                                    'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200' => ! $grade['weighted'],
                                                                ])
                                                            >
                                                                {{ $grade['value'] }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>

                                            {{-- Media semestrială OFICIALĂ (MS) — sub 5 = semnal de corigență. --}}
                                            <td class="px-3 py-2 text-center">
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

                                            {{-- Absențele la disciplină: total + câte nemotivate; datele la survol. --}}
                                            <td class="px-3 py-2 text-center" title="{{ $row['absences']['dates'] }}">
                                                @if ($row['absences']['total'] === 0)
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                @else
                                                    <span class="tabular-nums text-gray-700 dark:text-gray-200">{{ $row['absences']['total'] }}</span>
                                                    @if ($row['absences']['unmotivated'] > 0)
                                                        <span class="ms-1 text-xs font-medium text-danger-600 dark:text-danger-400">
                                                            ({{ $row['absences']['unmotivated'] }} {{ __('panel.class_register.unmotivated_short') }})
                                                        </span>
                                                    @endif
                                                @endif
                                            </td>

                                            @if ($canGrade)
                                                <td class="px-3 py-2 text-center">
                                                    <input
                                                        type="text"
                                                        data-quick-input
                                                        wire:model="entries.{{ $studentId }}.value"
                                                        x-on:keydown.enter.prevent="focusNext($event.target)"
                                                        @if ($numeric) inputmode="numeric" maxlength="2" @else maxlength="10" @endif
                                                        aria-label="{{ __('panel.class_register.new_grade_column') }} — {{ $row['student']->full_name }}"
                                                        @class([
                                                            'h-9 w-14 rounded-lg border-0 bg-white text-center text-sm font-semibold tabular-nums text-gray-950 shadow-sm ring-1 transition focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white',
                                                            'ring-danger-400 dark:ring-danger-500' => $errors->has('entries.'.$studentId),
                                                            'ring-gray-950/10 dark:ring-white/20' => ! $errors->has('entries.'.$studentId),
                                                        ])
                                                    />
                                                </td>
                                            @endif

                                            @if ($canAbsent)
                                                <td class="px-3 py-2 text-center">
                                                    <input
                                                        type="checkbox"
                                                        data-quick-check
                                                        wire:model="entries.{{ $studentId }}.absent"
                                                        aria-label="{{ __('panel.class_register.absent_column') }} — {{ $row['student']->full_name }}"
                                                        class="h-5 w-5 rounded border-gray-300 text-danger-600 focus:ring-danger-600 dark:border-white/20 dark:bg-white/5"
                                                    />
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-950/5 px-4 py-2.5 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <span>{{ trans_choice('panel.class_register.students_count', count($rows), ['count' => count($rows)]) }}</span>
                            <span>{{ __('panel.class_register.term_note') }}</span>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
