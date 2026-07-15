<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Resources\Teachers\TeacherResource;
use App\Models\SchoolClass;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;

class ListTeachers extends ListRecords
{
    protected static string $resource = TeacherResource::class;

    /**
     * Profesor → clasa/clasele unde e diriginte — memoizat pe instanță (per request).
     *
     * @var Collection<int, string>|null
     */
    private ?Collection $homeroomOfMap = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** @return Collection<int, string> */
    public function homeroomOfMap(): Collection
    {
        return $this->homeroomOfMap ??= SchoolClass::query()
            ->whereNotNull('homeroom_teacher_id')
            ->get()
            ->groupBy('homeroom_teacher_id')
            ->map(fn ($classes) => $classes
                ->map(fn ($c) => trim($c->name.' '.($c->section ?? '')))
                ->unique()
                ->sort()
                ->implode(' · '));
    }
}
