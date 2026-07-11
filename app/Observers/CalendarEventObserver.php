<?php

namespace App\Observers;

use App\Enums\CalendarEventScope;
use App\Enums\NotificationType;
use App\Models\CalendarEvent;
use App\Models\Student;
use App\Notifications\CatalogNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Notifică FAMILIILE din scope la crearea și la anularea (soft-delete) unui eveniment de calendar
 * (decizia userului, 2026-07-12): altfel familia descoperea evenimentul — sau dispariția lui —
 * doar deschizând calendarul. Modificările NU notifică (ar fi zgomot la fiecare corectură de text);
 * evenimentele TRECUTE nu notifică (bookkeeping). Audiența = exact familiile care VĂD evenimentul
 * în cabinet (global / treaptă / clasă — {@see CalendarEvent::scopeVisibleToClass}).
 */
class CalendarEventObserver
{
    public function created(CalendarEvent $event): void
    {
        $this->notifyFamilies($event, NotificationType::NewCalendarEvent);
    }

    public function deleted(CalendarEvent $event): void
    {
        if ($event->isForceDeleting()) {
            return;
        }

        $this->notifyFamilies($event, NotificationType::CalendarEventCancelled);
    }

    private function notifyFamilies(CalendarEvent $event, NotificationType $type): void
    {
        // Seederele/comenzile de consolă nu declanșează valul de notificări (ca importul legacy);
        // în producție evenimentele se creează din panou (web). Testele rămân acoperite.
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        // Doar evenimente viitoare sau în desfășurare.
        $lastDay = $event->ends_on ?? $event->starts_on;

        if ($lastDay->lt(today())) {
            return;
        }

        $students = Student::query()
            ->with(['user', 'guardians'])
            ->whereHas('enrollments', function (Builder $enrollment) use ($event): void {
                if ($event->visibility_scope === CalendarEventScope::GradeLevel) {
                    $enrollment->whereHas('schoolClass', fn (Builder $class): Builder => $class->where('grade_level', $event->grade_level));
                } elseif ($event->visibility_scope === CalendarEventScope::SchoolClass) {
                    $enrollment->where('school_class_id', $event->school_class_id);
                }
                // Global: orice elev înmatriculat.
            })
            ->get();

        // Deduplicat pe utilizator: un părinte cu doi copii în scope primește O notificare.
        $users = $students
            ->flatMap(fn (Student $student) => $student->notifiableUsers())
            ->unique('id')
            ->values();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new CatalogNotification(
            $type,
            ['title' => $event->title, 'date' => $event->starts_on->format('d.m.Y')],
            route('cabinet.calendar', absolute: false),
        ));
    }
}
