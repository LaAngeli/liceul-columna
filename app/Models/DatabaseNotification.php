<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * Extinde modelul de notificare Laravel cu STAREA DE ARHIVĂ (cerința beneficiarului, 2026-07-21):
 * o notificare e „activă" (în lista principală) cât timp `archived_at` e null și trece în arhivă —
 * fără a fi ștearsă vreodată de utilizatori — prin măturarea zilnică (`app:archive-notifications`).
 * Aceeași tabelă `notifications`; {@see User::notifications()} leagă relația de acest model,
 * deci cabinetul, clopoțelul Filament și paginile de inbox lucrează toate pe el.
 *
 * @property Carbon|null $archived_at
 */
class DatabaseNotification extends BaseDatabaseNotification
{
    /**
     * Notificările din lista principală (nearhivate).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Notificările din arhivă.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** Mută notificarea în arhivă (idempotent) — nu atinge starea citit/necitit. */
    public function archive(): void
    {
        if ($this->archived_at === null) {
            $this->forceFill(['archived_at' => $this->freshTimestamp()])->save();
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'archived_at' => 'datetime',
        ]);
    }
}
