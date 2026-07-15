<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Resources\Teachers\TeacherResource;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Support\ContentTranslator;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;

class ListTeachers extends ListRecords
{
    protected static string $resource = TeacherResource::class;

    /**
     * Hărțile coloanelor pe rol, memoizate PE INSTANȚĂ (per request, per utilizator) — un cache
     * static în clasa tabelului s-ar scurge între utilizatori (vezi lecția din SubjectsTable).
     *
     * @var Collection<int, string>|null
     */
    private ?Collection $teachesInMyClassesMap = null;

    /** @var Collection<int, string>|null */
    private ?Collection $inMyHomeroomMap = null;

    /** @var Collection<int, string>|null */
    private ?Collection $homeroomOfMap = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Profesorul rândului → ce predă în CLASELE MELE (ale viewer-ului): „Chimie (VII 1) · …".
     *
     * @return Collection<int, string>
     */
    public function teachesInMyClassesMap(): Collection
    {
        return $this->teachesInMyClassesMap ??= ($classIds = $this->viewerClassIds()) === []
            ? collect()
            : TeachingAssignment::query()
                ->with(['subject', 'schoolClass'])
                ->whereIn('school_class_id', $classIds)
                ->get()
                ->groupBy('teacher_id')
                ->map(fn ($assignments) => $assignments
                    ->map(fn ($a) => $a->subject !== null && $a->schoolClass !== null
                        ? ContentTranslator::subject($a->subject->name).' ('.trim($a->schoolClass->name.' '.($a->schoolClass->section ?? '')).')'
                        : null)
                    ->filter()
                    ->unique()
                    ->sort()
                    ->implode(' · '));
    }

    /**
     * Profesorul rândului → disciplinele predate în clasa MEA de diriginție.
     *
     * @return Collection<int, string>
     */
    public function inMyHomeroomMap(): Collection
    {
        return $this->inMyHomeroomMap ??= ($homeroomIds = $this->viewerTeacher()?->homeroomSchoolClassIds() ?? []) === []
            ? collect()
            : TeachingAssignment::query()
                ->with('subject')
                ->whereIn('school_class_id', $homeroomIds)
                ->get()
                ->groupBy('teacher_id')
                ->map(fn ($assignments) => $assignments
                    ->map(fn ($a) => $a->subject !== null ? ContentTranslator::subject($a->subject->name) : null)
                    ->filter()
                    ->unique()
                    ->sort()
                    ->implode(' · '));
    }

    /**
     * Profesorul rândului → clasa/clasele unde e DIRIGINTE (în perimetrul viewer-ului).
     *
     * @return Collection<int, string>
     */
    public function homeroomOfMap(): Collection
    {
        return $this->homeroomOfMap ??= SchoolClass::query()
            ->whereNotNull('homeroom_teacher_id')
            ->when(($classIds = $this->viewerClassIds()) !== null, fn ($q) => $q->whereKey($classIds))
            ->get()
            ->groupBy('homeroom_teacher_id')
            ->map(fn ($classes) => $classes
                ->map(fn ($c) => trim($c->name.' '.($c->section ?? '')))
                ->unique()
                ->sort()
                ->implode(' · '));
    }

    /**
     * Clasele viewer-ului (null = administrația, fără limitare).
     *
     * @return array<int, int>|null
     */
    private function viewerClassIds(): ?array
    {
        $teacher = $this->viewerTeacher();

        return $teacher?->visibleSchoolClassIds();
    }

    private function viewerTeacher(): ?Teacher
    {
        $user = auth('web')->user();

        return ($user && ! $user->isAdministrator()) ? $user->teacher : null;
    }
}
