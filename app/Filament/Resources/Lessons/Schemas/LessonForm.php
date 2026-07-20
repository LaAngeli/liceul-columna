<?php

namespace App\Filament\Resources\Lessons\Schemas;

use App\Enums\Weekday;
use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Observers\LessonObserver;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class LessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Clasa e alegerea de bază: anul și disciplinele posibile decurg din ea.
                Select::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->relationship('schoolClass', 'name')
                    ->getOptionLabelFromRecordUsing(fn (SchoolClass $record): string => trim($record->name.' '.($record->section ?? '')))
                    // Din orarul unei clase (navigator), contextul pre-completează clasa — validat
                    // (id străin = ignorat), nu preluat orbește.
                    ->default(fn (): ?int => self::contextClass()?->getKey())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    // Disciplina aleasă pentru clasa veche n-are de ce să rămână validă la alta.
                    ->afterStateUpdated(fn (Set $set): mixed => $set('subject_id', null)),
                Select::make('academic_year_id')
                    ->label(__('panel.fields.academic_year'))
                    ->relationship('academicYear', 'name')
                    // DERIVAT din clasă, nu ales: clasa aparține deja unui an, iar două câmpuri
                    // independente însemnau că un slot poate ajunge într-un an în care clasa lui
                    // nici nu exista — lecția dispărea atunci din calculul riscului de amânare,
                    // fără niciun semnal. Invariantul e impus și pe model ({@see LessonObserver}),
                    // pentru căile de scriere care nu trec prin formular.
                    ->default(function (): ?int {
                        $class = self::contextClass();

                        return $class !== null
                            ? (int) $class->academic_year_id
                            : AcademicYear::query()->latest('id')->value('id');
                    })
                    ->disabled()
                    ->dehydrated()
                    ->required(),
                Select::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    // FĂRĂ `relationship()`, deliberat: pe un câmp `searchable()` relația preia
                    // rezolvarea opțiunilor și `options()` nu mai are efect — căutarea returna tot
                    // nomenclatorul (prins la verificarea live: „Chimie" apărea la clasa a II-a).
                    // Eticheta valorii salvate vine din `getOptionLabelUsing`, ca fișele istorice
                    // din afara intervalului să se vadă la editare, nu să apară goale.
                    ->getOptionLabelUsing(fn (mixed $value): ?string => Subject::query()->whereKey($value)->value('name'))
                    // Doar disciplinele valabile pe treapta clasei: nomenclatorul are câte o fișă
                    // per ciclu pentru zece denumiri („Matematică" [1-4] și [5-12]), iar alegerea
                    // celei greșite leagă lecția de disciplina altui ciclu — exact defectul care a
                    // afectat 219 din 507 lecții la import, reintrodus altfel manual.
                    ->options(fn (Get $get): array => self::subjectOptions($get('school_class_id')))
                    ->helperText(fn (Get $get): ?string => $get('school_class_id') === null
                        ? (string) __('panel.forms.lesson.pick_class_first')
                        : null)
                    ->searchable()
                    ->required(),
                Select::make('teacher_id')
                    ->label(__('panel.forms.lesson.teacher_short'))
                    ->relationship('teacher', 'last_name')
                    ->getOptionLabelFromRecordUsing(fn (Teacher $record): string => $record->full_name)
                    ->searchable()
                    ->preload()
                    ->live()
                    // AVERTISMENT, nu respingere: același profesor în același interval la două clase
                    // e uneori real (grupe comasate între clase paralele). Semnalăm suprapunerea și
                    // lăsăm decizia omului care cunoaște cazul.
                    ->helperText(fn (Get $get, ?Lesson $record): ?string => self::conflictWarning($get, $record, 'teacher_id', 'clash_teacher')),
                Select::make('day_of_week')
                    ->label(__('panel.forms.lesson.weekday'))
                    ->options(Weekday::class)
                    ->required()
                    ->live(),
                Select::make('lesson_number')
                    ->label(__('panel.forms.lesson.period_with_no'))
                    ->options(array_combine(range(1, 8), array_map(fn (int $n): string => (string) $n, range(1, 8))))
                    ->required()
                    ->live()
                    // Slotul e unic pe (clasă, an, zi, nr.) în baza de date. Fără regula asta,
                    // suprapunerea ieșea ca eroare de constrângere — pagină de eroare, nu mesaj.
                    ->rule(fn (Get $get, ?Lesson $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        if (self::slotTaken($get, $record, (int) $value)) {
                            $fail(__('panel.forms.lesson.slot_taken'));
                        }
                    }),
                TextInput::make('room')
                    ->label(__('panel.forms.lesson.room'))
                    ->maxLength(20)
                    ->live(onBlur: true)
                    ->helperText(fn (Get $get, ?Lesson $record): ?string => self::conflictWarning($get, $record, 'room', 'clash_room')),
            ]);
    }

    /**
     * Suprapunerea pe profesor sau pe sală în același interval — text de avertizare cu clasele
     * implicate, sau null când nu e nimic de semnalat.
     *
     * De ce avertisment și nu validare: două lecții pot împărți legitim profesorul (grupe comasate
     * din clase paralele) sau sala (jumătăți de clasă la aceeași disciplină). O interdicție ar bloca
     * orare corecte; tăcerea ar ascunde greșeli de tastare. Semnalul informează, omul decide.
     */
    private static function conflictWarning(Get $get, ?Lesson $record, string $field, string $key): ?string
    {
        $value = $get($field);
        $day = $get('day_of_week');
        $number = $get('lesson_number');
        $classId = $get('school_class_id');

        if ($value === null || $value === '' || $day === null || $day === '' || ! is_numeric($number)) {
            return null;
        }

        $yearId = is_numeric($classId)
            ? SchoolClass::query()->whereKey((int) $classId)->value('academic_year_id')
            : null;

        $clashes = Lesson::query()
            ->where($field, $value)
            ->where('day_of_week', $day)
            ->where('lesson_number', (int) $number)
            // Doar în anul clasei: un slot din alt an școlar nu se suprapune cu acesta.
            ->when($yearId !== null, fn (Builder $q): Builder => $q->where('academic_year_id', $yearId))
            ->when(is_numeric($classId), fn (Builder $q): Builder => $q->where('school_class_id', '!=', (int) $classId))
            ->when($record?->exists, fn (Builder $q): Builder => $q->whereKeyNot($record?->getKey()))
            ->with('schoolClass')
            ->get();

        if ($clashes->isEmpty()) {
            return null;
        }

        $classes = $clashes
            ->map(fn (Lesson $lesson): string => trim(($lesson->schoolClass->name ?? '?').' '.($lesson->schoolClass->section ?? '')))
            ->unique()
            ->implode(', ');

        return (string) __('panel.forms.lesson.'.$key, ['classes' => $classes]);
    }

    /**
     * Disciplinele valabile pe treapta clasei alese. Fără clasă — listă goală, nu tot nomenclatorul:
     * o listă completă invită exact la alegerea greșită.
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
            ->pluck('name', 'id')
            ->all();
    }

    /** Există deja un alt slot pe aceeași (clasă, zi, nr. lecție)? */
    private static function slotTaken(Get $get, ?Lesson $record, int $lessonNumber): bool
    {
        $classId = $get('school_class_id');
        $day = $get('day_of_week');

        if (! is_numeric($classId) || $day === null || $day === '') {
            return false;
        }

        return Lesson::query()
            ->where('school_class_id', (int) $classId)
            ->where('day_of_week', $day)
            ->where('lesson_number', $lessonNumber)
            ->when($record?->exists, fn (Builder $q): Builder => $q->whereKeyNot($record?->getKey()))
            ->exists();
    }

    /** Clasa din contextul navigatorului (`?clasa=`), doar dacă există. */
    private static function contextClass(): ?SchoolClass
    {
        $raw = request()->query('clasa');

        if (! is_string($raw) || ! ctype_digit($raw)) {
            return null;
        }

        return SchoolClass::query()->whereKey((int) $raw)->first();
    }
}
