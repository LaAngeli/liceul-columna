<?php

namespace App\Actions;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Publică un anunț al conducerii (spec §4): îl marchează publicat, reține câți destinatari are și
 * îl trimite ca notificare TUTUROR familiilor (părinți + elevi). Notificarea poartă `announcement_id`
 * în payload, ca să se poată număra confirmările de citire (`read_at`) per anunț.
 */
class BroadcastAnnouncement
{
    public function publish(Announcement $announcement): void
    {
        $families = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [UserRole::Parinte->value, UserRole::Elev->value]))
            ->get();

        $announcement->update([
            'published_at' => now(),
            'recipients_count' => $families->count(),
        ]);

        if ($families->isNotEmpty()) {
            Notification::send($families, new CatalogNotification(
                NotificationType::Announcement,
                url: route('cabinet.notifications', [], false),
                customTitle: $announcement->title,
                customBody: $announcement->body,
                meta: ['announcement_id' => $announcement->id],
            ));
        }
    }
}
