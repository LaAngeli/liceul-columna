<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Director = 'director';
    case DirectorAdjunct = 'director-adjunct';
    case Diriginte = 'diriginte';
    case Profesor = 'profesor';
    case Elev = 'elev';
    case Parinte = 'parinte';

    /**
     * Eticheta afișată în interfață (RO).
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Director => 'Director',
            self::DirectorAdjunct => 'Director adjunct',
            self::Diriginte => 'Diriginte',
            self::Profesor => 'Profesor',
            self::Elev => 'Elev',
            self::Parinte => 'Părinte',
        };
    }

    /**
     * Rolurile care au acces la panoul de gestiune Filament (personalul școlii).
     * Elevii și părinții folosesc cabinetul Inertia, nu panoul.
     *
     * @return list<self>
     */
    public static function panelRoles(): array
    {
        return [self::Admin, self::Director, self::DirectorAdjunct, self::Diriginte, self::Profesor];
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
     * Toate valorile string ale rolurilor.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
