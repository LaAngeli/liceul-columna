<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;

class ListSubjects extends ListRecords
{
    protected static string $resource = SubjectResource::class;

    /**
     * Hărțile coloanelor pe rol (disciplină → clasele mele / profesorii clasei mele), memoizate
     * PE INSTANȚĂ (per request, per utilizator) — un cache static în clasa tabelului s-ar scurge
     * între utilizatori în teste (autoincrement resetat) sau într-un runtime long-lived.
     *
     * @var Collection<int, string>|null
     */
    private ?Collection $myClassesMap = null;

    /** @var Collection<int, string>|null */
    private ?Collection $homeroomTeachersMap = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Disciplina → clasele în care profesorul LOGAT o predă (din alocările proprii).
     *
     * @return Collection<int, string>
     */
    public function myClassesMap(): Collection
    {
        return $this->myClassesMap ??= ($teacher = $this->currentTeacher()) === null
            ? collect()
            : TeachingAssignment::query()
                ->with('schoolClass')
                ->where('teacher_id', $teacher->id)
                ->get()
                ->groupBy('subject_id')
                ->map(fn ($assignments) => $assignments
                    ->map(fn ($a) => $a->schoolClass !== null
                        ? trim($a->schoolClass->name.' '.($a->schoolClass->section ?? ''))
                        : null)
                    ->filter()
                    ->unique()
                    ->sort()
                    ->implode(' · '));
    }

    /**
     * Disciplina → profesorii care o predau în clasa/clasele de diriginție ale userului logat.
     *
     * @return Collection<int, string>
     */
    public function homeroomTeachersMap(): Collection
    {
        return $this->homeroomTeachersMap ??= ($homeroomIds = $this->currentTeacher()?->homeroomSchoolClassIds() ?? []) === []
            ? collect()
            : TeachingAssignment::query()
                ->with('teacher')
                ->whereIn('school_class_id', $homeroomIds)
                ->get()
                ->groupBy('subject_id')
                ->map(fn ($assignments) => $assignments
                    ->map(fn ($a) => $a->teacher?->full_name)
                    ->filter()
                    ->unique()
                    ->implode(' · '));
    }

    private function currentTeacher(): ?Teacher
    {
        $user = auth('web')->user();

        return ($user && ! $user->isAdministrator()) ? $user->teacher : null;
    }
}
