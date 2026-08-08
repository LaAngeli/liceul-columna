<?php

namespace App\Filament\Resources\Subjects\RelationManagers;

use App\Actions\SyncSubjectTeachers;
use App\Filament\Resources\Subjects\Schemas\SubjectForm;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Support\GradeLevels;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * REGISTRUL alocărilor disciplinei (profesor ↔ clasă ± grupă) — strict CONSULTATIV: toți anii,
 * inclusiv alocările retrase (TrashedFilter), grupate pe clasă cu anul în descriere.
 *
 * ⚠️ FĂRĂ acțiuni de scriere, deliberat (07.08.2026): echipa ANULUI CURENT se gestionează în
 * secțiunea „Profesorii disciplinei" din formularul fișei ({@see SubjectForm}
 * → {@see SyncSubjectTeachers}). Două suprafețe de scriere pe aceeași pagină s-ar
 * călca una pe alta: formularul se pre-completează la DESCHIDERE, deci orice alocare adăugată
 * din tabel după aceea ar fi retrasă la prima salvare a formularului (starea lui, rămasă în
 * urmă, ar „câștiga"). Restaurarea și arhiva pe persoană rămân pe fișa utilizatorului
 * ({@see \App\Filament\Resources\Users\RelationManagers\TeachingAssignmentsRelationManager}).
 */
class TeachingAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'teachingAssignments';

    protected static string|BackedEnum|null $icon = 'heroicon-o-briefcase';

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
}
