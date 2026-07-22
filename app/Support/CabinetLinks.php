<?php

namespace App\Support;

/**
 * Destinațiile de cabinet ale notificărilor — SURSĂ UNICĂ, ca fiecare notificare să ducă la
 * SECȚIUNEA relevantă, nu la profilul general. Înainte, mai mulți observeri puneau bare
 * `route('cabinet.student', …)` (tab implicit „Privire de ansamblu"), deci o notificare despre
 * absențe ateriza pe pagina generală, nu la absențe.
 *
 * Toate URL-urile sunt RELATIVE (deschiderea notificării acceptă doar ținte relative — vezi
 * NotificationsController::open) și poartă id-ul elevului fie în cale (`/cabinet/elev/{id}`,
 * pentru taburile fișei), fie ca `?copil={id}` (modulele de catalog) — ambele forme sunt
 * recunoscute de garda `studentIdFromUrl` din controller.
 */
final class CabinetLinks
{
    /** Modulul Absențe → registrul pe discipline (absență nouă). */
    public static function absenceRegister(int $studentId): string
    {
        return route('cabinet.absences', ['copil' => $studentId, 'sectiune' => 'registru'], absolute: false);
    }

    /** Modulul Absențe → motivările (verdict la o cerere de motivare). */
    public static function motivations(int $studentId): string
    {
        return route('cabinet.absences', ['copil' => $studentId, 'sectiune' => 'motivari'], absolute: false);
    }

    /** Modulul Note (notă nouă / anulată / corectată — valoarea vizibilă în cabinet). */
    public static function grades(int $studentId): string
    {
        return route('cabinet.grades', ['copil' => $studentId], absolute: false);
    }

    /** Fișa elevului, tabul „Cereri" (contestații, corigență, cereri tipice — nu au modul propriu). */
    public static function requests(int $studentId): string
    {
        return route('cabinet.student', ['student' => $studentId, 'tab' => 'requests'], absolute: false);
    }
}
