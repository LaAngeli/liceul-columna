<?php

namespace App\Filament\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pagină de listare cu navigator de catalog (drill-down pe clase / discipline / profesori /
 * perioade, în locul filtrelor). Tabelul resursei citește contextul prin acest contract —
 * vezi HasCatalogNavigator pentru implementare.
 */
interface CatalogNavigator
{
    /** Există un context de navigare valid (entitate primară aleasă și permisă)? */
    public function hasCatalogContext(): bool;

    /**
     * Aplică contextul de navigare pe interogarea tabelului. Contextul doar ÎNGUSTEAZĂ
     * interogarea deja scoped a resursei — un id falsificat nu poate lărgi accesul.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyCatalogContext(Builder $query): Builder;

    /** Id-ul de clasă activ în context (primar sau chip), validat pe scope — altfel null. */
    public function catalogClassIdInContext(): ?int;

    /** Id-ul de disciplină activ în context (primar sau chip), validat pe scope — altfel null. */
    public function catalogSubjectIdInContext(): ?int;

    /** Id-ul de semestru activ în context, validat — altfel null. */
    public function catalogTermIdInContext(): ?int;
}
