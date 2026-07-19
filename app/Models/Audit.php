<?php

namespace App\Models;

use Illuminate\Support\Facades\Lang;
use OwenIt\Auditing\Models\Audit as BaseAudit;

/**
 * Extinde modelul de audit owen-it cu etichete traduse, pentru viewer-ul din panou (spec §7 /
 * L133). Aceeași tabelă `audits` — doar adăugăm ajutoare de afișare; scrierea rămâne a pachetului.
 *
 * @property string $event
 * @property string $auditable_type
 */
class Audit extends BaseAudit
{
    /**
     * Eticheta tradusă a tipului de date auditat (din clasa modelului). TOATE modelele Auditable
     * au cheie în `panel.audit_types` (garantat de test); un tip nou fără cheie cade pe numele
     * clasei — vizibil, nu ascuns.
     */
    public function auditableLabel(): string
    {
        return self::labelForType($this->auditable_type);
    }

    /** Aceeași etichetă, pentru un tip dat (opțiunile filtrelor din viewer). */
    public static function labelForType(string $auditableType): string
    {
        $key = 'panel.audit_types.'.class_basename($auditableType);

        return Lang::has($key) ? (string) trans($key) : class_basename($auditableType);
    }

    /**
     * Eticheta tradusă a evenimentului (inclusiv accesul: vizualizare/export, spec §7).
     */
    public function eventLabel(): string
    {
        return self::eventLabelFor($this->event);
    }

    /**
     * Aceeași etichetă, pentru un eveniment dat — varianta STATICĂ, folosită acolo unde avem doar
     * valoarea coloanei (`formatStateUsing`), nu instanța: nu depinde de clasa hidratată, deci nu
     * se rupe dacă relația întoarce modelul pachetului în loc de al nostru.
     */
    public static function eventLabelFor(string $event): string
    {
        $key = 'panel.tables.audits.event_'.$event;

        return Lang::has($key) ? (string) trans($key) : $event;
    }
}
