<?php

namespace App\Jobs;

use App\Actions\ComputeTermAverage;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recalculează media semestrială (cache în `term_averages`) pentru un triplet
 * (elev, disciplină, semestru), pe coadă — ca salvarea unei note din panou să nu
 * aștepte calculul în request (spec §5: munca derivată nu blochează utilizatorul).
 *
 * {@see ShouldBeUniqueUntilProcessing}: salvările repetate ale aceleiași note
 * colapsează într-un singur job în coadă; lacătul se eliberează la începutul
 * procesării, deci o modificare ulterioară reprogramează un recalcul proaspăt.
 */
class RecomputeTermAverage implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $studentId,
        public int $subjectId,
        public int $termId,
    ) {}

    public function handle(ComputeTermAverage $compute): void
    {
        $compute->execute($this->studentId, $this->subjectId, $this->termId);
    }

    public function uniqueId(): string
    {
        return $this->studentId.':'.$this->subjectId.':'.$this->termId;
    }
}
