<?php

namespace App\Models;

use OwenIt\Auditing\Models\Audit as BaseAudit;

/**
 * Extinde modelul de audit owen-it cu etichete RO, pentru viewer-ul din panou (spec §7 / L133).
 * Aceeași tabelă `audits` — doar adăugăm ajutoare de afișare; scrierea rămâne a pachetului.
 *
 * @property string $event
 * @property string $auditable_type
 */
class Audit extends BaseAudit
{
    /**
     * Eticheta RO a tipului de date auditat (din clasa modelului).
     */
    public function auditableLabel(): string
    {
        return match (class_basename($this->auditable_type)) {
            'Grade' => 'Notă',
            'Absence' => 'Absență',
            'AcademicRecord' => 'Foaie matricolă',
            'TermAverage' => 'Medie semestrială',
            'Student' => 'Elev (date personale)',
            default => class_basename($this->auditable_type),
        };
    }

    /**
     * Eticheta RO a evenimentului (inclusiv accesul: vizualizare/export, spec §7).
     */
    public function eventLabel(): string
    {
        return match ($this->event) {
            'created' => 'Creare',
            'updated' => 'Modificare',
            'deleted' => 'Ștergere',
            'restored' => 'Restaurare',
            'viewed' => 'Vizualizare',
            'exported' => 'Export',
            default => $this->event,
        };
    }
}
