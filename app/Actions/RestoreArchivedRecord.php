<?php

namespace App\Actions;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Restaurarea unei înregistrări din coș — într-o TRANZACȚIE, cu conflictele re-verificate pe
 * server (butonul stins în UI nu e o garanție: cine apasă cu un POST fabricat trebuie oprit aici).
 *
 * Restaurarea nu e doar `restore()`: elevul fără înmatricularea lui revine în afara oricărei clase
 * și, fiindcă slotul (elev, an) rămâne ocupat de rândul șters, nu i se mai poate crea alta — deci
 * cascada e parte din operațiune, nu un extra. Disciplina cu poziția ocupată în foaia matricolă se
 * repară singură (mutată la coadă), fiindcă altfel ordinea ar avea două valori egale.
 *
 * Restaurarea e AUDITATĂ automat (modelele sunt Auditable → eveniment `restored`), deci „cine a
 * readus fișa" rămâne în jurnal fără cod suplimentar.
 */
class RestoreArchivedRecord
{
    public function __construct(private InspectRestoreConflicts $inspector) {}

    /**
     * @param  Student|Teacher|SchoolClass|Enrollment|Subject  $record
     * @return array{restored: int, cascaded: int, repaired: array<int, string>}
     */
    public function restore(Model $record, bool $withCascade = true): array
    {
        $conflicts = $this->inspector->inspect($record);

        if ($conflicts['blocking'] !== []) {
            throw ValidationException::withMessages([
                'restore' => $conflicts['blocking'],
            ]);
        }

        return DB::transaction(function () use ($record, $withCascade): array {
            $repaired = $record instanceof Subject ? $this->freeReportOrder($record) : [];

            $record->restore();

            $cascaded = $withCascade ? $this->cascade($record) : 0;

            return ['restored' => 1, 'cascaded' => $cascaded, 'repaired' => $repaired];
        });
    }

    /**
     * Înmatriculările șterse ODATĂ CU elevul/clasa revin împreună cu el — dar numai cele al căror
     * CELĂLALT capăt e viu (o înmatriculare într-o clasă ștearsă ar lega registrul de nimic).
     */
    private function cascade(Model $record): int
    {
        $query = match (true) {
            $record instanceof Student => Enrollment::query()->onlyTrashed()->where('student_id', $record->getKey()),
            $record instanceof SchoolClass => Enrollment::query()->onlyTrashed()->where('school_class_id', $record->getKey()),
            default => null,
        };

        if ($query === null) {
            return 0;
        }

        $restored = 0;

        foreach ($query->get() as $enrollment) {
            $conflicts = $this->inspector->inspect($enrollment);

            if ($conflicts['blocking'] !== []) {
                continue;
            }

            $enrollment->restore();
            $restored++;
        }

        return $restored;
    }

    /**
     * Poziția în foaia matricolă e unică și contiguă: dacă între timp altcineva a ocupat-o,
     * disciplina restaurată merge la coada ordinii în loc să dubleze poziția.
     *
     * @return array<int, string>
     */
    private function freeReportOrder(Subject $record): array
    {
        if ($record->report_order === null) {
            return [];
        }

        $taken = Subject::query()
            ->where('report_order', $record->report_order)
            ->whereKeyNot($record->getKey())
            ->exists();

        if (! $taken) {
            return [];
        }

        $next = (int) Subject::query()->max('report_order') + 1;
        $previous = (int) $record->report_order;

        // Update direct: `report_order` nu se dehidratează din formular, iar aici nu vrem să
        // declanșăm renumerotarea completă — doar să eliberăm poziția disputată.
        Subject::query()->whereKey($record->getKey())->update(['report_order' => $next]);
        $record->report_order = $next;

        return [(string) __('panel.restore.repaired.subject_order', [
            'from' => $previous,
            'to' => $next,
        ])];
    }
}
