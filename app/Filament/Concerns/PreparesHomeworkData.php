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

        $teacher = auth('web')->user()?->teacher;
        if ($teacher) {
            $data['teacher_id'] = $teacher->id;
            $data['author_name'] = $teacher->full_name;
        }

        // Rândul gol al repeater-ului ajungea în DB ca `[null]`, iar cabinetul afișa un chip gol.
        if (array_key_exists('links', $data)) {
            $data['links'] = array_values(array_filter(
                (array) $data['links'],
                static fn (mixed $link): bool => filled($link),
            ));
        }

        return $data;
    }
}
