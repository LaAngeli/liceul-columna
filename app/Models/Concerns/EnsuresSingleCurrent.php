<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Garantează invariantul „cel mult un rând cu is_current = true" pe model: la salvarea unui rând cu
 * is_current trecut pe true, toate celelalte sunt puse pe false (prin query builder, fără a declanșa
 * alte evenimente → fără recursie). Tot codul (derivarea semestrului, fallback-uri, dinamica) presupune
 * un singur curent; formularul putea rupe invariantul setând manual toggle-ul (audit M-2). Pentru Term,
 * sursa de adevăr rămâne intervalele de date (comanda app:sync-current-term); acest gard e plasa de
 * siguranță pentru orice cale de scriere prin model.
 */
trait EnsuresSingleCurrent
{
    public static function bootEnsuresSingleCurrent(): void
    {
        static::saved(static function (Model $model): void {
            if ($model->wasChanged('is_current') && $model->getAttribute('is_current') === true) {
                $model->newQuery()->whereKeyNot($model->getKey())->update(['is_current' => false]);
            }
        });
    }
}
