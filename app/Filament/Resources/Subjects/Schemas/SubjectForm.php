<?php

namespace App\Filament\Resources\Subjects\Schemas;

use App\Enums\GradingType;
use App\Enums\SchoolCycle;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
                    ->columns(2)
                    ->schema([
                        // Treptele se ALEG, nu se tastează (standardizare 2026-07-21): doar clasele
                        // I–XII din structura școlii; „Până la" oferă numai trepte ≥ „De la", deci
                        // intervalul nu se poate inversa din interfață.
                        Select::make('min_grade')
                            ->label(__('panel.forms.subject.min_grade'))
                            ->options(SchoolCycle::gradeLevelOptions())
                            ->required()
                            ->native(false)
                            ->live()
                            // Treapta de start mutată peste finalul curent → finalul se golește
                            // (nu păstrăm tăcut un interval invalid în formular).
                            ->afterStateUpdated(static function (Get $get, Set $set, mixed $state): void {
                                $min = is_numeric($state) ? (int) $state : null;
                                $max = is_numeric($maxRaw = $get('max_grade')) ? (int) $maxRaw : null;

                                if ($min !== null && $max !== null && $max < $min) {
                                    $set('max_grade', null);
                                }
                            }),
                        Select::make('max_grade')
                            ->label(__('panel.forms.subject.max_grade'))
                            ->options(static function (Get $get): array {
                                $options = SchoolCycle::gradeLevelOptions();
                                $min = is_numeric($minRaw = $get('min_grade')) ? (int) $minRaw : null;

                                return $min === null
                                    ? $options
                                    : array_filter($options, static fn (int $grade): bool => $grade >= $min, ARRAY_FILTER_USE_KEY);
                            })
                            ->required()
                            ->native(false)
                            ->live()
                            ->rules([
                                // Dublura pe SERVER a regulilor din UI (POST forjat): interval
                                // nerăsturnat + fără suprapunere cu o altă disciplină omonimă
                                // (duplicatele legitime — „Matematică" primar calificativ / gimnaziu
                                // numerică — trăiesc DOAR pe intervale disjuncte) + fără ÎNGUSTARE
                                // peste istoricul existent (alocări/note pe trepte care ar ieși din
                                // interval — coerența cu orarul și catalogul).
                                static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    $max = is_numeric($value) ? (int) $value : null;
                                    $min = is_numeric($minRaw = $get('min_grade')) ? (int) $minRaw : null;

                                    if ($max === null || $min === null) {
                                        return;
                                    }

                                    if ($max < $min) {
                                        $fail(__('panel.validation.subject.grade_span_inverted'));

                                        return;
                                    }

                                    $name = trim((string) $get('name'));

                                    if ($name !== '') {
                                        $conflict = Subject::query()
                                            ->where('name', $name)
                                            ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                                            ->whereNotNull('min_grade')
                                            ->whereNotNull('max_grade')
                                            ->where('min_grade', '<=', $max)
                                            ->where('max_grade', '>=', $min)
                                            ->first();

                                        if ($conflict !== null) {
                                            $fail(__('panel.validation.subject.grade_span_overlap', [
                                                'min' => $conflict->min_grade,
                                                'max' => $conflict->max_grade,
                                            ]));

                                            return;
                                        }
                                    }

                                    if ($record !== null) {
                                        self::failIfNarrowingOverHistory($record, $min, $max, $fail);
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
     * Îngustarea intervalului pe o disciplină CU istoric: treptele care ar ieși din interval și
     * au deja alocări didactice sau note sunt blocate — orarul, catalogul și mediile de acolo ar
     * rămâne legate de o disciplină care „nu se mai predă" la clasa lor.
     */
    private static function failIfNarrowingOverHistory(Model $record, int $min, int $max, Closure $fail): void
    {
        $assignmentGrades = TeachingAssignment::query()
            ->where('subject_id', $record->getKey())
            ->join('school_classes', 'school_classes.id', '=', 'teaching_assignments.school_class_id')
            ->whereNotNull('school_classes.grade_level')
            ->where(fn ($query) => $query
                ->where('school_classes.grade_level', '<', $min)
                ->orWhere('school_classes.grade_level', '>', $max))
            ->distinct()
            ->pluck('school_classes.grade_level');

        $gradeGrades = Grade::withTrashed()
            ->where('grades.subject_id', $record->getKey())
            ->join('school_classes', 'school_classes.id', '=', 'grades.school_class_id')
            ->whereNotNull('school_classes.grade_level')
            ->where(fn ($query) => $query
                ->where('school_classes.grade_level', '<', $min)
                ->orWhere('school_classes.grade_level', '>', $max))
            ->distinct()
            ->pluck('school_classes.grade_level');

        $outside = $assignmentGrades->merge($gradeGrades)->unique()->sort()->values();

        if ($outside->isNotEmpty()) {
            $fail(__('panel.validation.subject.grade_span_narrow_blocked', [
                'grades' => $outside
                    ->map(fn ($grade): string => SchoolCycle::romanNumeral((int) $grade))
                    ->implode(', '),
            ]));
        }
    }
}
