<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                // Fără scope-ul SoftDeletes (echivalent withTrashed): și un rând ARHIVAT trebuie
                // stins, altfel restaurarea lui ar readuce un al doilea „curent" (modelele care
                // folosesc trait-ul au toate SoftDeletes).
                $model->newQuery()->withoutGlobalScope(SoftDeletingScope::class)
                    ->whereKeyNot($model->getKey())->update(['is_current' => false]);
            }
        });

        // Plasa la RESTAURARE: un rând fost-curent, arhivat înainte ca garda withTrashed de mai sus
        // să existe (sau stins ulterior), nu are voie să revină ca AL DOILEA curent — cel activ câștigă.
        static::restored(static function (Model $model): void {
            if ($model->getAttribute('is_current') === true
                && $model->newQuery()->whereKeyNot($model->getKey())->where('is_current', true)->exists()) {
                $model->forceFill(['is_current' => false])->saveQuietly();
            }
        });
    }
}
