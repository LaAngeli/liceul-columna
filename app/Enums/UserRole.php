<?php

namespace App\Enums;

/**
 * Cele 9 roluri ale platformei (spec §3.2/§3.3 + Super Administrator break-glass).
 *
 * Ierarhia de încredere: Super Administrator (tehnic, atotputernic) → Director →
 * Prim-vicedirector → Administrator operațional (config + atribuțiile vicedirectorului,
 * comasate) → Administrator tehnic (infra) → Diriginte → Profesor → Părinte / Elev.
 *
 * DEVIERE confirmată: UN singur rol per utilizator (nu cumul). „Administrator operațional"
 * absoarbe atribuțiile de vicedirector, fiindcă nu se poate cumula rolul separat.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Director = 'director';
    case PrimVicedirector = 'prim-vicedirector';
    case AdministratorOperational = 'administrator-operational';
    case AdministratorTehnic = 'administrator-tehnic';
    case Diriginte = 'diriginte';
    case Profesor = 'profesor';
    case Elev = 'elev';
    case Parinte = 'parinte';

    /**
     * Eticheta afișată în interfață (RO/RU/EN). Reutilizează dicționarul `site.roles.*`
     * (deja tradus, folosit și în welcome widget/cabinet) ca să nu duplicăm denumirile rolurilor.
     */
    public function label(): string
    {
        return (string) trans('site.roles.'.$this->value);
    }

    /**
     * Rolurile care au acces la panoul de gestiune Filament (personalul școlii).
     * Elevii și părinții folosesc cabinetul Inertia, nu panoul.
     *
     * @return list<self>
     */
    public static function panelRoles(): array
    {
        return [
            self::Admin,
            self::Director,
            self::PrimVicedirector,
            self::AdministratorOperational,
            self::AdministratorTehnic,
            self::Diriginte,
            self::Profesor,
        ];
    }

    /**
     * Valorile string ale rolurilor cu acces la panou.
     *
     * @return list<string>
     */
    public static function panelRoleValues(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::panelRoles());
    }

    /**
     * Rolurile de „administrație academică" — văd TOT catalogul fără scoping (§3.3, coloanele
     * Dir/VD/AO la VIZUALIZARE). NU include Administratorul tehnic (infra, fără date academice)
     * și nici implică drept de SCRIERE (vezi capabilitățile din User pentru editare/aprobare).
     *
     * @return list<string>
     */
    public static function administratorValues(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            [self::Admin, self::Director, self::PrimVicedirector, self::AdministratorOperational],
        );
    }

    /**
     * Rolurile care pot PURTA un domeniu de audiență (Instruire/Educație/…): conducerea academică
     * și administratorul operațional. Regulă de DOMENIU, nu de UI — o folosesc deopotrivă
     * vizibilitatea câmpului din formularul de utilizator, curățarea la retrogradare și rutările
     * care caută responsabilul unui domeniu. O a doua listă undeva ar diverge tăcut.
     *
     * @return list<string>
     */
    public static function audienceDomainHolderValues(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            [self::Director, self::PrimVicedirector, self::AdministratorOperational],
        );
    }

    /**
     * Toate valorile string ale rolurilor.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }

    /**
     * Rolurile de FAMILIE: se acordă câte UNUL singur, fără cumul — nici cu personalul, nici între
     * ele. Un cont de elev și unul de părinte sunt persoane diferite, iar cabinetul lor e altul
     * decât panoul; un cont care ar fi „și elev, și profesor" n-ar avea un perimetru definibil.
     *
     * @return list<string>
     */
    public static function familyValues(): array
    {
        return array_map(static fn (self $role): string => $role->value, [self::Elev, self::Parinte]);
    }

    /**
     * Setul de roluri e o combinație PERMISĂ? Singura regulă de combinație din sistem: exclusivitatea
     * familiei. Funcțiile de personal se cumulă liber între ele (Director + Profesor + Diriginte).
     *
     * Sursă UNICĂ: o folosesc și garda de server ({@see EnforcesManageableRole}), și dezactivarea
     * opțiunilor din formular. Două copii ar diverge tăcut, iar UI-ul ar promite altceva decât acceptă
     * serverul.
     *
     * @param  list<string>  $values
     */
    public static function isAllowedCombination(array $values): bool
    {
        return count(array_unique($values)) <= 1
            || array_intersect($values, self::familyValues()) === [];
    }

    /**
     * Bifarea lui `$value` peste selecția curentă ar produce o combinație interzisă?
     *
     * Predicat pentru dezactivarea opțiunilor în formular (cerința beneficiarului, 06.08.2026):
     * interfața trebuie să OPREASCĂ din start selecțiile invalide, nu să le corecteze după aceea
     * prin debifare automată — un câmp care se răzgândește singur pare defect.
     *
     * Un rol DEJA bifat nu se dezactivează niciodată: altfel o combinație invalidă venită din date
     * vechi ar rămâne blocată, fără cale de îndreptare din interfață.
     *
     * @param  list<string>  $selected
     */
    public static function blocksCombination(string $value, array $selected): bool
    {
        if (in_array($value, $selected, true)) {
            return false;
        }

        return ! self::isAllowedCombination([...$selected, $value]);
    }
}
