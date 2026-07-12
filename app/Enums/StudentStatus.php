<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Statutul elevului la final de semestru/an (§2.5): promovat (toate mediile ≥ 5), corigent (medie
 * < 5, are examene de lichidare), repetent (a picat corigența → repetă anul), amânat (situația nu
 * poate fi definitivată). Corigența trecută → promovat; picată → repetent.
 */
enum StudentStatus: string implements HasLabel
{
    case Promovat = 'promovat';
    case Corigent = 'corigent';
    case Repetent = 'repetent';
    case Amanat = 'amanat';

    public function label(): string
    {
        return (string) trans('enums.student_status.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function color(): string
    {
        return match ($this) {
            self::Promovat => 'success',
            self::Corigent => 'warning',
            self::Repetent => 'danger',
            self::Amanat => 'gray',
        };
    }

    /**
     * Statut care CERE atenția familiei (aprinde alerta de aterizare + bordura roșie a cardului).
     * Sursă UNICĂ, ca definiția server (cockpit) să nu poată diverge de badge-ul frontend: corigent
     * și amânat cer confirmare, iar REPETENT — cel mai grav rezultat — trebuie să aprindă și el
     * alerta (altfel banda spunea „nimic de atenționat" peste un card cu badge roșu „Repetent").
     */
    public function isAtRisk(): bool
    {
        return in_array($this, [self::Corigent, self::Amanat, self::Repetent], true);
    }
}
