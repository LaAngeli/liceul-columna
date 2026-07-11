<?php

namespace App\Filament\Resources\Subjects\Schemas;

use App\Enums\GradingType;
use App\Models\Grade;
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
                    ->maxValue(12),
                TextInput::make('max_grade')
                    ->label(__('panel.forms.subject.max_grade'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12),
                TextInput::make('report_order')
                    ->label(__('panel.forms.subject.report_order_long'))
                    ->numeric(),
            ]);
    }
}
