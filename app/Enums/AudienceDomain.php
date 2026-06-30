<?php

namespace App\Enums;

use App\Models\User;
use Filament\Support\Contracts\HasLabel;

/**
 * Domeniul unei solicitări de audiență (spec §4.2). Audiențele familiei se rutează către
 * vicedirectorul responsabil de domeniu — NU către un prim-vicedirector unic. La „Liceul Columna"
 * domeniile sunt: instruire (proces didactic) și educație (frecvență/disciplină/activitate educativă).
 *
 * Responsabilul de domeniu nu e un ROL separat: e un atribut ({@see User::$audience_domains})
 * pe care administrația îl atribuie unor conturi de conducere existente. Astfel se evită proliferarea
 * de roluri, iar accesul curge „în dependență de rol + atribut".
 */
enum AudienceDomain: string implements HasLabel
{
    case Instruire = 'instruire';
    case Educatie = 'educatie';

    public function label(): string
    {
        return (string) trans('enums.audience_domain.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Valorile, pentru validări și selectoare.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $domain): string => $domain->value, self::cases());
    }

    /**
     * Opțiuni value=>etichetă (RO) pentru formularele Filament.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $domain) {
            $options[$domain->value] = $domain->label();
        }

        return $options;
    }
}
