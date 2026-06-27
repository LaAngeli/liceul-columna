<?php

namespace App\Actions;

use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Trimite o notificare „de nișă" personalului (spec §5): fie tuturor conturilor cu unul dintre
 * rolurile date, fie unui cont anume (ex. dirigintele clasei). Rutarea pe canale (cabinet/email/
 * social) + limba o face notificarea însăși, din preferințele fiecărui destinatar.
 */
class NotifyStaff
{
    /**
     * @param  list<string>  $roleValues
     */
    public function byRole(array $roleValues, CatalogNotification $notification): void
    {
        if ($roleValues === []) {
            return;
        }

        $users = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roleValues))
            ->get();

        if ($users->isNotEmpty()) {
            Notification::send($users, $notification);
        }
    }

    public function toUser(?User $user, CatalogNotification $notification): void
    {
        $user?->notify($notification);
    }
}
