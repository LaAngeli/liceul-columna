<?php

namespace App\Console\Commands;

use App\Models\AbsenceMotivation;
use App\Models\DocumentRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Retenția datelor (Legea 133/2011, spec §7): după expirarea perioadei de păstrare (implicit 12 ani
 * de la plecarea elevului), dosarul se ȘTERGE definitiv. `forceDelete` pe elev declanșează cascada
 * BD → dispar note/absențe/matricolă/medii/înrolări/cereri/motivări/legături tutori; fișierele PII de
 * pe disc (PDF-uri/justificative) se șterg explicit, iar contul de login rezidual se anonimizează.
 *
 * IREVERSIBIL. Implicit rulează în SIMULARE (dry-run); ștergerea efectivă cere `--force`. NU e pus pe
 * scheduler automat — se rulează deliberat, după verificarea listei.
 */
class PurgeExpiredStudents extends Command
{
    protected $signature = 'app:purge-expired-students {--force : Execută ștergerea definitivă (altfel doar simulează)} {--years=12 : Perioada de retenție, în ani}';

    protected $description = 'Șterge definitiv dosarele elevilor cu retenția expirată (Legea 133 §7).';

    public function handle(): int
    {
        $years = max(1, (int) $this->option('years'));
        $cutoff = Carbon::now()->subYears($years);

        $eligible = $this->eligible($cutoff);

        if ($eligible->isEmpty()) {
            $this->info("Niciun elev cu retenția expirată (peste {$years} ani). Nimic de șters.");

            return self::SUCCESS;
        }

        $this->warn("Elevi cu retenția expirată (plecați înainte de {$cutoff->format('d.m.Y')}): {$eligible->count()}");
        $this->table(
            ['ID', 'Elev', 'Plecat la'],
            $eligible->map(fn (Student $student): array => [
                $student->id,
                $student->full_name,
                $this->leftDate($student)?->format('d.m.Y') ?? '—',
            ])->all(),
        );

        if (! $this->option('force')) {
            $this->comment('SIMULARE (dry-run). Nimic șters. Rulează cu --force pentru ștergere DEFINITIVĂ — ireversibilă.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($eligible as $student) {
            try {
                $this->erase($student);
                $deleted++;
            } catch (Throwable $e) {
                $this->error("Eșec la elevul #{$student->id}: {$e->getMessage()}");
                Log::error('Retenție L133: ștergere eșuată', ['student_id' => $student->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Șterse definitiv: {$deleted} dosare de elev.");
        Log::warning("Retenție L133: {$deleted} dosare de elev șterse definitiv (retenție >{$years} ani).");

        return self::SUCCESS;
    }

    /**
     * Elevi eligibili pentru ștergere: cei care NU mai au nicio înrolare activă și a căror ultimă
     * plecare e înainte de cutoff, plus cei arhivați (soft-deleted) demult. Conservator — fără dată
     * de plecare cunoscută, elevul NU e atins.
     *
     * @return Collection<int, Student>
     */
    private function eligible(Carbon $cutoff): Collection
    {
        $leftLongAgo = Student::query()
            ->whereDoesntHave('enrollments', fn ($query) => $query->whereNull('left_on'))
            ->whereHas('enrollments', fn ($query) => $query->whereNotNull('left_on'))
            ->whereDoesntHave('enrollments', fn ($query) => $query->where('left_on', '>=', $cutoff))
            ->get();

        $archivedLongAgo = Student::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->get();

        return $leftLongAgo->concat($archivedLongAgo)->unique('id')->values();
    }

    private function leftDate(Student $student): ?Carbon
    {
        $max = $student->enrollments()->max('left_on');

        return $max !== null ? Carbon::parse((string) $max) : null;
    }

    /**
     * Ștergerea definitivă a unui dosar de elev + fișierele PII de pe disc + anonimizarea contului
     * de login rezidual.
     */
    private function erase(Student $student): void
    {
        // Căile fișierelor PII se colectează ÎNAINTE de ștergere (cascada BD le pierde rândurile).
        $files = $this->piiFilePaths($student);
        $userId = $student->user_id;

        $student->forceDelete();

        foreach ($files as $path) {
            Storage::disk('local')->delete($path);
        }

        $this->anonymizeUser($userId);
    }

    /**
     * @return list<string>
     */
    private function piiFilePaths(Student $student): array
    {
        $paths = [];

        $candidates = DocumentRequest::withTrashed()
            ->where('student_id', $student->id)
            ->pluck('pdf_path')
            ->merge(AbsenceMotivation::query()->where('student_id', $student->id)->pluck('document_path'));

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Contul de login rezidual al elevului se de-identifică (FK-safe), nu se șterge — referințele
     * istorice (mesaje/audit) rămân valide, dar fără PII. Erasure-ul complet al contului (+ mesaje/
     * notificări) rămâne o rafinare ulterioară.
     */
    private function anonymizeUser(?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        User::query()->whereKey($userId)->update([
            'name' => 'Elev șters (retenție)',
            'username' => null,
            'email' => null,
        ]);
    }
}
