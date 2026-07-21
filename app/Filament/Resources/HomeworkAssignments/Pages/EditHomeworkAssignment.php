<?php

namespace App\Filament\Resources\HomeworkAssignments\Pages;

use App\Filament\Concerns\EnforcesHomeworkScope;
use App\Filament\Concerns\PlacesRecordActionsWithForm;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Models\SchoolClass;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditHomeworkAssignment extends EditRecord
{
    use EnforcesHomeworkScope;
    use PlacesRecordActionsWithForm;

    protected static string $resource = HomeworkAssignmentResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getRecordActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Ținta stocată (treaptă + literă) → câmpul unic al formularului. Secția NULL = toată
     * treapta; o secție istorică se mapează pe clasa reală cu acea pereche (cel mai recent an).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $section = $data['section'] ?? null;
        $gradeLevel = (int) ($data['grade_level'] ?? 0);

        if ($section === null || $section === '') {
            $data['class_target'] = 'grade:'.$gradeLevel;
        } else {
            $classId = SchoolClass::query()
                ->where('grade_level', $gradeLevel)
                ->where('section', $section)
                ->orderByDesc('academic_year_id')
                ->value('id');

            // Fără clasă reală pentru pereche (istoric orfan): ținta rămâne goală — editorul
            // alege conștient una validă (formularul o cere), fără re-țintiri tăcute.
            $data['class_target'] = $classId !== null ? 'class:'.(int) $classId : null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->enforceHomeworkScope($data, creating: false);
    }
}
