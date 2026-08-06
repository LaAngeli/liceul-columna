<?php

namespace App\Filament\Resources\Users\RelationManagers;

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
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ALOCĂRILE contului pedagogic (clasă ↔ disciplină ± grupă) — mutate pe fișa persoanei din
 * Utilizatori (consolidarea Profesori→Utilizatori, 2026-07-31): un singur loc de administrare
 * a personalului. Alocarea e fundamentul scoping-ului catalogului
 * ({@see Teacher::canGradeClassSubject}); scrierea = configuratori (§3.3, prin
 * {@see TeachingAssignmentPolicy}).
 *
 * Diferențe față de registrul vechi (erorile semnalate NU s-au preluat):
 *  - eticheta de MODEL e tradusă (modalul spunea „Creare Teaching Assignment");
 *  - grupa apare DOAR când disciplina aleasă e limba engleză — singura împărțită pe grupe
 *    (garda de pe model o impune oricum, pe orice cale);
 *  - crearea trece prin model cu `teacher_id` explicit (HasManyThrough nu știe să creeze) —
 *    observerul de membrie (rolul Profesor urmează alocările) rămâne activ.
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

    /** Registrul are sens doar pe un cont CU fișă pedagogică; vizibil personalului academic. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof User
            && $ownerRecord->teacher !== null
            && (auth('web')->user()?->canSeeAcademicData() ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->options(fn (): array => self::classOptions())
                    ->searchable()
                    ->required()
                    // Clasa dă TREAPTA, iar treapta decide ce discipline există — deci se alege
                    // prima, iar schimbarea ei golește disciplina (una rămasă din alt ciclu ar fi
                    // invalidă tăcut).
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('subject_id', null)),
                Select::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    // Disciplinele TREPTEI, nu toate din nomenclator (sesizat de beneficiar,
                    // 06.08.2026: „Matematică" apărea de două ori la o clasă a VI-a).
                    //
                    // Zece denumiri se repetă în nomenclator — aceeași materie predată la primar
                    // și la gimnaziu/liceu, cu tip de notare DIFERIT („Matematică" cl. 1–4 pe
                    // calificativ vs cl. 5–12 numeric). Nu e o eroare de date: e school-ul real.
                    // Eroarea era că formularul le arăta pe amândouă, identic etichetate, la orice
                    // clasă — iar alegerea greșită ar fi pus clasa a VI-a pe calificative.
                    // Filtrarea pe treaptă ({@see Subject::coversGrade}) le desparte: la treapta 6
                    // rămân 18 discipline și NICIUN omonim.
                    ->options(fn (Get $get): array => self::subjectOptions($get('school_class_id')))
                    ->helperText(fn (Get $get): ?string => self::blank($get('school_class_id'))
                        ? (string) __('panel.forms.lesson.pick_class_first')
                        : null)
                    ->searchable()
                    ->required()
                    ->live()
                    // Anti-duplicat cu mesaj clar (indexul unic vede ȘI alocările arhivate — un
                    // duplicat mergea direct în eroarea SQL; cel ARHIVAT se restaurează, nu se recreează).
                    ->rules([
                        // Treapta, pe SERVER: lista filtrată e o comoditate, nu o garanție — o
                        // alocare compusă altfel (payload vechi, import) ar lega clasa de fișa
                        // altui ciclu, iar catalogul ar nota pe scala greșită.
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if (self::subjectOutsideGrade($get('school_class_id'), $value)) {
                                $fail(__('panel.validation.lesson.subject_outside_grade'));
                            }
                        },
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $classId = $get('school_class_id');

                            if (! $value || ! $classId) {
                                return;
                            }

                            $teacherId = $this->ownerTeacherId();

                            if ($teacherId === null) {
                                return;
                            }

                            $group = $get('english_group');

                            $conflict = TeachingAssignment::withTrashed()
                                ->where('teacher_id', $teacherId)
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
                    ->maxValue(9)
                    // Grupa există DOAR la limba engleză — pe orice altă disciplină câmpul
                    // dispare (eroarea „grupă pe discipline fără legătură" nu se mai poate produce).
                    ->visible(fn (Get $get): bool => self::isEnglishSubject($get('subject_id')))
                    ->dehydrated(fn (Get $get): bool => self::isEnglishSubject($get('subject_id'))),
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
                    ->label(__('panel.forms.teaching_assignment.add'))
                    // Fără „Creați și creați altul": traitul {@see DisablesCreateAnother} acoperă
                    // paginile de Creare, dar acțiunile din modale au propriul buton — aceeași
                    // decizie de flux (creare → revizuire), deci se stinge și aici.
                    ->createAnother(false)
                    // HasManyThrough nu poate crea — alocarea se naște explicit pe fișa
                    // profesorului (prin MODEL: garda de grupă + membria de rol rămân active).
                    ->using(function (array $data): TeachingAssignment {
                        return TeachingAssignment::create([
                            ...$data,
                            'teacher_id' => $this->ownerTeacherId(),
                        ]);
                    }),
            ])
            ->recordActions([
                // Retragerea alocării = soft delete: notele consemnate rămân (autorul e pe notă,
                // nu pe alocare); profesorul pierde doar scoping-ul (clasa, disciplina) pe viitor.
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    /** Fișa de profesor a contului deschis — sursa `teacher_id` la creare și în anti-duplicat. */
    private function ownerTeacherId(): ?int
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof User ? ($owner->teacher?->getKey() !== null ? (int) $owner->teacher->getKey() : null) : null;
    }

    private static function isEnglishSubject(mixed $subjectId): bool
    {
        if (! filled($subjectId) || ! is_scalar($subjectId)) {
            return false;
        }

        return Subject::query()->find((int) $subjectId)?->isEnglishLanguage() ?? false;
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

    /** Câmpul e gol (null, string vid)? Un id „0" nu există, deci trece tot pe aici. */
    private static function blank(mixed $value): bool
    {
        return $value === null || $value === '' || (int) $value === 0;
    }

    /** Disciplina cade în afara treptei clasei? Aceeași regulă ca la Lecții, aceeași sursă. */
    private static function subjectOutsideGrade(mixed $classId, mixed $subjectId): bool
    {
        if (self::blank($classId) || self::blank($subjectId)) {
            return false;
        }

        $grade = SchoolClass::query()->whereKey((int) $classId)->value('grade_level');
        $subject = Subject::query()->whereKey((int) $subjectId)->first();

        if ($grade === null || $subject === null) {
            return false;
        }

        return ! $subject->coversGrade((int) $grade);
    }

    /**
     * Disciplinele care se predau la TREAPTA clasei alese. Fără clasă → listă goală: alegerea
     * disciplinei n-are sens până nu se știe ciclul, iar o listă „toate" ar readuce omonimele.
     *
     * Dacă, în ciuda filtrării, două discipline rămân cu ACELAȘI nume (interval editat de școală
     * ca să se suprapună), eticheta capătă intervalul de clase — o listă cu două rânduri identice
     * e o alegere pe ghicite.
     *
     * @return array<int, string>
     */
    private static function subjectOptions(mixed $classId): array
    {
        if (self::blank($classId)) {
            return [];
        }

        $level = SchoolClass::query()->whereKey((int) $classId)->value('grade_level');

        if ($level === null) {
            return [];
        }

        $subjects = Subject::query()
            ->orderBy('name')
            ->get()
            ->filter(fn (Subject $subject): bool => $subject->coversGrade((int) $level));

        $omonime = $subjects
            ->groupBy(fn (Subject $subject): string => ContentTranslator::subject($subject->name))
            ->filter(fn ($group): bool => $group->count() > 1)
            ->keys()
            ->all();

        $options = [];

        foreach ($subjects as $subject) {
            $label = ContentTranslator::subject($subject->name);

            if (in_array($label, $omonime, true)) {
                $label .= ' ('.__('panel.fields.class').' '.($subject->min_grade ?? '—').'–'.($subject->max_grade ?? '—').')';
            }

            $options[$subject->id] = $label;
        }

        return $options;
    }
}
