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
    $rights = $panel['rights'];
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
                    <li class="flex flex-wrap items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                        <span @class([
                            'inline-flex h-7 w-9 items-center justify-center rounded text-sm font-semibold tabular-nums ring-1',
                            'bg-primary-50 text-primary-700 ring-primary-600/30 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/30' => $grade['weighted'] && ! $grade['annulled'],
                            'bg-white text-gray-800 ring-gray-950/10 dark:bg-white/10 dark:text-gray-200 dark:ring-white/15' => ! $grade['weighted'] && ! $grade['annulled'],
                            'bg-gray-100 text-gray-400 line-through ring-gray-950/10 dark:bg-white/5 dark:text-gray-500 dark:ring-white/10' => $grade['annulled'],
                        ])>{{ $grade['value'] }}</span>

                        <span class="text-xs text-gray-600 dark:text-gray-300">{{ $grade['type_label'] }}</span>

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
                                    wire:click="mountAction('requestGradeCorrection', { id: {{ $grade['id'] }} })"
                                    class="rounded-md px-2 py-1 text-xs font-medium text-warning-600 hover:bg-warning-50 dark:text-warning-400 dark:hover:bg-warning-400/10"
                                >
                                    {{ __('panel.actions.request_correction.label') }}
                                </button>
                            @endif

                            @if ($grade['can_annul'])
                                <button
                                    type="button"
                                    wire:click="mountAction('annulGrade', { id: {{ $grade['id'] }} })"
                                    class="rounded-md px-2 py-1 text-xs font-medium text-danger-600 hover:bg-danger-50 dark:text-danger-400 dark:hover:bg-danger-400/10"
                                >
                                    {{ __('panel.actions.annul.label') }}
                                </button>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ── Absențele zilei, pe ore ──────────────────────────────────────────────────────── --}}
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

                        <span class="text-xs text-gray-600 dark:text-gray-300">
                            {{ $absence['lesson'] !== null
                                ? __('panel.forms.absence.lesson_option', ['number' => $absence['lesson']])
                                : __('panel.forms.absence.lesson_unspecified') }}
                        </span>

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

        {{-- Consemnarea unei absențe NOI pe ora aleasă — calea spre „a lipsit la AMBELE ore".
             Orele din orar vin ca sugestii-pastilă; „fără oră" rămâne pentru consemnarea rapidă.
             O oră deja consemnată se arată dezactivată, nu ascunsă: se vede că e luată. --}}
        @if ($panel['can_absent'])
            <div class="mt-3 rounded-lg border border-dashed border-gray-300 p-3 dark:border-white/15">
                <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ __('panel.class_register.day_panel.add_absence_heading') }}
                </p>
                <div class="flex flex-wrap items-center gap-1.5">
                    @foreach ($panel['hours']['timetable'] as $hour)
                        @php($taken = in_array($hour, $panel['hours']['taken'], true))
                        <button
                            type="button"
                            @disabled($taken)
                            wire:click="addDayAbsence({{ $panel['student']?->getKey() ?? 0 }}, '{{ $panel['iso'] }}', {{ $hour }})"
                            @class([
                                'inline-flex h-8 items-center rounded-md px-2.5 text-xs font-semibold ring-1 transition',
                                'cursor-not-allowed bg-gray-100 text-gray-400 ring-gray-950/5 dark:bg-white/5 dark:text-gray-600 dark:ring-white/10' => $taken,
                                'bg-white text-gray-700 ring-gray-950/10 hover:bg-warning-50 hover:text-warning-700 hover:ring-warning-600/30 dark:bg-white/5 dark:text-gray-200 dark:ring-white/15 dark:hover:bg-warning-400/10' => ! $taken,
                            ])
                        >
                            {{ __('panel.forms.absence.lesson_option', ['number' => $hour]) }}
                        </button>
                    @endforeach

                    {{-- Orele din afara orarului (orar absent sau incomplet) — un select discret. --}}
                    <select
                        x-data
                        x-on:change="if ($el.value !== '') { $wire.addDayAbsence({{ $panel['student']?->getKey() ?? 0 }}, '{{ $panel['iso'] }}', $el.value === 'null' ? null : parseInt($el.value)); $el.value = ''; }"
                        class="h-8 rounded-md border-0 bg-white py-0 pe-8 ps-2 text-xs text-gray-600 shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/15"
                    >
                        <option value="">{{ __('panel.class_register.day_panel.other_hour') }}</option>
                        <option value="null">{{ __('panel.forms.absence.lesson_unspecified') }}</option>
                        @foreach (range(1, 8) as $hour)
                            @unless (in_array($hour, $panel['hours']['timetable'], true))
                                <option value="{{ $hour }}" @disabled(in_array($hour, $panel['hours']['taken'], true))>
                                    {{ __('panel.forms.absence.lesson_option', ['number' => $hour]) }}
                                </option>
                            @endunless
                        @endforeach
                    </select>
                </div>
            </div>
        @endif
    </section>
</div>
