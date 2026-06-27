<?php

namespace App\Actions;

use App\Models\Student;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Jurnalizează un ACCES la datele sensibile ale unui elev — vizualizare sau export (Legea 133 §7:
 * „jurnalizarea accesului — cine a vizualizat ce date, nu doar modificările"). Foloseúte
 * Folosește evenimentul custom owen-it, deci accesul apare în ACELAȘI jurnal ca modificările.
 * Actorul (user_id), IP-ul, URL-ul se rezolvă automat de owen-it.
 */
class LogStudentAccess
{
    public function record(Student $student, string $event = 'viewed', ?string $detail = null): void
    {
        $student->auditEvent = $event;
        $student->isCustomEvent = true;
        $student->auditCustomOld = [];
        $student->auditCustomNew = ['detaliu' => $detail ?? $event];

        Event::dispatch(new AuditCustom($student));
    }
}
