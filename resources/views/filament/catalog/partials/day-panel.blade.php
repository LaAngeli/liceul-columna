{{-- PANOUL ZILEI (modal) — celula (elev × zi) desfăcută: notele cu pârghiile lor, absențele pe
     ore cu statutul lor, consemnarea unei absențe noi pe ora aleasă. Toate pârghiile vin judecate
     de pe server (ClassRegister::dayPanel) — blade-ul doar le arată.

     Modal, nu popover ancorat de celulă: celula trăiește în zona derulabilă (overflow ascuns),
     unde un popover ar fi retezat la margine. --}}
@php
    $statusPalette = [
        'warning' => 'bg-amber-100 text-amber-800 ring-amber-600/30 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30',
        'success' => 'bg-green-100 text-green-800 ring-green-600/30 dark:bg-green-400/10 dark:text-green-300 dark:ring-green-400/30',
        'danger' => 'bg-red-100 text-red-800 ring-red-600/30 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/30',
    ];
    $statusChoices = [
        \App\Enums\AbsenceStatus::Motivated,
        \App\Enums\AbsenceStatus::Unmotivated,
        \App\Enums\AbsenceStatus::Pending,
    ];
    // Panoul doar-note (harta Note) nu trimite drepturile de absență — secțiunea lor lipsește.
    $rights = $panel['rights'] ?? ['can_status' => false];
    $defaultHour = $panel['default_hour'] ?? null;
    $busyCount = $panel['busy_count'] ?? 0;
    $canGrade = $panel['can_grade'] ?? false;
    $canAbsent = $panel['can_absent'] ?? false;
    $hourMenu = $panel['hour_menu'] ?? [];

    // Eticheta unei opțiuni de oră: liberă = „Ora N"; ocupată = „Ora N · schimbă cu nota/absența"
    // — ora ocupată RĂMÂNE selectabilă, fiindcă acțiunea firească acolo e schimbul de locuri
    // (altfel inversarea a două consemnări ar cere trei mutări printr-un ordinal liber).
    $hourOptionLabel = function (array $slot, ?int $own): string {
        $label = (string) __('panel.forms.absence.lesson_option', ['number' => $slot['hour']]);

        if ($slot['hour'] === $own || $slot['busy'] === null) {
            return $label;
        }

        return $label.' · '.__($slot['busy'] === 'grade'
            ? 'panel.class_register.day_panel.hour_swap_grade'
            : 'panel.class_register.day_panel.hour_swap_absence');
    };
@endphp

<div class="space-y-5 text-sm">
    {{-- ── Notele zilei ─────────────────────────────────────────────────────────────────── --}}
    <section>
        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
            {{ __('panel.class_register.day_panel.grades_heading') }}
        </h4>

        @if ($panel['grades'] === [])
            <p class="text-gray-500 dark:text-gray-400">{{ __('panel.class_register.day_panel.no_grades') }}</p>
        @else
            <ul class="space-y-2">
                @foreach ($panel['grades'] as $grade)
                    {{-- Formularele de anulare/corecție se deschid INLINE, în rândul notei.
                         ⚠️ NU acțiuni Filament montate din modal: `mountAction` nu montează peste
                         o acțiune deja montată (acțiunile imbricate se declară prin
                         `extraModalFooterActions`, care sunt globale pe modal — aici trebuie una
                         per notă). Butoanele existau, dar clickul nu deschidea nimic. --}}
                    <li x-data="{ mode: null, reason: '', value: '' }" class="flex flex-wrap items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                        <span @class([
                            'inline-flex h-7 w-9 items-center justify-center rounded text-sm font-semibold tabular-nums ring-1',
                            'bg-primary-50 text-primary-700 ring-primary-600/30 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/30' => $grade['weighted'] && ! $grade['annulled'],
                            'bg-white text-gray-800 ring-gray-950/10 dark:bg-white/10 dark:text-gray-200 dark:ring-white/15' => ! $grade['weighted'] && ! $grade['annulled'],
                            'bg-gray-100 text-gray-400 line-through ring-gray-950/10 dark:bg-white/5 dark:text-gray-500 dark:ring-white/10' => $grade['annulled'],
                        ])>{{ $grade['value'] }}</span>

                        <span class="text-xs text-gray-600 dark:text-gray-300">{{ $grade['type_label'] }}</span>

                        {{-- ORA notei — ORDINALĂ (06.08.2026 v2): a câta consemnare a disciplinei
                             e în ziua dată, nu poziția din orar. CORECTABILĂ (07.08.2026): ora
                             atribuită automat (nota pe Ora 1, absența pe Ora 2 la introducerea în
                             masă) putea să nu corespundă realității; aici se schimbă, iar dacă ora
                             aleasă e ocupată, cele două consemnări fac SCHIMB de locuri. --}}
                        @if ($grade['can_move_hour'] && $hourMenu !== [])
                            <select
                                x-on:change="$wire.moveDayGradeHour({{ $grade['id'] }}, $event.target.value)"
                                aria-label="{{ __('panel.class_register.day_panel.hour_select_label') }}"
                                title="{{ __('panel.class_register.day_panel.hour_select_label') }}"
                                class="h-7 rounded-md border-0 bg-white py-0 pe-7 ps-2 text-xs text-gray-600 shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/15"
                            >
                                @if ($grade['lesson'] === null)
                                    <option value="" selected>{{ __('panel.forms.absence.lesson_unspecified') }}</option>
                                @endif

                                @foreach ($hourMenu as $slot)
                                    <option value="{{ $slot['hour'] }}" @selected($grade['lesson'] === $slot['hour'])>
                                        {{ $hourOptionLabel($slot, $grade['lesson']) }}
                                    </option>
                                @endforeach
                            </select>
                        @elseif ($grade['lesson'] !== null)
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ __('panel.forms.absence.lesson_option', ['number' => $grade['lesson']]) }}
                            </span>
                        @endif

                        @if ($grade['annulled'])
                            <span class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                {{ __('panel.class_register.day_panel.annulled_badge') }}
                            </span>
                        @endif

                        @if ($grade['pending'])
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-400/10 dark:text-amber-300">
                                <x-filament::icon icon="heroicon-o-clock" class="h-3 w-3" />
                                {{ __('panel.tables.grades.pending_correction_tooltip') }}
                            </span>
                        @endif

                        <span class="ms-auto inline-flex items-center gap-1">
                            @if ($grade['edit_url'] !== null)
                                <a href="{{ $grade['edit_url'] }}" class="rounded-md px-2 py-1 text-xs font-medium text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-400/10">
                                    {{ __('panel.class_register.day_panel.edit_grade') }}
                                </a>
                            @endif

                            @if ($grade['can_request'])
                                <button
                                    type="button"
                                    x-on:click="mode = (mode === 'corectie' ? null : 'corectie')"
                                    class="rounded-md px-2 py-1 text-xs font-medium text-warning-600 hover:bg-warning-50 dark:text-warning-400 dark:hover:bg-warning-400/10"
                                >
                                    {{ __('panel.actions.request_correction.label') }}
                                </button>
                            @endif

                            @if ($grade['can_annul'])
                                <button
                                    type="button"
                                    x-on:click="mode = (mode === 'anulare' ? null : 'anulare')"
                                    class="rounded-md px-2 py-1 text-xs font-medium text-danger-600 hover:bg-danger-50 dark:text-danger-400 dark:hover:bg-danger-400/10"
                                >
                                    {{ __('panel.actions.annul.label') }}
                                </button>
                            @endif
                        </span>

                        {{-- ANULARE: motivul e obligatoriu — nota iese din medii, dar rămâne în
                             istoric cu explicația ei. --}}
                        <div x-show="mode === 'anulare'" x-cloak class="w-full space-y-2 border-t border-gray-200 pt-2 dark:border-white/10">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('panel.actions.annul.description') }}</p>
                            {{-- ⚠️ px/py EXPLICITE: preflight-ul Tailwind taie padding-ul nativ al
                                 lui <textarea>, iar fără utilitare textul se lipea de chenar
                                 (raportat 06.08.2026). --}}
                            <textarea
                                x-model="reason"
                                rows="2"
                                maxlength="255"
                                placeholder="{{ __('panel.actions.annul.reason') }}"
                                class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm leading-5 text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                            ></textarea>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    x-bind:disabled="reason.trim() === ''"
                                    x-on:click="$wire.annulDayGrade({{ $grade['id'] }}, reason); mode = null; reason = '';"
                                    class="inline-flex h-8 items-center rounded-lg bg-danger-600 px-3 text-xs font-semibold text-white transition hover:bg-danger-500 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {{ __('panel.actions.annul.label') }}
                                </button>
                                <button type="button" x-on:click="mode = null" class="text-xs text-gray-500 hover:underline dark:text-gray-400">
                                    {{ __('panel.class_register.day_panel.cancel') }}
                                </button>
                            </div>
                        </div>

                        {{-- CORECȚIE: valoarea propusă + motivul; decizia rămâne a administrației. --}}
                        <div x-show="mode === 'corectie'" x-cloak class="w-full space-y-2 border-t border-gray-200 pt-2 dark:border-white/10">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('panel.actions.request_correction.description') }}</p>
                            <div class="flex flex-wrap items-center gap-2">
                                <input
                                    type="text"
                                    x-model="value"
                                    @if ($panel['numeric']) inputmode="numeric" maxlength="2" placeholder="1–10" @else maxlength="10" placeholder="FB / B / S" @endif
                                    aria-label="{{ __('panel.actions.request_correction.new_value') }}"
                                    class="h-9 w-20 rounded-lg border-0 bg-white text-center text-sm font-semibold text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                />
                                <input
                                    type="text"
                                    x-model="reason"
                                    maxlength="255"
                                    placeholder="{{ __('panel.actions.request_correction.reason') }}"
                                    class="h-9 min-w-40 flex-1 rounded-lg border-0 bg-white px-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                />
                                <button
                                    type="button"
                                    x-bind:disabled="value.trim() === '' || reason.trim() === ''"
                                    x-on:click="$wire.requestDayCorrection({{ $grade['id'] }}, value, reason); mode = null; value = ''; reason = '';"
                                    class="inline-flex h-9 items-center rounded-lg bg-warning-500 px-3 text-xs font-semibold text-white transition hover:bg-warning-400 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-warning-400 dark:text-warning-950"
                                >
                                    {{ __('panel.actions.request_correction.submit') }}
                                </button>
                                <button type="button" x-on:click="mode = null" class="text-xs text-gray-500 hover:underline dark:text-gray-400">
                                    {{ __('panel.class_register.day_panel.cancel') }}
                                </button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

    </section>

    {{-- ── Absențele zilei, pe ore ──────────────────────────────────────────────────────────
         DOAR când payload-ul le aduce: panoul hărții din secțiunea Note e al notelor (fără
         absențe — acelea au harta și borderoul lor), deci nu trimite cheile de absențe și
         secțiunea întreagă dispare. Un partial, două alcătuiri — aceeași înfățișare. --}}
    @if (array_key_exists('absences', $panel))
    <section>
        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
            {{ __('panel.class_register.day_panel.absences_heading') }}
        </h4>

        @if ($panel['absences'] === [])
            <p class="text-gray-500 dark:text-gray-400">{{ __('panel.class_register.day_panel.no_absences') }}</p>
        @else
            <ul class="space-y-2">
                @foreach ($panel['absences'] as $absence)
                    <li class="flex flex-wrap items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5" wire:key="day-abs-{{ $absence['id'] }}">
                        <span @class([
                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1',
                            $statusPalette[$absence['color']] ?? $statusPalette['warning'],
                        ])>
                            {{ $absence['status_label'] }}
                        </span>

                        {{-- Ora absenței, corectabilă la fel ca a notei (07.08.2026). --}}
                        @if (($absence['can_move_hour'] ?? false) && $hourMenu !== [])
                            <select
                                x-on:change="$wire.moveDayAbsenceHour({{ $absence['id'] }}, $event.target.value)"
                                aria-label="{{ __('panel.class_register.day_panel.hour_select_label') }}"
                                title="{{ __('panel.class_register.day_panel.hour_select_label') }}"
                                class="h-7 rounded-md border-0 bg-white py-0 pe-7 ps-2 text-xs text-gray-600 shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/15"
                            >
                                @if ($absence['lesson'] === null)
                                    <option value="" selected>{{ __('panel.forms.absence.lesson_unspecified') }}</option>
                                @endif

                                @foreach ($hourMenu as $slot)
                                    <option value="{{ $slot['hour'] }}" @selected($absence['lesson'] === $slot['hour'])>
                                        {{ $hourOptionLabel($slot, $absence['lesson']) }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <span class="text-xs text-gray-600 dark:text-gray-300">
                                {{ $absence['lesson'] !== null
                                    ? __('panel.forms.absence.lesson_option', ['number' => $absence['lesson']])
                                    : __('panel.forms.absence.lesson_unspecified') }}
                            </span>
                        @endif

                        {{-- Statutul îl fixează DOAR cine are dreptul (diriginte/administrație) —
                             aceleași trei stări, aceleași culori ca în harta absențelor. --}}
                        @if ($rights['can_status'])
                            <span class="ms-auto inline-flex items-center gap-1">
                                @foreach ($statusChoices as $choice)
                                    @if ($choice->value !== $absence['status'])
                                        <button
                                            type="button"
                                            wire:click="setDayAbsenceStatus({{ $absence['id'] }}, '{{ $choice->value }}')"
                                            title="{{ $choice->getLabel() }}"
                                            @class([
                                                'inline-flex h-7 w-7 items-center justify-center rounded-md ring-1 transition',
                                                'text-green-600 ring-green-600/30 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-400/10' => $choice === \App\Enums\AbsenceStatus::Motivated,
                                                'text-red-600 ring-red-600/30 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-400/10' => $choice === \App\Enums\AbsenceStatus::Unmotivated,
                                                'text-amber-600 ring-amber-600/30 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-400/10' => $choice === \App\Enums\AbsenceStatus::Pending,
                                            ])
                                        >
                                            <x-filament::icon :icon="$choice->getIcon()" class="h-4 w-4" />
                                        </button>
                                    @endif
                                @endforeach
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

    </section>
    @endif

    {{-- ── Înregistrare nouă — ora ORDINALĂ (06.08.2026 v2) ────────────────────────────────────
         Ora nu vine din orar: e „a câta consemnare a disciplinei în ziua dată" (Ora 1 = prima
         pereche, Ora 2 = a doua). Prima consemnare se face DIRECT — formularul e deschis pe Ora 1;
         pentru încă una, utilizatorul DESCHIDE ora următoare (butonul de mai jos) și abia acolo
         poate adăuga nota sau absența. O oră poartă o singură consemnare (notă SAU absență) —
         excluderea o impune serverul, indiferent pe unde vine scrierea. --}}
    @if ($canGrade || $canAbsent)
        <section
            wire:key="day-write-{{ $panel['iso'] }}-{{ $busyCount }}-{{ $defaultHour ?? 'plin' }}"
            class="rounded-lg border border-dashed border-gray-300 p-3 dark:border-white/15"
            x-data="{ open: {{ $busyCount === 0 ? 'true' : 'false' }}, v: '', t: 'curenta' }"
        >
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                {{ __('panel.class_register.day_panel.write_heading') }}
            </h4>

            @if ($defaultHour === null)
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('panel.class_register.day_panel.all_hours_taken') }}
                </p>
            @else
                {{-- Ziua are deja consemnări: ora următoare stă ÎNCHISĂ până o deschide
                     utilizatorul — a doua înregistrare e un act deliberat, nu o alunecare. --}}
                <button
                    type="button"
                    x-show="!open"
                    x-cloak
                    x-on:click="open = true"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-white px-3 text-xs font-semibold text-primary-700 ring-1 ring-primary-600/40 transition hover:bg-primary-50 dark:bg-white/5 dark:text-primary-300 dark:ring-primary-400/40 dark:hover:bg-primary-400/10"
                >
                    <x-filament::icon icon="heroicon-o-plus" class="h-4 w-4" />
                    {{ __('panel.class_register.day_panel.open_next_hour', ['number' => $defaultHour]) }}
                </button>

                <div x-show="open" x-cloak class="space-y-2">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('panel.class_register.day_panel.hour_target', ['number' => $defaultHour]) }}
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex h-9 items-center rounded-lg bg-gray-100 px-2.5 text-xs font-semibold text-gray-700 ring-1 ring-gray-950/10 dark:bg-white/10 dark:text-gray-200 dark:ring-white/15">
                            {{ __('panel.forms.absence.lesson_option', ['number' => $defaultHour]) }}
                        </span>

                        @if ($canGrade)
                            <input
                                type="text"
                                x-model="v"
                                x-on:keydown.enter.prevent="if (v.trim() !== '') { $wire.addDayGrade({{ $panel['student']?->getKey() ?? 0 }}, '{{ $panel['iso'] }}', v, t); v = ''; }"
                                @if ($panel['numeric']) inputmode="numeric" maxlength="2" placeholder="1–10" @else maxlength="10" @endif
                                aria-label="{{ __('panel.class_register.new_grade_column') }}"
                                class="h-9 w-16 rounded-lg border-0 bg-white text-center text-sm font-semibold tabular-nums text-gray-950 shadow-sm ring-1 ring-gray-950/10 transition focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                            />

                            @if ($panel['numeric'])
                                <select
                                    x-model="t"
                                    aria-label="{{ __('panel.fields.evaluation_type') }}"
                                    class="h-9 rounded-lg border-0 bg-white py-0 pe-8 ps-2 text-xs text-gray-700 shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-gray-200 dark:ring-white/20"
                                >
                                    @foreach ($panel['grade_types'] as $typeValue => $typeLabel)
                                        <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                                    @endforeach
                                </select>
                            @endif

                            <button
                                type="button"
                                x-on:click="if (v.trim() !== '') { $wire.addDayGrade({{ $panel['student']?->getKey() ?? 0 }}, '{{ $panel['iso'] }}', v, t); v = ''; }"
                                x-bind:disabled="v.trim() === ''"
                                class="inline-flex h-9 items-center rounded-lg bg-primary-600 px-3 text-xs font-semibold text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ __('panel.class_register.day_panel.add_grade_button') }}
                            </button>
                        @endif

                        @if ($canGrade && $canAbsent)
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('panel.class_register.day_panel.write_or') }}</span>
                        @endif

                        @if ($canAbsent)
                            <button
                                type="button"
                                wire:click="addDayAbsence({{ $panel['student']?->getKey() ?? 0 }}, '{{ $panel['iso'] }}')"
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-warning-500 px-3 text-xs font-semibold text-white transition hover:bg-warning-400 dark:bg-warning-400 dark:text-warning-950 dark:hover:bg-warning-300"
                            >
                                <x-filament::icon icon="heroicon-o-user-minus" class="h-4 w-4" />
                                {{ __('panel.class_register.day_panel.add_absence_button') }}
                            </button>
                        @endif
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('panel.class_register.day_panel.write_hint') }}
                    </p>
                </div>
            @endif
        </section>
    @endif
</div>
