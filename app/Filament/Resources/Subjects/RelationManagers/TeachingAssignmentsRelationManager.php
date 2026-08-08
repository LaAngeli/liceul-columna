<?php

namespace App\Filament\Resources\Subjects\RelationManagers;

use App\Actions\SyncSubjectTeachers;
use App\Filament\Resources\Subjects\Schemas\SubjectForm;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Support\GradeLevels;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * REGISTRUL alocărilor disciplinei — strict CONSULTATIV, restructurat (08.08.2026, cerința
 * beneficiarului: „foarte incomod... să nu arate toți anii, ci informația utilă la moment").
 *
 * Forma veche: un rând per alocare, grupat pe clasă, TOȚI anii dintr-odată — la Matematică,
 * 67 de rânduri cu antete de grup pe 7 pagini, ca să afli ce încape într-o privire.
 * Forma nouă: un rând per (PROFESOR, grupă) cu clasele lui ca pastile — verde = activă,
 * gri = retrasă — filtrat implicit pe ANUL CURENT. Anii trecuți sunt la un filtru distanță
 * (doar anii care chiar au alocări la disciplină); filtrul golit = toți anii, cu anul scris
 * pe fiecare pastilă.
 *
 * ⚠️ FĂRĂ acțiuni de scriere, deliberat: echipa anului curent se gestionează în secțiunea
 * „Profesorii disciplinei" din formular ({@see SubjectForm}
 * → {@see SyncSubjectTeachers}). Două suprafețe de scriere pe aceeași pagină s-ar
 * călca una pe alta: formularul se pre-completează la DESCHIDERE, deci orice alocare adăugată
 * din tabel după aceea ar fi retrasă la prima lui salvare. Registrul aduce exact ce formularul
 * nu arată: alocările RETRASE și echipele anilor trecuți. Restaurarea per persoană rămâne pe
 * fișa utilizatorului ({@see \App\Filament\Resources\Users\RelationManagers\TeachingAssignmentsRelationManager}).
 */
class TeachingAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'teachingAssignments';

    protected static string|BackedEnum|null $icon = 'heroicon-o-briefcase';

    /**
     * Randat ODATĂ cu pagina, nu la derulare: implicit, un relation manager e „lazy" și până la
     * intersecție lasă în josul fișei o casetă goală — exact golul reclamat la restructurare.
     */
    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.resources.teaching_assignments.registry');
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

    public function table(Table $table): Table
    {
        return $table
            ->description(__('panel.resources.teaching_assignments.registry_hint'))
            // Un rând per (profesor, grupă): agregarea o face baza, cheia rândului = MIN(id).
            // Scope-ul soft delete cade PERMANENT (echivalentul lui withTrashed, dar curat pe
            // builderul generic) — starea nu se mai ascunde după un filtru, o spune fiecare
            // pastilă în parte (retrasă = gri). Joinul pe profesori există doar pentru sortarea
            // alfabetică; coloanele lui intră în GROUP BY ca să treacă de ONLY_FULL_GROUP_BY.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withoutGlobalScopes([SoftDeletingScope::class])
                ->leftJoin('teachers', 'teachers.id', '=', 'teaching_assignments.teacher_id')
                ->select('teaching_assignments.teacher_id', 'teaching_assignments.english_group')
                ->selectRaw('MIN(teaching_assignments.id) as id')
                ->groupBy('teaching_assignments.teacher_id', 'teaching_assignments.english_group', 'teachers.last_name', 'teachers.first_name')
                ->orderBy('teachers.last_name')
                ->orderBy('teachers.first_name')
                ->orderBy('teaching_assignments.english_group'))
            // ⚠️ Fără sortarea automată pe cheie: Filament adaugă singur `ORDER BY id` pentru
            // paginare stabilă, dar pe interogarea AGREGATĂ id-ul brut nu e în GROUP BY —
            // MySQL (ONLY_FULL_GROUP_BY) pică cu 42000, deși SQLite din teste tolerează.
            // Ordinea stabilă o dă deja sortarea alfabetică pe profesor + grupă.
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('teacher_name')
                    ->label(__('panel.fields.teacher'))
                    // Fișa arhivată a profesorului nu rupe rândul — alocarea lui e istorie
                    // legitimă. (`->` sub `??` e sigur pe lanțul posibil-null — coalescentul
                    // înghite și proprietatea de pe null.)
                    ->getStateUsing(fn (TeachingAssignment $record): string => Teacher::withTrashed()->find($record->teacher_id)->full_name
                        ?? (string) __('panel.common.dash'))
                    // Grupa de engleză, doar unde există — ca sufix, nu coloană mereu goală.
                    ->description(fn (TeachingAssignment $record): ?string => $record->english_group === null
                        ? null
                        : __('panel.forms.teaching_assignment.english_group').' '.$record->english_group),
                TextColumn::make('classes')
                    ->label(__('panel.forms.subject.teacher_classes'))
                    ->badge()
                    // Pastila retrasă își poartă starea ÎN etichetă — culoarea doar o subliniază.
                    ->color(fn (string $state): string => str_contains($state, (string) __('panel.resources.teaching_assignments.withdrawn_suffix'))
                        ? 'gray'
                        : 'success')
                    ->getStateUsing(fn (TeachingAssignment $record): array => $this->classBadgesFor($record)),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label(__('panel.fields.academic_year'))
                    // Doar anii care CHIAR au alocări la disciplină — un filtru cu ani goi ar fi
                    // tot zgomotul pe care îl scoatem. Golit → toți anii, cu anul pe pastile.
                    ->options(fn (): array => $this->yearOptions())
                    ->default(fn (): ?int => self::currentYearId())
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            filled($data['value'] ?? null),
                            fn (Builder $q): Builder => $q->whereHas(
                                'schoolClass',
                                fn (Builder $class): Builder => $class->where('academic_year_id', (int) $data['value']),
                            ),
                        )),
            ])
            ->emptyStateHeading(__('panel.resources.teaching_assignments.registry_empty'))
            ->emptyStateDescription(null)
            ->paginated([10, 25, 'all']);
    }

    private function ownerSubject(): ?Subject
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Subject ? $owner : null;
    }

    /**
     * Pastilele rândului: clasele perechii (profesor, grupă) în anul ALES — sau în toți anii,
     * cu anul în etichetă, când filtrul e golit. Retrasă = sufix + gri; activă = verde.
     * Interogare per rând, deliberat: după agregare rămân puține rânduri (câți profesori are
     * disciplina), nu N+1 peste sute.
     *
     * @return array<int, string>
     */
    private function classBadgesFor(TeachingAssignment $record): array
    {
        $yearId = $this->selectedYearId();

        $assignments = TeachingAssignment::withTrashed()
            ->where('subject_id', (int) ($this->ownerSubject()?->getKey() ?? 0))
            ->where('teacher_id', $record->teacher_id)
            ->when(
                $record->english_group === null,
                fn (Builder $query): Builder => $query->whereNull('english_group'),
                fn (Builder $query): Builder => $query->where('english_group', $record->english_group),
            )
            ->when($yearId !== null, fn (Builder $query): Builder => $query->whereHas(
                'schoolClass',
                fn (Builder $class): Builder => $class->where('academic_year_id', $yearId),
            ))
            ->with(['schoolClass.academicYear'])
            ->get()
            ->sortBy([
                fn (TeachingAssignment $a, TeachingAssignment $b): int => ($a->schoolClass->academicYear->starts_on ?? null) <=> ($b->schoolClass->academicYear->starts_on ?? null),
                fn (TeachingAssignment $a, TeachingAssignment $b): int => ($a->schoolClass->grade_level ?? 0) <=> ($b->schoolClass->grade_level ?? 0),
                fn (TeachingAssignment $a, TeachingAssignment $b): int => strnatcasecmp((string) $a->schoolClass?->name, (string) $b->schoolClass?->name),
            ]);

        return $assignments->map(function (TeachingAssignment $assignment) use ($yearId): string {
            $class = $assignment->schoolClass;

            $label = $class === null
                ? (string) __('panel.common.dash')
                : trim($class->name.' '.($class->section ?? '')).' · '.GradeLevels::roman((int) $class->grade_level);

            if ($yearId === null && $class?->academicYear !== null) {
                $label .= ' · '.$class->academicYear->name;
            }

            if ($assignment->trashed()) {
                $label .= (string) __('panel.resources.teaching_assignments.withdrawn_suffix');
            }

            return $label;
        })->values()->all();
    }

    /**
     * Anul din filtrul tabelului — sau anul curent cât timp filtrul stă pe implicit.
     * `null` = filtrul golit deliberat („toți anii").
     */
    private function selectedYearId(): ?int
    {
        $raw = data_get($this->tableFilters, 'year.value');

        return is_numeric($raw) ? (int) $raw : null;
    }

    /**
     * Anii de filtrat: DOAR cei în care disciplina are alocări (vii sau retrase), cei mai noi
     * primii.
     *
     * @return array<int, string>
     */
    private function yearOptions(): array
    {
        $classIds = TeachingAssignment::withTrashed()
            ->where('subject_id', (int) ($this->ownerSubject()?->getKey() ?? 0))
            ->distinct()
            ->pluck('school_class_id');

        return AcademicYear::query()
            ->whereIn('id', SchoolClass::query()->whereIn('id', $classIds)->distinct()->pluck('academic_year_id'))
            ->orderByDesc('starts_on')
            ->pluck('name', 'id')
            ->map(static fn ($name): string => (string) $name)
            ->all();
    }

    private static function currentYearId(): ?int
    {
        $id = AcademicYear::query()->where('is_current', true)->value('id');

        return $id === null ? null : (int) $id;
    }
}
