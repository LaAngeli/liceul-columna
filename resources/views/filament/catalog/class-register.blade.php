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
                        const absences = $el.querySelectorAll('[data-quick-absence-active]').length;
                        this.filled = inputs + absences;
                    },
                    focusNext(current) {
                        const inputs = Array.from($el.querySelectorAll('[data-quick-input]'));
                        const next = inputs[inputs.indexOf(current) + 1];
                        if (next) { next.focus(); next.select(); }
                    },
                }"
                x-on:input.debounce.150ms="recount()"
                x-on:change="recount()"
                {{-- Primul input primește focus la deschidere: profesorul tastează direct, fără click.
                     Contorul se recalculează și după re-randările Livewire — statutul absenței vine
                     de la server, deci un simplu `input`/`change` nu l-ar prinde niciodată. --}}
                x-init="
                    $nextTick(() => $el.querySelector('[data-quick-input]')?.focus());
                    Livewire.hook('morph.updated', () => recount());
                "
                class="space-y-4"
            >
                {{-- ── Bara de introducere rapidă (o singură dată pentru tot batch-ul) ──── --}}
                @if ($canGrade || $canAbsent)
                    {{-- Bara de control: fiecare câmp e o coloană cu etichetă sus, control de 2.25rem
                         și subtitlu dedesubt — toate pe aceeași grilă, deci capetele se aliniază
                         indiferent ce câmpuri sunt vizibile (raportat ca „unele mai sus, altele mai jos”). --}}
                    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex flex-wrap items-start gap-x-4 gap-y-3">
                            {{-- DATA e sursa UNICĂ: decide și semestrul în care intră totul, și pe
                                 cel afișat în borderou. Semestrul rezultat stă în linia de subtitlu,
                                 aceeași pentru toate coloanele — nu împinge nimic mai jos. --}}
                            <div class="w-40">
                                <label for="borderou-date" class="mb-1 flex h-4 items-center text-xs font-medium text-gray-500 dark:text-gray-400">
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
                                <p class="mt-1 flex h-4 items-center text-xs text-gray-400 dark:text-gray-500">
                                    {{ $activeTerm?->name }}
                                </p>
                            </div>

                            @if ($canGrade && $numeric)
                                <div class="w-44">
                                    <label for="borderou-type" class="mb-1 flex h-4 items-center text-xs font-medium text-gray-500 dark:text-gray-400">
                                        {{ __('panel.fields.evaluation_type') }}
                                    </label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select id="borderou-type" wire:model="entryType">
                                            @foreach (\App\Enums\EvaluationType::cases() as $type)
                                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                            @endforeach
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                    <p class="mt-1 h-4"></p>
                                </div>
                            @endif

                            {{-- Salvarea se aliniază la aceeași bandă ca celelalte controale. --}}
                            <div class="ms-auto flex flex-col">
                                <span class="mb-1 flex h-4 items-center justify-end text-xs text-gray-400 dark:text-gray-500">
                                    <span x-show="filled > 0" x-cloak>
                                        <span x-text="filled"></span> {{ __('panel.class_register.pending_count') }}
                                    </span>
                                </span>

                                <x-filament::button
                                    wire:click="saveEntries"
                                    wire:loading.attr="disabled"
                                    icon="heroicon-m-check"
                                >
                                    {{ __('panel.class_register.save_all') }}
                                </x-filament::button>
                                <p class="mt-1 h-4"></p>
                            </div>
                        </div>

                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
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
                                            <th class="w-36 px-3 py-3 text-center font-semibold text-primary-600 dark:text-primary-400">
                                                {{ __('panel.class_register.absent_column') }}
                                            </th>
                                        @endif
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                                    @foreach ($rows as $index => $row)
                                        @php($studentId = $row['student']->getKey())
                                        <tr wire:key="borderou-{{ $studentId }}" class="align-middle hover:bg-gray-50 dark:hover:bg-white/5">
                                            {{-- Elevul — lipit la stânga, numerotat (ordinea din catalogul de hârtie). --}}
                                            <td class="sticky left-0 z-[1] h-12 max-w-56 bg-white px-4 py-2 align-middle dark:bg-gray-900">
                                                <div class="flex items-baseline gap-2">
                                                    <span class="w-5 shrink-0 text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ $index + 1 }}</span>
                                                    <span class="truncate font-medium text-gray-950 dark:text-white">{{ $row['student']->full_name }}</span>
                                                </div>

                                                @error('entries.'.$studentId)
                                                    <p class="mt-0.5 ps-7 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                                @enderror
                                            </td>

                                            {{-- Notele semestrului, cronologic; teza/ESI evidențiate. --}}
                                            <td class="h-12 px-4 py-2 align-middle">
                                                @if ($row['grades'] === [])
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                @else
                                                    <div class="flex max-w-md flex-wrap gap-1">
                                                        {{-- La survol: valoarea, tipul și DATA aplicării notei, formulate
                                                             explicit („Nota 8 · Curentă · 20.07.2026"). Prima variantă
                                                             arăta doar „Curentă 20.07" și părea o a doua valoare. --}}
                                                        @foreach ($row['grades'] as $grade)
                                                            <span
                                                                title="{{ $grade['tooltip'] }}"
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
                                            <td class="h-12 px-3 py-2 text-center align-middle">
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
                                            <td class="h-12 px-3 py-2 text-center align-middle" title="{{ $row['absences']['dates'] }}">
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

                                            @if ($canGrade)
                                                <td class="h-12 px-3 py-2 text-center align-middle">
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
                                                {{-- UN singur buton: „Absent" (cerința beneficiarului, 04.08.2026).
                                                     Profesorul rareori știe DE CE lipsește elevul, deci nu i se mai
                                                     cere statutul aici — absența pleacă fără statut, iar dirigintele
                                                     o decide din secțiunea Absențe. Click activează, click anulează. --}}
                                                @php($absence = $this->entries[$studentId]['absence'] ?? null)
                                                @php($marked = $absence === \App\Filament\Pages\ClassRegister::ABSENCE_MARKED)
                                                <td class="h-12 px-3 py-2 align-middle">
                                                    <div class="flex items-center justify-center">
                                                        <button
                                                            type="button"
                                                            data-quick-absence
                                                            @if ($marked) data-quick-absence-active @endif
                                                            wire:click="toggleAbsence({{ $studentId }})"
                                                            title="{{ __('panel.class_register.absence_mark_title') }}"
                                                            aria-pressed="{{ $marked ? 'true' : 'false' }}"
                                                            aria-label="{{ __('panel.class_register.absent_column') }} — {{ $row['student']->full_name }}"
                                                            @class([
                                                                'h-8 w-24 rounded-lg text-xs font-semibold ring-1 transition duration-75 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600',
                                                                // Activ = chihlimbar, culoarea „fără statut" din secțiunea Absențe:
                                                                // același semn pentru aceeași stare, de la consemnare la triaj.
                                                                'bg-warning-500 text-white ring-warning-500 dark:bg-warning-400 dark:text-warning-950 dark:ring-warning-400' => $marked,
                                                                'bg-white text-gray-600 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/10' => ! $marked,
                                                            ])
                                                        >
                                                            {{ __('panel.class_register.absence_mark') }}
                                                        </button>
                                                    </div>
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
