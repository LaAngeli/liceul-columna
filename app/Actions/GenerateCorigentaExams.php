<?php

namespace App\Actions;

use App\Enums\CorigentaSeason;
use App\Enums\StudentStatus;
use App\Models\CorigentaExam;
use App\Models\SemesterValidation;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermAverage;

/**
 * Generează automat intrările de corigență (spec §2.5 / #33) la marcarea elevului ca „corigent":
 * câte o intrare per disciplină restantă (medie < 5), idempotent. Data + comisia se completează
 * ulterior, când sesiunea de corigență e configurată/publicată. Sezon: sem. I → iarnă, sem. II → vară.
 */
class GenerateCorigentaExams
{
    /**
     * Aceeași generare, dar pentru TOT semestrul: pentru fiecare elev al cărui statut a fost
     * VALIDAT OFICIAL ca „corigent" (Consiliul profesoral + ordin), nu pentru oricine are o medie
     * sub 5. Distincția e de fond: corigența e o decizie a Consiliului, nu un rezultat de calcul —
     * o generare pe medii ar transforma un raport statistic în hotărâre.
     *
     * De folos când validările s-au făcut înainte ca generarea să existe, sau când o rulare a
     * eșuat: fiind idempotentă (updateOrCreate pe elev+disciplină+semestru), se poate relua oricând.
     *
     * @return array{students: int, exams: int, pending: int} `pending` = elevi cu medii sub 5 care
     *                                                        NU au încă statut validat — semnal
     *                                                        pentru Consiliu, nu acțiune automată
     */
    public function forTerm(Term $term): array
    {
        $validated = SemesterValidation::query()
            ->where('term_id', $term->id)
            ->where('status', StudentStatus::Corigent->value)
            ->with('student')
            ->get();

        $exams = 0;
        $students = 0;

        foreach ($validated as $validation) {
            $student = $validation->student;

            if ($student === null) {
                continue;
            }

            $created = $this->forStudentTerm($student, $term);

            if ($created > 0) {
                $students++;
                $exams += $created;
            }
        }

        return [
            'students' => $students,
            'exams' => $exams,
            'pending' => $this->awaitingValidation($term, array_values($validated->pluck('student_id')->all())),
        ];
    }

    /**
     * Câți elevi au cel puțin o medie sub 5 în semestru, fără statut validat încă. Nu declanșează
     * nimic — e numărul pe care secretariatul trebuie să-l ducă în fața Consiliului.
     *
     * @param  list<int>  $alreadyValidated
     */
    private function awaitingValidation(Term $term, array $alreadyValidated): int
    {
        return TermAverage::query()
            ->where('term_id', $term->id)
            ->whereNotIn('student_id', $alreadyValidated)
            ->get()
            ->filter(fn (TermAverage $average): bool => $average->isFailing())
            ->pluck('student_id')
            ->unique()
            ->count();
    }

    public function forStudentTerm(Student $student, Term $term): int
    {
        $season = $term->number === 1 ? CorigentaSeason::Iarna : CorigentaSeason::Vara;

        $failing = TermAverage::query()
            ->where('student_id', $student->id)
            ->where('term_id', $term->id)
            ->get()
            ->filter(fn (TermAverage $average): bool => $average->isFailing());

        $created = 0;

        foreach ($failing as $average) {
            CorigentaExam::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'subject_id' => $average->subject_id,
                    'term_id' => $term->id,
                ],
                ['season' => $season],
            );
            $created++;
        }

        return $created;
    }
}
