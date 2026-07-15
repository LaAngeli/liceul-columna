<?php

namespace App\Filament\Resources\HomeworkAssignments\Schemas;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Support\ContentTranslator;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class HomeworkAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    ->options(fn (): array => self::subjectOptions())
                    // Venind din navigatorul de catalog, contextul pre-completează formularul —
                    // DOAR dacă e printre opțiunile permise rolului (un id străin e ignorat).
                    ->default(fn (): ?int => self::requestedContextSubjectId())
                    ->searchable()
                    ->required(),
                Select::make('grade_level')
                    ->label(__('panel.forms.homework.class_level'))
                    ->options(fn (): array => self::gradeLevelOptions())
                    // Clasa din context se traduce în treaptă + literă (modelul temelor).
                    ->default(fn (): ?int => self::requestedContextClass()?->grade_level)
                    ->required(),
                TextInput::make('section')
                    ->label(__('panel.forms.homework.section'))
                    ->helperText(__('panel.forms.homework.section_hint'))
                    ->default(fn (): ?string => self::requestedContextClass()?->section)
                    ->maxLength(4)
                    // Litera goală = toată treapta (valid). Dacă e completată, TREBUIE să existe o clasă
                    // (treaptă, literă) — altfel tema nu ajunge la nicio clasă și profesorul nu află (no-op
                    // tăcut, audit-teme #6). O respingem cu mesaj clar în loc s-o salvăm inutil.
                    ->rules([
                        static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $section = is_string($value) ? trim($value) : '';
                            $gradeLevel = $get('grade_level');

                            if ($section === '' || $gradeLevel === null || $gradeLevel === '') {
                                return;
                            }

                            $exists = SchoolClass::query()
                                ->where('grade_level', (int) $gradeLevel)
                                ->where('section', $section)
                                ->exists();

                            if (! $exists) {
                                $fail(__('panel.forms.homework.section_not_found', ['section' => $section]));
                            }
                        },
                    ]),
                DatePicker::make('assigned_on')
                    ->label(__('panel.fields.date'))
                    ->required()
                    ->default(now()),
                Textarea::make('topic')
                    ->label(__('panel.forms.homework.topic'))
                    ->rows(2)
                    ->columnSpanFull(),
                Textarea::make('required_task')
                    ->label(__('panel.forms.homework.required_task'))
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('optional_task')
                    ->label(__('panel.forms.homework.optional_task'))
                    ->rows(2)
                    ->columnSpanFull(),
                Repeater::make('links')
                    ->label(__('panel.forms.homework.links'))
                    ->simple(
                        TextInput::make('url')
                            ->url()
                            ->placeholder('https://…')
                    )
                    ->addActionLabel(fn (): string => __('panel.forms.homework.add_link'))
                    ->columnSpanFull(),
                Hidden::make('teacher_id')
                    ->default(fn (): ?int => auth('web')->user()?->teacher?->id),
            ]);
    }

    private static function currentTeacher(): ?Teacher
    {
        $user = auth('web')->user();

        return ($user && ! $user->isAdministrator()) ? $user->teacher : null;
    }

    /**
     * Clasa de context primită în query string (din navigatorul de catalog) — DOAR dacă rolul o
     * poate vedea (profesorul: clasele lui; administrația: oricare). Altfel null.
     */
    private static function requestedContextClass(): ?SchoolClass
    {
        $raw = request()->query('clasa');

        if (! is_string($raw) || ! ctype_digit($raw)) {
            return null;
        }

        $id = (int) $raw;

        if (($teacher = self::currentTeacher()) !== null
            && ! in_array($id, $teacher->visibleSchoolClassIds(), true)) {
            return null;
        }

        return SchoolClass::query()->find($id);
    }

    /** Disciplina de context, acceptată doar dintre opțiunile permise rolului. */
    private static function requestedContextSubjectId(): ?int
    {
        $raw = request()->query('disciplina');

        if (! is_string($raw) || ! ctype_digit($raw)) {
            return null;
        }

        $id = (int) $raw;

        return array_key_exists($id, self::subjectOptions()) ? $id : null;
    }

    /**
     * Profesorul pur vede doar disciplinele lui; dirigintele și administrația, toate.
     *
     * @return array<int, string>
     */
    private static function subjectOptions(): array
    {
        $query = Subject::query()->orderBy('name');

        $teacher = self::currentTeacher();
        if ($teacher && $teacher->homeroomSchoolClassIds() === []) {
            $query->whereKey($teacher->taughtSubjectIds());
        }

        $options = [];
        foreach ($query->get() as $subject) {
            $options[$subject->id] = ContentTranslator::subject($subject->name);
        }

        return $options;
    }

    /**
     * Treptele claselor pe care le acoperă profesorul (administrația: 1-12).
     *
     * @return array<int, int>
     */
    private static function gradeLevelOptions(): array
    {
        $teacher = self::currentTeacher();

        if (! $teacher) {
            return array_combine(range(1, 12), range(1, 12));
        }

        $levels = SchoolClass::query()
            ->whereKey($teacher->visibleSchoolClassIds())
            ->orderBy('grade_level')
            ->pluck('grade_level', 'grade_level')
            ->all();

        /** @var array<int, int> $levels */
        return $levels;
    }
}
