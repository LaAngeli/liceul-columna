<?php

namespace App\Filament\Resources\Teachers\RelationManagers;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Policies\TeachingAssignmentPolicy;
use App\Support\ContentTranslator;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ALOCĂRILE profesorului (clasă ↔ disciplină ± grupă engleză) — până acum NU exista nicio cale
 * în panou de a aloca un profesor: o disciplină/clasă nouă era o fundătură (profesorii nu puteau
 * preda/nota niciodată la ea fără tinker/import). Alocarea e fundamentul scoping-ului catalogului
 * ({@see Teacher::canGradeClassSubject}); scrierea = configuratori (§3.3, prin
 * {@see TeachingAssignmentPolicy} — acțiunile Filament se autorizează prin Gate).
 */
class TeachingAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'teachingAssignments';

    protected static string|BackedEnum|null $icon = 'heroicon-o-briefcase';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.resources.teaching_assignments.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth('web')->user()?->canSeeAcademicData() ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->options(fn (): array => self::classOptions())
                    ->searchable()
                    ->required(),
                Select::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    ->options(fn (): array => self::subjectOptions())
                    ->searchable()
                    ->required()
                    // Anti-duplicat cu mesaj clar (indexul unic vede ȘI alocările arhivate — un
                    // duplicat mergea direct în eroarea SQL; cel ARHIVAT se restaurează, nu se recreează).
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $classId = $get('school_class_id');

                            if (! $value || ! $classId) {
                                return;
                            }

                            $group = $get('english_group');
                            $ownerTeacherId = (int) $this->getOwnerRecord()->getKey();

                            $conflict = TeachingAssignment::withTrashed()
                                ->where('teacher_id', $ownerTeacherId)
                                ->where('subject_id', (int) $value)
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
                    ->maxValue(9),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('schoolClass.name')
                    ->label(__('panel.fields.class'))
                    ->formatStateUsing(function (TeachingAssignment $record): string {
                        $class = $record->schoolClass;

                        return $class === null ? '—' : trim($class->name.' '.($class->section ?? ''));
                    })
                    ->sortable(),
                TextColumn::make('subject.name')
                    ->label(__('panel.fields.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : ContentTranslator::subject($state))
                    ->sortable(),
                TextColumn::make('english_group')
                    ->label(__('panel.forms.teaching_assignment.english_group'))
                    ->placeholder(__('panel.common.dash'))
                    ->toggleable(),
            ])
            ->filters([
                TrashedFilter::make()
                    ->visible(fn (): bool => ($user = auth('web')->user()) instanceof User && $user->canConfigureSchool()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('panel.forms.teaching_assignment.add')),
            ])
            ->recordActions([
                // Retragerea alocării = soft delete: notele consemnate rămân (autorul e pe notă,
                // nu pe alocare); profesorul pierde doar scoping-ul (clasa, disciplina) pe viitor.
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function classOptions(): array
    {
        $options = [];

        foreach (SchoolClass::query()->with('academicYear')->orderBy('grade_level')->orderBy('name')->get() as $class) {
            $label = trim($class->name.' '.($class->section ?? ''));
            $year = $class->academicYear?->name;
            $options[$class->id] = $year === null ? $label : "{$label} ({$year})";
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private static function subjectOptions(): array
    {
        $options = [];

        foreach (Subject::query()->orderBy('name')->get() as $subject) {
            $options[$subject->id] = ContentTranslator::subject($subject->name);
        }

        return $options;
    }
}
