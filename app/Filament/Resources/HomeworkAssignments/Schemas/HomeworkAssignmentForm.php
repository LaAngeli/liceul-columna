<?php

namespace App\Filament\Resources\HomeworkAssignments\Schemas;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Support\ContentTranslator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                    ->searchable()
                    ->required(),
                Select::make('grade_level')
                    ->label(__('panel.forms.homework.class_level'))
                    ->options(fn (): array => self::gradeLevelOptions())
                    ->required(),
                TextInput::make('section')
                    ->label(__('panel.forms.homework.section'))
                    ->maxLength(4),
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
