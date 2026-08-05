<?php

namespace App\Observers;

use App\Models\HomeworkAssignment;
use App\Models\HomeworkCorrection;
use Illuminate\Support\Facades\Storage;

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
        // Igiena discului înaintea gardului de autentificare: fișierele scoase din temă se șterg
        // indiferent cine a operat (interfață sau comandă) — altfel s-ar aduna orfani în storage.
        $this->deleteRemovedAttachments($homework);

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

    /**
     * Ștergerea DEFINITIVĂ ia cu ea și fișierele atașate. Cea logică (coșul de restaurare) NU:
     * tema restaurată trebuie să-și regăsească fișele.
     */
    public function forceDeleted(HomeworkAssignment $homework): void
    {
        $this->deleteAttachmentFiles($homework->attachments ?? []);
    }

    private function deleteRemovedAttachments(HomeworkAssignment $homework): void
    {
        if (! $homework->wasChanged('attachments')) {
            return;
        }

        /** @var array<int, string> $previous */
        $previous = (array) json_decode((string) ($homework->getRawOriginal('attachments') ?? '[]'), true);

        $this->deleteAttachmentFiles(array_diff($previous, $homework->attachments ?? []));
    }

    /**
     * Șterge DOAR din directorul temelor — o cale rătăcită în coloană (import, editare manuală)
     * nu poate atinge alte fișiere private (justificative, PDF-uri de cereri).
     *
     * @param  array<int, string>  $paths
     */
    private function deleteAttachmentFiles(array $paths): void
    {
        $own = array_values(array_filter(
            $paths,
            fn (string $path): bool => str_starts_with($path, 'homework-attachments/'),
        ));

        if ($own !== []) {
            Storage::disk('local')->delete($own);
        }
    }
}
