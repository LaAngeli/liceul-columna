<?php

namespace App\Observers;

use App\Actions\RealignTermAssignments;
use App\Models\Term;

/**
 * La mutarea granițelor unui semestru, evaluările existente rămâneau în semestrul derivat la
 * introducere → medii pe o componență depășită. Realinierea rulează pe orice cale de scriere
 * (pagină de editare, API viitor), nu doar în UI — vezi {@see RealignTermAssignments}.
 */
class TermObserver
{
    public function __construct(
        private RealignTermAssignments $realigner,
    ) {}

    public function updated(Term $term): void
    {
        if (! $term->wasChanged(['starts_on', 'ends_on'])) {
            return;
        }

        $this->realigner->run($term);
    }
}
