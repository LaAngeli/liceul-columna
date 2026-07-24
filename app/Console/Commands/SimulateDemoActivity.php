<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Simulează activitate DENSĂ (note / absențe / corecții / motivări / mesaje) pentru un cont [DEMO],
 * ca „Monitor activitate" să aibă ce afișa. TOTUL e reversibil:
 *   - câmpurile text (corecții/motivări/mesaje) sunt prefixate „[DEMO]";
 *   - note/absențe NU au câmp text → ID-urile create se scriu într-un MANIFEST
 *     (storage/app/demo/activity-{userId}.json), citit la `--remove`.
 * SIGURANȚĂ: operează exclusiv pe conturi al căror nume începe cu „[DEMO]" (nu atinge date reale).
 * Scrie prin query builder → fără observers/notificări/audit (fără efecte secundare, fără spam).
 */
class SimulateDemoActivity extends Command
{
    protected $signature = 'app:demo-activity
        {--name=Bujor-Cobili Carolina : Numele (fără prefixul [DEMO]) contului țintă}
        {--remove : Șterge activitatea creată anterior (din manifest + marcaje [DEMO])}';

    protected $description = 'Simulează / curăță activitate demo densă pentru un cont [DEMO] (pentru Monitor activitate)';

    private const TEXT = '[DEMO]';

    public function handle(): int
    {
        $user = User::query()
            ->where('name', self::TEXT.' '.$this->option('name'))
            ->first();

        if (! $user instanceof User) {
            $this->error("Cont [DEMO] negăsit pentru numele: {$this->option('name')}");

            return self::FAILURE;
        }

        // Apărare dublă: nu atingem niciodată un cont real.
        if (! str_starts_with($user->name, self::TEXT)) {
            $this->error('Refuz: contul nu e marcat [DEMO]. Comanda operează doar pe conturi demo.');

            return self::FAILURE;
        }

        $manifestPath = storage_path("app/demo/activity-{$user->id}.json");

        if ($this->option('remove')) {
            return $this->remove($user, $manifestPath);
        }

        return $this->seed($user, $manifestPath);
    }

    private function seed(User $user, string $manifestPath): int
    {
        if (File::exists($manifestPath)) {
            $this->error('Activitate demo deja creată pentru acest cont. Rulează întâi `--remove`.');

            return self::FAILURE;
        }

        $teacher = $user->teacher;
        if ($teacher === null) {
            $this->error('Contul nu are fișă de profesor — nu pot simula note/absențe.');

            return self::FAILURE;
        }

        $teacherId = $teacher->id;
        $homeroomClassIds = $teacher->homeroomSchoolClassIds();
        $termId = (int) DB::table('terms')->where('is_current', true)->value('id');
        if ($termId === 0) {
            $termId = (int) DB::table('terms')->value('id');
        }

        // Perechi valide (clasă → discipline predate) ∩ (clasă → elevi înscriși).
        $subjectsByClass = TeachingAssignment::query()
            ->where('teacher_id', $teacherId)
            ->get(['school_class_id', 'subject_id'])
            ->groupBy('school_class_id')
            ->map(fn ($rows) => $rows->pluck('subject_id')->unique()->values()->all());

        $studentsByClass = Enrollment::query()
            ->whereIn('school_class_id', $subjectsByClass->keys())
            ->whereNull('left_on')
            ->get(['school_class_id', 'student_id'])
            ->groupBy('school_class_id')
            ->map(fn ($rows) => $rows->pluck('student_id')->unique()->values()->all());

        /** @var list<int> $classPool clasele care au ȘI discipline, ȘI elevi */
        $classPool = $subjectsByClass->keys()
            ->filter(fn ($c) => isset($studentsByClass[$c]) && $studentsByClass[$c] !== [])
            ->values()
            ->all();

        if ($classPool === []) {
            $this->error('Profesorul nu are clase cu elevi înscriși — nu am ce popula.');

            return self::FAILURE;
        }

        /** @var list<int> $userPool destinatari/solicitanți (orice user real, nu contul demo) */
        $userPool = User::query()->where('id', '!=', $user->id)->limit(60)->pluck('id')->all();
        $familyUserId = $userPool[0] ?? $user->id;

        /** @var array{grades: list<int>, absences: list<int>, grade_corrections: list<int>, absence_motivations: list<int>, messages: list<int>} $manifest */
        $manifest = [
            'grades' => [],
            'absences' => [],
            'grade_corrections' => [],
            'absence_motivations' => [],
            'messages' => [],
        ];

        // 6 luni: fiecare lună primește un număr VARIAT de acțiuni (curbe care se mișcă vizibil).
        for ($m = 5; $m >= 0; $m--) {
            // Note (dense — miezul activității unui profesor).
            foreach (range(1, random_int(14, 30)) as $ignored) {
                $ts = $this->stamp($m);
                $classId = $classPool[array_rand($classPool)];
                $isTeza = random_int(1, 12) === 1;
                $manifest['grades'][] = (int) DB::table('grades')->insertGetId([
                    'student_id' => $this->pick($studentsByClass[$classId]),
                    'subject_id' => $this->pick($subjectsByClass[$classId]),
                    'school_class_id' => $classId,
                    'term_id' => $termId,
                    'teacher_id' => $teacherId,
                    'graded_on' => $ts->toDateString(),
                    'type' => 1,
                    'evaluation_type' => $isTeza ? 'teza' : 'curenta',
                    // ÎNTREG, nu zecimal: `random_int(50,100)/10` producea 6,5 / 7,3 — note pe care
                    // scala 1–10 nu le cunoaște (zecimalele aparțin mediilor). Cele 52.228 de
                    // note reale importate din sistemul școlii sunt TOATE întregi.
                    'value' => random_int(5, 10),
                    'calificativ' => null,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]);
            }

            // Absențe.
            foreach (range(1, random_int(8, 18)) as $ignored) {
                $ts = $this->stamp($m);
                $classId = $classPool[array_rand($classPool)];
                $manifest['absences'][] = (int) DB::table('absences')->insertGetId([
                    'student_id' => $this->pick($studentsByClass[$classId]),
                    'subject_id' => $this->pick($subjectsByClass[$classId]),
                    'school_class_id' => $classId,
                    'term_id' => $termId,
                    'teacher_id' => $teacherId,
                    'occurred_on' => $ts->toDateString(),
                    'is_motivated' => random_int(0, 1),
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]);
            }

            // Corecții cerute de profesor (pe propriile note — bucla de note de mai sus rulează mereu).
            foreach (range(1, random_int(2, 5)) as $ignored) {
                $ts = $this->stamp($m);
                $old = random_int(40, 90) / 10;
                $manifest['grade_corrections'][] = (int) DB::table('grade_corrections')->insertGetId([
                    'grade_id' => $manifest['grades'][array_rand($manifest['grades'])],
                    'requested_by_user_id' => $user->id,
                    'old_value' => number_format($old, 2, '.', ''),
                    'new_value' => number_format(min(10, $old + 1), 2, '.', ''),
                    'old_calificativ' => null,
                    'new_calificativ' => null,
                    'reason' => self::TEXT.' '.fake()->sentence(),
                    'status' => 'pending',
                    'reviewed_by_user_id' => null,
                    'reviewed_at' => null,
                    'review_note' => null,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]);
            }

            // Motivări revizuite de diriginte (dacă e diriginte).
            if ($homeroomClassIds !== [] && isset($studentsByClass[$homeroomClassIds[0]])) {
                $homeroomStudents = $studentsByClass[$homeroomClassIds[0]];
                foreach (range(1, random_int(2, 4)) as $ignored) {
                    $reviewed = $this->stamp($m);
                    $created = $reviewed->copy()->subDays(random_int(1, 3));
                    $manifest['absence_motivations'][] = (int) DB::table('absence_motivations')->insertGetId([
                        'student_id' => $this->pick($homeroomStudents),
                        'requested_by_user_id' => $familyUserId,
                        'reason' => self::TEXT.' '.fake()->sentence(),
                        'period_start' => $created->copy()->subDays(4)->toDateString(),
                        'period_end' => $created->copy()->subDays(1)->toDateString(),
                        'document_path' => null,
                        'status' => 'approved',
                        'is_exception' => false,
                        'reviewed_by_user_id' => $user->id,
                        'reviewed_at' => $reviewed,
                        'review_note' => self::TEXT.' '.fake()->sentence(4),
                        'created_at' => $created,
                        'updated_at' => $reviewed,
                    ]);
                }
            }

            // Mesaje trimise de profesor.
            foreach (range(1, random_int(4, 9)) as $ignored) {
                $ts = $this->stamp($m);
                $read = random_int(0, 1) === 1;
                $manifest['messages'][] = (int) DB::table('messages')->insertGetId([
                    'sender_user_id' => $user->id,
                    'recipient_user_id' => $userPool[array_rand($userPool)],
                    'student_id' => null,
                    'parent_id' => null,
                    'type' => 'direct',
                    'audience_domain' => null,
                    'subject' => self::TEXT.' '.fake()->sentence(3),
                    'body' => self::TEXT.' '.fake()->paragraph(),
                    'read_at' => $read ? $ts->copy()->addHours(random_int(1, 40)) : null,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                    'deleted_at' => null,
                ]);
            }
        }

        File::ensureDirectoryExists(dirname($manifestPath));
        File::put($manifestPath, (string) json_encode([
            'user_id' => $user->id,
            'name' => $user->name,
            'ids' => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Activitate demo creată pentru {$user->name}:");
        $this->table(['Serie', 'Rânduri'], collect($manifest)->map(fn (array $ids, string $k) => [$k, count($ids)])->values()->all());
        $this->line("Manifest: {$manifestPath}");
        $suffix = $this->option('name') !== 'Bujor-Cobili Carolina' ? " --name=\"{$this->option('name')}\"" : '';
        $this->line("Curățare: php artisan app:demo-activity --remove{$suffix}");

        return self::SUCCESS;
    }

    private function remove(User $user, string $manifestPath): int
    {
        if (! File::exists($manifestPath)) {
            $this->warn('Fără manifest — șterg doar rândurile marcate [DEMO] (corecții/motivări/mesaje) ale acestui cont.');
            $fallback = $this->removeByMarker($user);
            $this->info("Șterse prin marcaj [DEMO]: {$fallback} rânduri. Notele/absențele demo (fără marcaj) NU pot fi identificate fără manifest.");

            return self::SUCCESS;
        }

        /** @var array{ids?: array<string, list<int>>} $manifest */
        $manifest = json_decode((string) File::get($manifestPath), true);
        $ids = $manifest['ids'] ?? [];

        // Ordine sigură FK: corecțiile referă note → șterse înaintea notelor.
        $order = ['messages', 'absence_motivations', 'grade_corrections', 'absences', 'grades'];
        $deleted = [];
        foreach ($order as $table) {
            $rowIds = $ids[$table] ?? [];
            $deleted[$table] = $rowIds === [] ? 0 : DB::table($table)->whereIn('id', $rowIds)->delete();
        }

        File::delete($manifestPath);

        $this->info("Activitate demo ștearsă pentru {$user->name}:");
        $this->table(['Serie', 'Șterse'], collect($deleted)->map(fn (int $n, string $k) => [$k, $n])->values()->all());

        return self::SUCCESS;
    }

    /**
     * Fallback fără manifest: șterge rândurile marcate [DEMO] atribuite acestui user (nu note/absențe).
     */
    private function removeByMarker(User $user): int
    {
        $like = self::TEXT.'%';

        return DB::table('grade_corrections')->where('requested_by_user_id', $user->id)->where('reason', 'like', $like)->delete()
            + DB::table('absence_motivations')->where('reviewed_by_user_id', $user->id)->where('reason', 'like', $like)->delete()
            + DB::table('messages')->where('sender_user_id', $user->id)->where('subject', 'like', $like)->delete();
    }

    /**
     * Un timestamp aleator în luna aflată la `$monthsAgo` distanță (luna curentă = plafonat la acum).
     */
    private function stamp(int $monthsAgo): Carbon
    {
        $start = Carbon::now()->subMonths($monthsAgo)->startOfMonth();
        $end = $monthsAgo === 0 ? Carbon::now() : Carbon::now()->subMonths($monthsAgo)->endOfMonth();
        $span = max(1, (int) $start->diffInSeconds($end));

        return $start->copy()->addSeconds(random_int(0, $span));
    }

    /**
     * @param  list<int>  $pool
     */
    private function pick(array $pool): int
    {
        return $pool[array_rand($pool)];
    }
}
