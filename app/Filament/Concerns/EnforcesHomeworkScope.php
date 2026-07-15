<?php

namespace App\Filament\Concerns;

use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Validation\ValidationException;

/**
 * Impune pe SERVER regulile de creare/editare a unei teme — opțiunile scoped din formular sunt
 * doar UX; aici e protecția reală împotriva POST-urilor manipulate (simetric cu
 * EnforcesGradeScope / EnforcesAbsenceScope, care lipseau cu desăvârșire la teme).
 *
 * Ținta temei sosește într-un SINGUR câmp (`class_target`):
 *  - `class:{id}`  → o clasă REALĂ; treapta + litera derivă din ea pe server (combinații
 *    inexistente devin structural imposibile, iar secția goală nu mai poate intra ca '' în DB);
 *  - `grade:{n}`   → toată treapta (secție NULL) — REZERVAT administrației.
 *
 * Profesorul (inclusiv dirigintele) poate da temă DOAR pe perechile (clasă, disciplină) din
 * alocările proprii — aceeași regulă ca la note (canGradeClassSubject). Autorul se forțează pe
 * server la creare; la editare autorul ORIGINAL nu se atinge (un director care predă și editează
 * tema unui coleg NU îi preia autoratul — scăpare reală a vechiului PreparesHomeworkData).
 */
trait EnforcesHomeworkScope
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function enforceHomeworkScope(array $data, bool $creating): array
    {
        $user = auth('web')->user();

        if (! $user) {
            throw ValidationException::withMessages([
                'data.class_target' => __('panel.validation.scope.no_teacher_profile'),
            ]);
        }

        $isAdministrator = $user->isAdministrator();
        $teacher = $user->teacher;

        // ── Ținta: un singur câmp → treaptă + literă derivate pe server ────────────────────
        $target = (string) ($data['class_target'] ?? '');
        unset($data['class_target']);

        if (str_starts_with($target, 'class:')) {
            $class = SchoolClass::query()->find((int) substr($target, 6));

            if ($class === null) {
                throw ValidationException::withMessages([
                    'data.class_target' => __('panel.validation.homework.class_target_invalid'),
                ]);
            }

            $data['grade_level'] = (int) $class->grade_level;
            // Derivată din clasa reală: NULL rămâne NULL (niciodată '' — cabinetul și navigatorul
            // caută whereNull pentru „toată treapta").
            $data['section'] = $class->section;
            $classId = (int) $class->id;
        } elseif (str_starts_with($target, 'grade:')) {
            // Toată treapta = doar administrația (profesorul de 7 A nu dă temă întregii trepte 7).
            if (! $isAdministrator) {
                throw ValidationException::withMessages([
                    'data.class_target' => __('panel.validation.homework.whole_grade_admin_only'),
                ]);
            }

            $gradeLevel = (int) substr($target, 6);

            if ($gradeLevel < 1 || $gradeLevel > 12) {
                throw ValidationException::withMessages([
                    'data.class_target' => __('panel.validation.homework.class_target_invalid'),
                ]);
            }

            $data['grade_level'] = $gradeLevel;
            $data['section'] = null;
            $classId = null;
        } else {
            throw ValidationException::withMessages([
                'data.class_target' => __('panel.validation.homework.class_target_invalid'),
            ]);
        }

        // ── Perechea (clasă, disciplină) a profesorului — protecția reală ──────────────────
        $subjectId = (int) ($data['subject_id'] ?? 0);

        if (! $isAdministrator) {
            if ($teacher === null) {
                throw ValidationException::withMessages([
                    'data.class_target' => __('panel.validation.scope.no_teacher_profile'),
                ]);
            }

            if ($classId === null || ! $teacher->canGradeClassSubject($classId, $subjectId)) {
                throw ValidationException::withMessages([
                    'data.subject_id' => __('panel.validation.scope.not_your_class_subject'),
                ]);
            }
        }

        // ── Conținut: o temă fără subiect ȘI fără sarcină obligatorie e o temă goală ───────
        if (blank($data['topic'] ?? null) && blank($data['required_task'] ?? null)) {
            throw ValidationException::withMessages([
                'data.topic' => __('panel.validation.homework.content_required'),
            ]);
        }

        // ── Câmpuri derivate ────────────────────────────────────────────────────────────────
        $data['subject_name'] = Subject::query()->whereKey($subjectId)->value('name') ?? '';

        if ($creating) {
            // Autorul = cel care creează (nu de încredere din formular). Administrația fără fișă
            // de profesor rămâne autor „de sistem", cu numele contului.
            $data['teacher_id'] = $teacher?->id;
            $data['author_name'] = $teacher->full_name ?? $user->name;
        } else {
            // La editare autorul ORIGINAL nu se atinge.
            unset($data['teacher_id'], $data['author_name']);
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
