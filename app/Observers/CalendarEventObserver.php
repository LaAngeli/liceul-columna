<?php

namespace App\Observers;

use App\Enums\AudienceReach;
use App\Enums\CalendarEventScope;
use App\Enums\NotificationType;
use App\Models\CalendarEvent;
use App\Models\Student;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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
        // Audiența nominală se notifică SEPARAT, din pagina de creare, DUPĂ ce pivotul de elevi e
        // atașat ({@see notifyNominalCreation}) — la momentul acestui `created` pivotul e încă gol.
        if ($event->visibility_scope === CalendarEventScope::Students) {
            return;
        }

        $this->notifyFamilies($event, NotificationType::NewCalendarEvent);
    }

    /**
     * Notificarea de CREARE pentru audiența nominală — apelată din CreateCalendarEvent::afterCreate,
     * după sincronizarea elevilor vizați (altfel destinatarii ar fi goi).
     */
    public function notifyNominalCreation(CalendarEvent $event): void
    {
        $this->notifyFamilies($event, NotificationType::NewCalendarEvent);
    }

    public function deleted(CalendarEvent $event): void
    {
        if ($event->isForceDeleting()) {
            return;
        }

        // La anulare (soft-delete) pivotul încă există — nominalul își găsește destinatarii.
        $this->notifyFamilies($event, NotificationType::CalendarEventCancelled);
    }

    private function notifyFamilies(CalendarEvent $event, NotificationType $type): void
    {
        // Comutatorul „Anunță familiile" (implicit pornit): oprit → evenimentul doar apare în
        // calendar, fără notificare nici la creare, nici la anulare. Un singur punct de gardă,
        // deci acoperă și audiențele largi, și nominalele.
        if (! $event->notify_families) {
            return;
        }

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

        // Audiența nominală notifică EXACT după reach (elev / părinți / ambii); audiențele largi
        // notifică întreaga familie a elevilor din scope, ca la restul catalogului.
        $users = $event->visibility_scope === CalendarEventScope::Students
            ? $this->nominalRecipients($event)
            : $this->broadRecipients($event);

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new CatalogNotification(
            $type,
            ['title' => $event->title, 'date' => $event->starts_on->format('d.m.Y')],
            route('cabinet.calendar', absolute: false),
        ));
    }

    /**
     * Destinatarii unei audiențe LARGI: familiile elevilor din scope (global/treaptă/clasă),
     * deduplicate pe utilizator (un părinte cu doi copii în scope primește O notificare).
     *
     * @return Collection<int, User>
     */
    private function broadRecipients(CalendarEvent $event): Collection
    {
        return Student::query()
            ->with(['user', 'guardians'])
            ->whereHas('enrollments', function (Builder $enrollment) use ($event): void {
                if ($event->visibility_scope === CalendarEventScope::GradeLevel) {
                    $enrollment->whereHas('schoolClass', fn (Builder $class): Builder => $class->where('grade_level', $event->grade_level));
                } elseif ($event->visibility_scope === CalendarEventScope::SchoolClass) {
                    $enrollment->where('school_class_id', $event->school_class_id);
                }
                // Global: orice elev înmatriculat.
            })
            ->get()
            ->flatMap(fn (Student $student): Collection => $student->notifiableUsers())
            ->unique('id')
            ->values();
    }

    /**
     * Destinatarii unei audiențe NOMINALE: pentru fiecare elev vizat, doar cei pe care reach-ul îi
     * include — elevul însuși (contul lui) și/sau părinții. Deduplicat pe utilizator.
     *
     * @return Collection<int, User>
     */
    private function nominalRecipients(CalendarEvent $event): Collection
    {
        $reach = $event->audience_reach ?? AudienceReach::Both;

        return $event->students()
            ->with(['user', 'guardians'])
            ->get()
            ->flatMap(function (Student $student) use ($reach): Collection {
                /** @var Collection<int, User> $recipients */
                $recipients = collect();

                if ($reach->includesStudent() && $student->user !== null) {
                    $recipients->push($student->user);
                }

                if ($reach->includesGuardians()) {
                    $recipients = $recipients->concat($student->guardians);
                }

                return $recipients;
            })
            ->unique('id')
            ->values();
    }
}
