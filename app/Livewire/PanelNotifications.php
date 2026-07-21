<?php

namespace App\Livewire;

use App\Console\Commands\ArchiveNotifications;
use App\Models\DatabaseNotification;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Livewire\DatabaseNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Clopoțelul panoului, FĂRĂ ștergere (cerința beneficiarului, 2026-07-21): notificările nu pot fi
 * șterse de niciun rol — părăsesc lista doar prin arhivarea automată de retenție
 * ({@see ArchiveNotifications}). Componenta standard Filament permitea
 * ștergerea per notificare (X → `notificationClosed` → delete) și în masă („Șterge tot") — ambele
 * căi sunt neutralizate AICI, pe server (butoanele sunt și ascunse în UI, dar garanția e asta):
 * suprascrierile fără atributul `#[On]` deconectează și listener-ul de eveniment al părintelui.
 *
 * Clopoțelul arată doar notificările ACTIVE; arhiva completă e pe pagina „Notificările mele".
 */
class PanelNotifications extends DatabaseNotifications
{
    /**
     * Lista clopoțelului = doar notificările nearhivate (arhiva are pagina ei). Tipurile largi
     * vin din părinte (userul generic Filament) — la rulare e relația {@see User::notifications()},
     * deci modelul propriu {@see DatabaseNotification} cu coloana `archived_at`.
     *
     * @return Builder<Model>|Relation<Model, Model, mixed>
     */
    public function getNotificationsQuery(): Builder|Relation
    {
        return parent::getNotificationsQuery()->whereNull('archived_at');
    }

    /**
     * Neutralizat: în componenta standard, X-ul unei notificări o ȘTERGEA din bază. Fără `#[On]`,
     * evenimentul `notificationClosed` nu mai are listener; un apel direct al metodei e no-op.
     */
    public function removeNotification(string $id): void
    {
        // Intenționat gol — notificările nu se șterg (retenție prin arhivare, nu prin delete).
    }

    /** Neutralizat: „Șterge tot" din componenta standard ștergea întreaga listă. */
    public function clearNotifications(): void
    {
        // Intenționat gol — vezi removeNotification().
    }

    /** Butonul „Șterge tot" dispare din antetul modalului (blade-ul respectă isVisible). */
    public function clearNotificationsAction(): Action
    {
        return parent::clearNotificationsAction()->hidden();
    }
}
