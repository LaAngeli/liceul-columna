<?php

namespace App\Actions;

use App\Models\Student;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Trimite o notificare familiei unui elev (cont propriu + tutori). Rutarea pe canale o face
 * notificarea însăși, din preferințele fiecărui utilizator (spec §5).
 */
class NotifyStudentFamily
{
    public function send(Student $student, CatalogNotification $notification): void
    {
        $users = $student->notifiableUsers();

        if ($users->isNotEmpty()) {
            Notification::send($users, $notification);
        }
    }
}
