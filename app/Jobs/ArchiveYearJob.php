<?php

namespace App\Jobs;

use App\Actions\ArchiveYearToTranscript;
use App\Models\AcademicYear;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Închiderea anului școlar pe QUEUE (2026-07-16): rulată sincron din panou, arhivarea depășea
 * limita PHP de 30s (mii de scrieri + audit per rând) și murea la mijloc. Job-ul rulează
 * acțiunea (atomică — tranzacție în ArchiveYearToTranscript) și anunță inițiatorul prin
 * clopoțelul din panou la final sau la eșec. Unic per an: două arhivări simultane ale
 * aceluiași an nu au sens și s-ar călca pe rânduri.
 */
class ArchiveYearJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Operațiune anuală pe toată școala — permitem minute bune, nu default-ul de 60s. */
    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        public readonly AcademicYear $year,
        public readonly int $initiatorId,
    ) {}

    public function uniqueId(): string
    {
        return 'archive-year-'.$this->year->getKey();
    }

    public function handle(ArchiveYearToTranscript $archiver): void
    {
        $result = $archiver->run($this->year);

        // Anul devine ÎNCHIS abia DUPĂ ce arhivarea a reușit: marcat înainte, o arhivare eșuată la
        // mijloc ar fi lăsat un an blocat la scriere și incomplet în foaia matricolă — cea mai
        // proastă combinație posibilă. Cine a închis rămâne consemnat: e un act cu răspundere.
        $this->year->forceFill([
            'closed_at' => now(),
            'closed_by_user_id' => $this->initiatorId,
        ])->save();

        $initiator = User::query()->find($this->initiatorId);

        if ($initiator === null) {
            return;
        }

        // Notificarea în limba inițiatorului (job-ul rulează în afara sesiunii lui).
        app()->setLocale($initiator->locale ?? (string) config('app.locale'));

        Notification::make()
            ->success()
            ->title(__('panel.actions.archive_year.success', [
                'records' => $result['records'],
                'students' => $result['students'],
            ]))
            ->sendToDatabase($initiator);

        if ($result['skipped'] > 0) {
            Notification::make()
                ->warning()
                ->title(__('panel.actions.archive_year.skipped', ['count' => $result['skipped']]))
                ->sendToDatabase($initiator);
        }
    }

    /** Eșecul nu are voie să rămână tăcut: operatorul așteaptă o arhivă închisă. */
    public function failed(?Throwable $exception): void
    {
        $initiator = User::query()->find($this->initiatorId);

        if ($initiator === null) {
            return;
        }

        app()->setLocale($initiator->locale ?? (string) config('app.locale'));

        Notification::make()
            ->danger()
            ->title(__('panel.actions.archive_year.failed', ['year' => $this->year->name]))
            ->sendToDatabase($initiator);
    }
}
