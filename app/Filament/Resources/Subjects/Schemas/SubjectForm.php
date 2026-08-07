<?php

namespace App\Filament\Resources\Subjects\Schemas;

use App\Enums\GradingType;
use App\Enums\SchoolCycle;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Support\GradeLevels;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use WeakMap;

/**
 * Formularul de disciplină, STANDARDIZAT (cerința beneficiarului, 2026-07-21): nimic din ce are
 * o structură cunoscută nu se mai TASTEAZĂ — treptele se aleg din selectoare (I–XII, imposibil
 * de inversat în UI), poziția în foaia matricolă se alege dintre pozițiile VALIDE (unice,
 * contigue — inserarea împinge restul), abrevierea se propune automat din nume. Trei secțiuni
 * logice: identitate → trepte → foaia matricolă. Fiecare regulă de UI are dublura ei pe server
 * (aici + {@see Subject::booted}) — un POST forjat nu poate strecura date invalide.
 */
class SubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.forms.subject.section_identity'))
                    ->description(__('panel.forms.subject.section_identity_hint'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('panel.forms.subject.name'))
                            ->required()
                            ->maxLength(255)
                            // Numele e cheia dicționarelor RU/EN — spus DINAINTE, nu doar la redenumire.
                            ->helperText(__('panel.forms.subject.name_hint'))
                            ->live(onBlur: true)
                            // Abrevierea se PROPUNE din nume (inițialele cuvintelor pline) cât timp
                            // e goală — configuratorul o poate rescrie oricând.
                            ->afterStateUpdated(static function (Get $get, Set $set, ?string $state): void {
                                if (blank($get('abbreviation')) && filled($state)) {
                                    $set('abbreviation', self::suggestAbbreviation($state));
                                }
                            }),
                        TextInput::make('abbreviation')
                            ->label(__('panel.forms.subject.abbreviation'))
                            ->helperText(__('panel.forms.subject.abbreviation_hint'))
                            ->maxLength(30),
                        Select::make('grading_type')
                            ->label(__('panel.forms.subject.grading_type'))
                            ->options(GradingType::class)
                            ->default(GradingType::Numeric->value)
                            ->required()
                            ->native(false)
                            ->helperText(__('panel.forms.subject.grading_type_hint'))
                            // Invariantul „notă SAU calificativ" trăia doar în UI-ul formularului de notă:
                            // comutarea numeric↔calificativ pe o disciplină CU note existente de tip
                            // incompatibil ar lăsa istoricul + mediile într-un mod pe care noul tip nu-l
                            // mai poate exprima. Blocat pe server cât timp există note incompatibile.
                            ->rules([
                                static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    if ($record === null || $value === null) {
                                        return;
                                    }

                                    $newType = $value instanceof GradingType ? $value : GradingType::tryFrom((string) $value);

                                    if ($newType === null || $newType === $record->getAttribute('grading_type')) {
                                        return;
                                    }

                                    $subjectGrades = Grade::withTrashed()->where('subject_id', $record->getKey());

                                    $incompatible = $newType === GradingType::Numeric
                                        ? $subjectGrades->whereNotNull('calificativ')->exists()
                                        : $subjectGrades->whereNotNull('value')->exists();

                                    if ($incompatible) {
                                        $fail(__('panel.validation.subject.grading_type_locked'));
                                    }
                                },
                            ]),
                    ]),

                Section::make(__('panel.forms.subject.section_span'))
                    ->description(__('panel.forms.subject.grade_span_hint'))
                    ->schema([
                        // Treptele se MARCHEAZĂ una câte una (cerința beneficiarului, 07.08.2026)
                        // — nu se mai construiește un interval „De la / Până la". Setul poate avea
                        // goluri (V–IX și XII, fără X–XI), iar debifarea unei trepte cu istoric e
                        // oprită DIN START: opțiunea e dezactivată vizual, ca la rolurile
                        // incompatibile, nu corectată după.
                        CheckboxList::make('grade_levels')
                            ->hiddenLabel()
                            ->validationAttribute(__('panel.forms.subject.grade_span'))
                            ->options(SchoolCycle::gradeLevelOptions())
                            // Pe coloane VERTICALE, treptele curg în ordinea ciclurilor: I–IV
                            // (primar) în prima coloană, V–VIII în a doua, IX–XII în a treia.
                            ->columns(['default' => 2, 'xl' => 3])
                            ->gridDirection('column')
                            ->bulkToggleable()
                            ->required()
                            // Treapta cu istoric (alocări sau note) nu se poate DEBIFA — opțiunea
                            // e blocată cât timp e în setul salvat. Treptele fără istoric rămân
                            // libere în ambele sensuri.
                            ->disableOptionWhen(static fn (string $value, ?Model $record): bool => $record instanceof Subject
                                && in_array((int) $value, self::lockedGrades($record), true))
                            // ⚠️ Regula `in:` implicită ia DOAR opțiunile active — o treaptă
                            // blocată-dar-bifată ar pica la salvare exact pentru că e protejată.
                            // Explicit: orice treaptă din structură e o valoare validă.
                            ->in(array_keys(SchoolCycle::gradeLevelOptions()))
                            ->rules([
                                // Dublura pe SERVER a regulilor din UI (POST forjat): omonimele
                                // stau pe seturi DISJUNCTE (duplicatele legitime — „Matematică"
                                // primar pe calificative / gimnaziu numerică — nu se pot suprapune)
                                // + nicio treaptă cu istoric nu iese din set (coerența cu orarul și
                                // catalogul — dezactivarea din UI e doar confort).
                                static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    $selected = collect(is_array($value) ? $value : [])
                                        ->filter(static fn (mixed $grade): bool => is_numeric($grade))
                                        ->map(static fn (mixed $grade): int => (int) $grade)
                                        ->unique()->sort()->values()->all();

                                    if ($selected === []) {
                                        return; // required() dă mesajul lui
                                    }

                                    $name = trim((string) $get('name'));

                                    if ($name !== '') {
                                        $twins = Subject::query()
                                            ->where('name', $name)
                                            ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                                            ->get();

                                        foreach ($twins as $twin) {
                                            // Set nedeclarat = acoperă tot (aceeași lectură ca la coversGrade).
                                            $shared = array_values(array_intersect(
                                                $selected,
                                                $twin->gradeLevelList() ?? range(SchoolCycle::MIN_GRADE_LEVEL, SchoolCycle::MAX_GRADE_LEVEL),
                                            ));

                                            if ($shared !== []) {
                                                $fail(__('panel.validation.subject.grade_levels_overlap', [
                                                    'grades' => GradeLevels::list($shared),
                                                ]));

                                                return;
                                            }
                                        }
                                    }

                                    if ($record instanceof Subject) {
                                        $blocked = array_values(array_intersect(
                                            self::lockedGrades($record),
                                            array_diff($record->gradeLevelList() ?? range(SchoolCycle::MIN_GRADE_LEVEL, SchoolCycle::MAX_GRADE_LEVEL), $selected),
                                        ));

                                        if ($blocked !== []) {
                                            $fail(__('panel.validation.subject.grade_levels_remove_blocked', [
                                                'grades' => GradeLevels::list($blocked),
                                            ]));
                                        }
                                    }
                                },
                            ]),
                    ]),

                Section::make(__('panel.forms.subject.section_transcript'))
                    ->description(__('panel.forms.subject.section_transcript_hint'))
                    ->schema([
                        // Poziția se ALEGE dintre pozițiile VALIDE (1..N+1) — nu se tastează un
                        // număr arbitrar. Ocupată = inserare (restul se împing); scrierea reală o
                        // face Subject::placeInReportOrder (paginile Create/Edit), de aceea câmpul
                        // NU se dehidratează — nicio stare intermediară cu duplicate.
                        Select::make('report_order')
                            ->label(__('panel.forms.subject.report_order_long'))
                            ->options(static fn (?Model $record): array => self::positionOptions($record))
                            ->default(static fn (): string => (string) Subject::nextReportOrderPosition())
                            ->placeholder(__('panel.forms.subject.report_order_unassigned'))
                            ->native(false)
                            ->dehydrated(false)
                            ->helperText(__('panel.forms.subject.report_order_hint')),
                    ]),
            ]);
    }

    /**
     * Abrevierea propusă: inițialele cuvintelor pline („Educația fizică și sportul" → „EFS"),
     * sau primele 4 litere la un singur cuvânt („Matematica" → „MAT").
     */
    public static function suggestAbbreviation(string $name): string
    {
        $words = preg_split('/[\s\-\/]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $full = array_values(array_filter($words, static fn (string $word): bool => mb_strlen($word) > 2));

        if (count($full) >= 2) {
            return mb_strtoupper(implode('', array_map(static fn (string $word): string => mb_substr($word, 0, 1), $full)));
        }

        $base = $full[0] ?? ($words[0] ?? '');

        return $base === '' ? '' : mb_strtoupper(Str::ascii(mb_substr($base, 0, mb_strlen($base) === 3 ? 3 : 4)));
    }

    /**
     * Pozițiile VALIDE din foaia matricolă, etichetate cu contextul real („3 — înaintea:
     * Matematică"), plus „la sfârșit". Recordul editat e exclus din context (el se mută).
     * Cheile numerice devin int în PHP — Filament/Livewire le potrivesc lejer cu starea.
     *
     * @return array<int, string>
     */
    private static function positionOptions(?Model $record): array
    {
        $ordered = Subject::query()
            ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->whereNotNull('report_order')
            ->orderBy('report_order')
            ->orderBy('name')
            ->pluck('name')
            ->values();

        $options = [];

        foreach ($ordered as $index => $name) {
            $options[$index + 1] = (string) __('panel.forms.subject.report_order_before', [
                'position' => $index + 1,
                'subject' => $name,
            ]);
        }

        $last = count($ordered) + 1;
        $options[$last] = (string) __('panel.forms.subject.report_order_last', ['position' => $last]);

        return $options;
    }

    /**
     * Treptele BLOCATE la debifare pe disciplina editată: cele din setul salvat care au deja
     * istoric — alocări didactice sau note (prin treapta clasei). Scoase din set, orarul,
     * catalogul și mediile de acolo ar rămâne legate de o disciplină care „nu se mai predă"
     * la clasa lor.
     *
     * O treaptă cu istoric aflată ÎN AFARA setului salvat (stare moștenită) NU se blochează —
     * ea nu e bifată, iar dezactivarea ei ar împiedica exact re-adăugarea care ar repara datele.
     *
     * Memoizat pe INSTANȚA recordului (WeakMap): `disableOptionWhen` evaluează închiderea pentru
     * fiecare din cele 12 opțiuni — fără memoizare ar însemna 24 de interogări la o singură
     * randare. NU pe id: sub RefreshDatabase id-urile se refolosesc între teste, iar un cache
     * static pe id ar servi istoricul altei discipline (leak clasic de proces lung).
     *
     * @return list<int>
     */
    private static function lockedGrades(Subject $record): array
    {
        /** @var WeakMap<Subject, list<int>>|null $cache */
        static $cache = null;

        $cache ??= new WeakMap;

        if (isset($cache[$record])) {
            return $cache[$record];
        }

        $key = (int) $record->getKey();

        $assignmentGrades = TeachingAssignment::query()
            ->where('subject_id', $key)
            ->join('school_classes', 'school_classes.id', '=', 'teaching_assignments.school_class_id')
            ->whereNotNull('school_classes.grade_level')
            ->distinct()
            ->pluck('school_classes.grade_level');

        $gradeGrades = Grade::withTrashed()
            ->where('grades.subject_id', $key)
            ->join('school_classes', 'school_classes.id', '=', 'grades.school_class_id')
            ->whereNotNull('school_classes.grade_level')
            ->distinct()
            ->pluck('school_classes.grade_level');

        $history = $assignmentGrades->merge($gradeGrades)
            ->map(static fn ($grade): int => (int) $grade)
            ->unique()->sort()->values()->all();

        return $cache[$record] = array_values(array_intersect(
            $record->gradeLevelList() ?? range(SchoolCycle::MIN_GRADE_LEVEL, SchoolCycle::MAX_GRADE_LEVEL),
            $history,
        ));
    }
}
