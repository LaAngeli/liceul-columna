<?php

namespace App\Filament\Resources\Subjects\Schemas;

use App\Enums\GradingType;
use App\Models\Grade;
use App\Models\Subject;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class SubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('panel.forms.subject.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('abbreviation')
                    ->label(__('panel.forms.subject.abbreviation'))
                    ->maxLength(30),
                Select::make('grading_type')
                    ->label(__('panel.forms.subject.grading_type'))
                    ->options(GradingType::class)
                    ->default(GradingType::Numeric->value)
                    ->required()
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
                TextInput::make('min_grade')
                    ->label(__('panel.forms.subject.min_grade'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12)
                    ->required()
                    ->live(onBlur: true),
                TextInput::make('max_grade')
                    ->label(__('panel.forms.subject.max_grade'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12)
                    ->required()
                    ->rules([
                        // AUDIT 2026-07-15: intervalul de trepte se putea INVERSA (min 9, max 5) și
                        // NIMIC nu împiedica două discipline active cu același nume pe trepte
                        // suprapuse — duplicatele legitime (Matematică 1-4 calificativ / 5-12
                        // numerică) devin ambigue la alocări dacă intervalele se ating.
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

                            if ($name === '') {
                                return;
                            }

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
                            }
                        },
                    ]),
                TextInput::make('report_order')
                    ->label(__('panel.forms.subject.report_order_long'))
                    ->numeric(),
            ]);
    }
}
