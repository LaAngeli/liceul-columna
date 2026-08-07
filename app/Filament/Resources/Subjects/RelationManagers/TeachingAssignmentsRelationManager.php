<?php

namespace App\Filament\Resources\Subjects\RelationManagers;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Support\GradeLevels;
use BackedEnum;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ALOCĂRILE disciplinei (profesor ↔ clasă ± grupă) — perechea managerului de pe fișa persoanei
 * ({@see \App\Filament\Resources\Users\RelationManagers\TeachingAssignmentsRelationManager}),
 * văzută dinspre DISCIPLINĂ (cerința beneficiarului, 07.08.2026: „după creare nu mai poți
 * modifica profesorii, clasele" — fișa disciplinei devine editabilă în totalitate).
 *
 * Aceleași reguli, alt punct de intrare: aici disciplina e FIXĂ (fișa deschisă), se aleg clasa
 * și profesorul. Clasele oferite = doar cele de pe treptele MARCATE ale disciplinei; grupa
 * apare doar când disciplina e limba engleză; duplicatul (inclusiv arhivat) e refuzat cu
 * îndrumare spre restaurare. Retragerea = soft delete: notele consemnate rămân (autorul e pe
 * notă), profesorul pierde doar scoping-ul viitor.
 */
class TeachingAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'teachingAssignments';

    protected static string|BackedEnum|null $icon = 'heroicon-o-briefcase';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.resources.teaching_assignments.plural');
    }

    public static function getModelLabel(): ?string
    {
        return __('panel.resources.teaching_assignments.single');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('panel.resources.teaching_assignments.plural');
    }

    /** Fișa disciplinei e a configuratorilor; consultarea alocărilor — a personalului academic. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Subject
            && (auth('web')->user()?->canSeeAcademicData() ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    // DOAR clasele de pe treptele marcate ale disciplinei — o alocare pe o
                    // treaptă nemarcată ar pune clasa pe fișa altui ciclu (scala greșită).
                    ->options(fn (): array => $this->classOptions())
                    ->searchable()
                    ->required()
                    ->rules([
                        // Dublura pe SERVER a filtrării de mai sus (payload forjat).
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if ($this->classOutsideSubjectGrades($value)) {
                                $fail(__('panel.validation.lesson.subject_outside_grade'));
                            }
                        },
                    ]),
                Select::make('teacher_id')
                    ->label(__('panel.fields.teacher'))
                    ->options(fn (): array => Teacher::query()
                        ->orderBy('last_name')->orderBy('first_name')
                        ->get()
                        ->mapWithKeys(fn (Teacher $teacher): array => [(int) $teacher->getKey() => $teacher->full_name])
                        ->all())
                    ->searchable()
                    ->required()
                    ->live()
                    // Anti-duplicat cu mesaj clar (indexul unic vede ȘI alocările arhivate — un
                    // duplicat mergea direct în eroarea SQL; cel ARHIVAT se restaurează, nu se recreează).
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $classId = $get('school_class_id');

                            if (! filled($value) || ! filled($classId)) {
                                return;
                            }

                            $group = $get('english_group');

                            $conflict = TeachingAssignment::withTrashed()
                                ->where('teacher_id', (int) $value)
                                ->where('subject_id', (int) $this->getOwnerRecord()->getKey())
                                ->where('school_class_id', (int) $classId)
                                ->when(
                                    $group !== null && $group !== '',
                                    fn ($query) => $query->where('english_group', (int) $group),
                                    fn ($query) => $query->whereNull('english_group'),
                                )
                                ->first();

                            if ($conflict !== null) {
                                $fail($conflict->trashed()
                                    ? __('panel.validation.teaching_assignment.archived_duplicate')
                                    : __('panel.validation.teaching_assignment.duplicate'));
                            }
                        },
                    ]),
                TextInput::make('english_group')
                    ->label(__('panel.forms.teaching_assignment.english_group'))
                    ->helperText(__('panel.forms.teaching_assignment.english_group_hint'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(9)
                    // Grupa există DOAR la limba engleză — disciplina e fixă aici, deci
                    // vizibilitatea se decide o dată, din fișa deschisă (garda de pe model
                    // o impune oricum, pe orice cale).
                    ->visible(fn (): bool => $this->ownerSubject()?->isEnglishLanguage() ?? false)
                    ->dehydrated(fn (): bool => $this->ownerSubject()?->isEnglishLanguage() ?? false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            // Aceeași lectură ca pe fișa persoanei: perimetrul se citește pe CLASĂ, iar anul stă
            // în descrierea grupului — două clase omonime din ani diferiți nu se pot confunda.
            ->groups([
                $this->classGroup(),
                Group::make('schoolClass.academic_year_id')
                    ->label(__('panel.fields.academic_year'))
                    ->getTitleFromRecordUsing(fn (TeachingAssignment $record): string => (string) ($record->schoolClass?->academicYear->name ?? __('panel.common.dash')))
                    ->titlePrefixedWithLabel(false)
                    ->collapsible(),
            ])
            ->defaultGroup($this->classGroup())
            ->columns([
                // Calea de relație lasă Filament să rezolve accesorul; fișa arhivată (relația
                // null la runtime, deși FK-ul e cert) cade pe placeholder, nu într-o eroare.
                TextColumn::make('teacher.full_name')
                    ->label(__('panel.fields.teacher'))
                    ->placeholder(__('panel.common.dash'))
                    // Grupa de engleză, doar unde există — ca sufix, nu coloană mereu goală.
                    ->description(fn (TeachingAssignment $record): ?string => $record->english_group === null
                        ? null
                        : __('panel.forms.teaching_assignment.english_group').' '.$record->english_group),
                TextColumn::make('deleted_at')
                    ->label(__('panel.fields.status'))
                    ->badge()
                    ->state(fn (TeachingAssignment $record): string => $record->trashed()
                        ? __('panel.resources.teaching_assignments.withdrawn')
                        : __('panel.resources.teaching_assignments.active'))
                    ->color(fn (TeachingAssignment $record): string => $record->trashed() ? 'gray' : 'success'),
            ])
            ->filters([
                TrashedFilter::make()
                    ->visible(fn (): bool => auth('web')->user()?->canConfigureSchool() ?? false),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('panel.forms.teaching_assignment.add'))
                    // Aceeași decizie de flux ca peste tot: creare → revizuire, fără „și încă unul".
                    ->createAnother(false),
            ])
            ->recordActions([
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    /** Gruparea pe CLASĂ — titlul = clasa, descrierea = treapta romană + anul școlar. */
    private function classGroup(): Group
    {
        return Group::make('school_class_id')
            ->label(__('panel.fields.class'))
            ->titlePrefixedWithLabel(false)
            ->getTitleFromRecordUsing(function (TeachingAssignment $record): string {
                $class = $record->schoolClass;

                return $class === null ? __('panel.common.dash') : trim($class->name.' '.($class->section ?? ''));
            })
            ->getDescriptionFromRecordUsing(function (TeachingAssignment $record): ?string {
                $class = $record->schoolClass;

                if ($class === null) {
                    return null;
                }

                return __('panel.fields.class').' '.GradeLevels::roman($class->grade_level)
                    .' · '.$class->academicYear->name;
            })
            ->collapsible();
    }

    private function ownerSubject(): ?Subject
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Subject ? $owner : null;
    }

    /**
     * Clasele de pe treptele MARCATE ale disciplinei, cu anul în etichetă. Un set nedeclarat
     * (nomenclator incomplet) nu limitează — aceeași lectură ca {@see Subject::coversGrade}.
     *
     * @return array<int, string>
     */
    private function classOptions(): array
    {
        $levels = $this->ownerSubject()?->gradeLevelList();

        $classes = SchoolClass::query()
            ->with('academicYear')
            ->when($levels !== null, fn ($query) => $query->whereIn('grade_level', $levels))
            ->orderBy('grade_level')->orderBy('name')->orderBy('section')
            ->get();

        $options = [];

        foreach ($classes as $class) {
            $label = trim($class->name.' '.($class->section ?? ''));
            $year = $class->academicYear?->name;
            $options[(int) $class->getKey()] = $year === null ? $label : "{$label} ({$year})";
        }

        return $options;
    }

    /** Clasa aleasă cade pe o treaptă nemarcată? Dublura pe server a listei filtrate. */
    private function classOutsideSubjectGrades(mixed $classId): bool
    {
        if (! filled($classId) || ! is_numeric($classId)) {
            return false;
        }

        $grade = SchoolClass::query()->whereKey((int) $classId)->value('grade_level');
        $subject = $this->ownerSubject();

        if ($grade === null || $subject === null) {
            return false;
        }

        return ! $subject->coversGrade((int) $grade);
    }
}
