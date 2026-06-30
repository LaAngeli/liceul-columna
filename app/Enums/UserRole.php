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
     * Toate valorile string ale rolurilor.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
