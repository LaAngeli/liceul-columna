<?php

namespace App\Filament\Widgets\Concerns;

/**
 * Helper pentru stilul „cockpit" al stat-card-urilor (direcția C aleasă de user): fiecare metrică
 * primește un accent top-border colorat — navy pentru informațional, cărămiziu pentru „necesită
 * acțiune". Clasele CSS trăiesc în `resources/css/filament/admin/theme.css` (`.fi-cockpit-stat*`).
 */
trait CockpitStats
{
    /**
     * Atributele extra pentru o Stat cockpit. `$alert = true` → top-border de alertă (cărămiziu),
     * folosit pe metricile care cer intervenție (corigenți, elevi de urmărit, clase fără diriginte,
     * parole neschimbate). Altfel top-border navy neutru.
     *
     * @return array<string, string>
     */
    protected static function cockpit(bool $alert = false): array
    {
        return ['class' => $alert ? 'fi-cockpit-stat fi-cockpit-stat--alert' : 'fi-cockpit-stat'];
    }
}
