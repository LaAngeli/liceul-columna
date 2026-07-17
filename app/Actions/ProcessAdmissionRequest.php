<?php

namespace App\Actions;

use App\Enums\AdmissionStatus;
use App\Models\AdmissionRequest;
use App\Models\User;

/**
 * Procesarea unei cereri de înscriere (spec: intake-ul admiterii) — fiecare tranziție lasă
 * URMĂ: cine a lucrat cererea, când a fost contactată familia, când și cu ce notă s-a închis.
 * Sursa unică pentru panou (tabel + fișa cererii) și pentru orice API viitor.
 */
class ProcessAdmissionRequest
{
    /** Familia a fost contactată: cererea intră „în lucru" (rămâne în coadă până la decizie). */
    public function markContacted(AdmissionRequest $request, User $actor): void
    {
        $request->forceFill([
            'status' => AdmissionStatus::Contactat,
            'contacted_at' => $request->contacted_at ?? now(),
            'processed_by_id' => $actor->getKey(),
        ])->save();
    }

    /** Închide cererea cu succes: copilul urmează să fie înmatriculat (fluxul de onboarding). */
    public function enroll(AdmissionRequest $request, User $actor, ?string $note = null): void
    {
        $this->close($request, $actor, AdmissionStatus::Inmatriculat, $note);
    }

    /** Închide cererea cu refuz — nota internă (motivul) e obligatorie pentru arhivă. */
    public function refuse(AdmissionRequest $request, User $actor, string $note): void
    {
        $this->close($request, $actor, AdmissionStatus::Refuzat, $note);
    }

    /**
     * Redeschide o cerere închisă (decizie greșită / familia a revenit): înapoi „în lucru".
     * Momentul contactării rămâne (e istoric real); decizia și autorul ei se șterg.
     */
    public function reopen(AdmissionRequest $request, User $actor): void
    {
        $request->forceFill([
            'status' => $request->contacted_at !== null ? AdmissionStatus::Contactat : AdmissionStatus::Nou,
            'processed_at' => null,
            'processed_by_id' => $actor->getKey(),
        ])->save();
    }

    private function close(AdmissionRequest $request, User $actor, AdmissionStatus $status, ?string $note): void
    {
        $request->forceFill([
            'status' => $status,
            // O cerere închisă direct din „Nou" a presupus oricum un contact — completăm momentul.
            'contacted_at' => $request->contacted_at ?? now(),
            'processed_at' => now(),
            'processed_by_id' => $actor->getKey(),
            'staff_note' => filled($note) ? $note : $request->staff_note,
        ])->save();
    }
}
