<?php

namespace App\Filament\Concerns;

use App\Models\Subject;

/**
 * Completează câmpurile derivate ale unei teme la salvare: numele disciplinei
 * (denormalizat) și — la creare de către un profesor — autorul (teacher_id + nume).
 */
trait PreparesHomeworkData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareHomeworkData(array $data): array
    {
        $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : null;
        if ($subjectId) {
            $data['subject_name'] = Subject::query()->whereKey($subjectId)->value('name') ?? '';
        }

        $teacher = auth()->user()?->teacher;
        if ($teacher) {
            $data['teacher_id'] = $teacher->id;
            $data['author_name'] = $teacher->full_name;
        }

        return $data;
    }
}
