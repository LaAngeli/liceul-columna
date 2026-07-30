<?php

namespace App\Observers;

use App\Models\HomeworkAssignment;
use App\Models\HomeworkCorrection;

/**
 * Registrul corecțiilor DIRECTE (2026-07-31): orice schimbare a CONȚINUTULUI temei (subiect /
 * sarcină obligatorie / sarcină opțională) operată din interfață se consemnează automat în
 * {@see HomeworkCorrection} — vechi → nou, cine, când. Fără aprobare: dreptul de editare e
 * tranșat de policy (autor / dirigintele clasei / administrația); aici doar se lasă urma.
 *
 * DOAR pe cereri autentificate web: seed-erele și comenzile de consolă își consemnează singure
 * corecțiile (cu marcajele lor) — altfel fiecare rulare ar dubla registrul.
 */
class HomeworkAssignmentObserver
{
    private const CONTENT_FIELDS = ['topic', 'required_task', 'optional_task'];

    public function updated(HomeworkAssignment $homework): void
    {
        if (! auth('web')->check()) {
            return;
        }

        $changed = [];
        $old = [];

        foreach (self::CONTENT_FIELDS as $field) {
            if ($homework->wasChanged($field)) {
                $changed[$field] = $homework->getAttribute($field);
                $old[$field] = $homework->getOriginal($field);
            }
        }

        if ($changed === []) {
            return;
        }

        HomeworkCorrection::recordApplied($homework, $old, $changed, (int) auth('web')->id());
    }
}
