<?php

namespace App\Filament\Resources\SummativeDesignations\Schemas;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Support\ContentTranslator;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class SummativeDesignationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_class_id')
                    ->label(__('grading.designation.fields.class'))
                    // Primarul (I–IV) nu are notă sumativă semestrială → doar gimnaziu/liceu (≥ 5).
                    ->relationship('schoolClass', 'name', fn (Builder $query): Builder => $query->where('grade_level', '>=', 5))
                    ->getOptionLabelFromRecordUsing(fn (SchoolClass $record): string => trim($record->name.' '.($record->section ?? '')))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('subject_id', null)),

                Select::make('subject_id')
                    ->label(__('grading.designation.fields.subject'))
                    // FĂRĂ `relationship()`: pe un câmp `searchable()` relația preia rezolvarea
                    // opțiunilor și filtrul de mai jos rămâne fără efect (aceeași capcană Filament
                    // reparată la orarul structurat). Eticheta valorii salvate vine separat.
                    ->options(fn (Get $get): array => self::subjectOptions($get('school_class_id')))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::subjectLabel($value))
                    ->helperText(fn (Get $get): ?string => $get('school_class_id') === null
                        ? (string) __('grading.designation.pick_class_first')
                        : null)
                    ->searchable()
                    ->required()
                    // Unic pe (disciplină × clasă): o disciplină nu se designează de două ori la aceeași clasă.
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('school_class_id', $get('school_class_id')),
                    ),

                TextInput::make('order_reference')
                    ->label(__('grading.designation.fields.order_reference'))
                    ->maxLength(255)
                    ->helperText(__('grading.designation.help')),
            ]);
    }

    /**
     * Disciplinele valabile pe treapta clasei alese. Nomenclatorul are câte o fișă per ciclu pentru
     * zece denumiri („Matematică" I–IV și V–XII); a designa sumativa pe fișa ciclului greșit ar lega
     * regula de o disciplină pe care clasa nici n-o studiază.
     *
     * @return array<int, string>
     */
    private static function subjectOptions(mixed $classId): array
    {
        if (! is_numeric($classId)) {
            return [];
        }

        $grade = SchoolClass::query()->whereKey((int) $classId)->value('grade_level');

        if ($grade === null) {
            return [];
        }

        return Subject::query()
            ->where(fn (Builder $q): Builder => $q->whereNull('min_grade')->orWhere('min_grade', '<=', $grade))
            ->where(fn (Builder $q): Builder => $q->whereNull('max_grade')->orWhere('max_grade', '>=', $grade))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Subject $subject): array => [$subject->id => ContentTranslator::subject($subject->name)])
            ->all();
    }

    /** Eticheta valorii deja salvate, chiar dacă fișa a ieșit între timp din intervalul treptei. */
    private static function subjectLabel(mixed $value): ?string
    {
        $name = Subject::query()->whereKey($value)->value('name');

        return $name === null ? null : ContentTranslator::subject($name);
    }
}
