<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Detectorul de „incidente" de testare: ce au creat/modificat conturile demo PRIN INTERFAȚĂ și,
 * mai ales, dacă vreo notă/absență/motivare a nimerit pe un elev REAL (nu demo).
 *
 * Cum funcționează: owen-it/auditing înregistrează în `audits` cine (user_id) a creat/modificat
 * fiecare entitate auditabilă prin Eloquent (interfața). Deci nu-i nevoie de „un id separat" —
 * indicatorul există deja. Comanda interoghează `audits` pentru acțiunile conturilor [DEMO] și
 * încrucișează cu tabelele operaționale ca să vadă dacă ținta e un elev demo sau unul real.
 *
 * ⚠️ Acoperă doar acțiunile prin interfață (Eloquent). Inserările în masă prin query builder (import,
 * seed) NU se auditează — dar testerii lucrează prin interfață, deci sunt acoperiți. Read-only.
 */
class DemoAudit extends Command
{
    protected $signature = 'app:demo-audit {--incidents-only : Afișează doar rândurile ajunse pe elevi REALI}';

    protected $description = 'Raport: ce au atins conturile demo prin interfață (din audit), semnalând ce a nimerit pe elevi REALI';

    /**
     * Modele operaționale legate direct de un elev (tabelul are `student_id`). `soft` = tabelul are
     * `deleted_at` → excludem rândurile soft-șterse (deja tratate), ca să nu raportăm zgomot.
     */
    private const STUDENT_MODELS = [
        'App\\Models\\Grade' => ['table' => 'grades', 'label' => 'Notă', 'soft' => true],
        'App\\Models\\Absence' => ['table' => 'absences', 'label' => 'Absență', 'soft' => true],
        'App\\Models\\AbsenceMotivation' => ['table' => 'absence_motivations', 'label' => 'Motivare', 'soft' => false],
    ];

    public function handle(): int
    {
        $demoUserIds = DB::table('users')->where('name', 'like', '[DEMO]%')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($demoUserIds === []) {
            $this->warn('Niciun cont demo ([DEMO]) în sistem — nimic de auditat.');

            return self::SUCCESS;
        }

        $demoStudentIds = DB::table('students')->where('last_name', 'like', '[DEMO]%')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if (! $this->option('incidents-only')) {
            $this->summary($demoUserIds);
        }

        return $this->incidents($demoUserIds, $demoStudentIds);
    }

    /**
     * Sumar: toate acțiunile auditate ale conturilor demo, pe tip + eveniment.
     *
     * @param  array<int, int>  $demoUserIds
     */
    private function summary(array $demoUserIds): void
    {
        $rows = DB::table('audits')
            ->whereIn('user_id', $demoUserIds)
            ->selectRaw('auditable_type, event, COUNT(*) AS n')
            ->groupBy('auditable_type', 'event')
            ->orderByDesc('n')
            ->get();

        $this->info('Acțiuni ale conturilor demo, prin interfață (din jurnalul de audit):');

        if ($rows->isEmpty()) {
            $this->line('  (niciuna — conturile demo n-au creat/modificat nimic prin interfață încă)');
            $this->newLine();

            return;
        }

        $this->table(
            ['Entitate', 'Acțiune', 'Nr.'],
            $rows->map(fn (object $r): array => [
                str_replace('App\\Models\\', '', (string) $r->auditable_type),
                (string) $r->event,
                (int) $r->n,
            ])->all(),
        );
    }

    /**
     * Incidente: rânduri operaționale aflate ACUM pe elevi REALI, create de conturi demo.
     *
     * @param  array<int, int>  $demoUserIds
     * @param  array<int, int>  $demoStudentIds
     */
    private function incidents(array $demoUserIds, array $demoStudentIds): int
    {
        $incidents = [];

        foreach (self::STUDENT_MODELS as $type => $meta) {
            $table = $meta['table'];

            $auditIds = DB::table('audits')
                ->whereIn('user_id', $demoUserIds)
                ->where('auditable_type', $type)
                ->where('event', 'created')
                ->pluck('auditable_id');

            if ($auditIds->isEmpty()) {
                continue;
            }

            $rows = DB::table($table)
                ->join('students', 'students.id', '=', $table.'.student_id')
                ->whereIn($table.'.id', $auditIds)
                ->when($meta['soft'], fn (Builder $q): Builder => $q->whereNull($table.'.deleted_at'))
                ->when($demoStudentIds !== [], fn (Builder $q): Builder => $q->whereNotIn($table.'.student_id', $demoStudentIds))
                ->get([$table.'.id', 'students.id AS student_id', 'students.last_name', 'students.first_name']);

            foreach ($rows as $row) {
                $incidents[] = [
                    $meta['label'],
                    (string) $row->id,
                    trim(((string) $row->last_name).' '.((string) $row->first_name))." (elev #{$row->student_id})",
                ];
            }
        }

        $this->newLine();

        if ($incidents === []) {
            $this->info('✅ Niciun incident: conturile demo nu au creat date operaționale pe elevi REALI.');

            return self::SUCCESS;
        }

        $this->error('⚠️ INCIDENTE — date create de conturi demo pe elevi REALI ('.count($incidents).'):');
        $this->table(['Tip', 'ID rând', 'Elev REAL afectat'], $incidents);
        $this->line('Aceste rânduri sunt pe elevi reali — verifică-le și șterge-le manual dacă sunt artefacte de testare.');

        return self::SUCCESS;
    }
}
