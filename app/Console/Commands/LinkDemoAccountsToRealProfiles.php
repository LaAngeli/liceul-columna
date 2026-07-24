<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Leagă conturile demo de FIȘELE REALE, aceleași pe orice mediu.
 *
 * Conturile de prezentare trebuie să arate identic pe local și pe producție — altfel demonstrezi
 * un ecran și clientul vede altul. Divergența apărută pe 2026-07-24: pe prod rulase
 * `app:seed-demo-zone`, care creează elevi și clase FICTIVE, iar conturile demo s-au legat de
 * aceia (id 772/773); pe local rămăseseră legate de elevi reali din importul legacy (41/553/554).
 * Aceiași oameni există pe ambele medii cu aceleași id-uri — deci legătura, nu datele, era problema.
 *
 * Comanda e IDEMPOTENTĂ și nu atinge nicio dată academică: rescrie doar `students.user_id`,
 * `teachers.user_id` și pivotul părinte–copil. Fișele deconectate rămân intacte.
 */
class LinkDemoAccountsToRealProfiles extends Command
{
    protected $signature = 'app:link-demo-accounts {--apply : Scrie efectiv (implicit: doar raportează)}';

    protected $description = 'Leagă conturile demo de fișele reale (aceleași pe local și pe producție)';

    /**
     * Contul demo → fișa reală de care trebuie legat.
     * Id-urile provin din importul legacy, identice pe toate mediile.
     */
    private const STUDENT_LINKS = [
        'elev@columna.test' => 555,
        'elev2@columna.test' => 552,
    ];

    private const TEACHER_LINKS = [
        'profesor@columna.test' => 1,
        'diriginte@columna.test' => 2,
    ];

    /** Copiii contului de părinte — trei fișe, ca să se vadă și comutatorul de copil. */
    private const PARENT_CHILDREN = ['parinte@columna.test' => [41, 553, 554]];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rows = [];
        $changes = 0;

        foreach (self::STUDENT_LINKS as $email => $studentId) {
            $rows[] = $this->linkProfile($email, Student::class, $studentId, 'elev', $apply, $changes);
        }

        foreach (self::TEACHER_LINKS as $email => $teacherId) {
            $rows[] = $this->linkProfile($email, Teacher::class, $teacherId, 'profesor', $apply, $changes);
        }

        foreach (self::PARENT_CHILDREN as $email => $children) {
            $rows[] = $this->linkChildren($email, $children, $apply, $changes);
        }

        $this->table(['Cont', 'Legătură', 'Acum', 'Devine'], $rows);

        if ($changes === 0) {
            $this->info('Toate conturile demo sunt deja legate corect.');

            return self::SUCCESS;
        }

        if (! $apply) {
            $this->warn("DRY-RUN — nimic nu a fost scris. {$changes} legătură(i) ar fi schimbate.");
            $this->line('Rulează din nou cu --apply pentru a aplica.');

            return self::SUCCESS;
        }

        $this->info("Aplicat: {$changes} legătură(i) actualizate.");

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Student|Teacher>  $model
     * @return array<int, string>
     */
    private function linkProfile(string $email, string $model, int $profileId, string $label, bool $apply, int &$changes): array
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            return [$email, $label, 'cont inexistent', '—'];
        }

        $table = (new $model)->getTable();
        $current = DB::table($table)->where('user_id', $user->id)->value('id');

        if ((int) $current === $profileId) {
            return [$email, $label, "#{$profileId}", 'neschimbat'];
        }

        if ($apply) {
            // Eliberăm întâi fișa veche, apoi o legăm pe cea corectă: `user_id` e unic.
            DB::table($table)->where('user_id', $user->id)->update(['user_id' => null]);
            DB::table($table)->where('id', $profileId)->update(['user_id' => $user->id]);
        }

        $changes++;

        return [$email, $label, $current ? "#{$current}" : '—', "#{$profileId}"];
    }

    /**
     * @param  list<int>  $children
     * @return array<int, string>
     */
    private function linkChildren(string $email, array $children, bool $apply, int &$changes): array
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            return [$email, 'copii', 'cont inexistent', '—'];
        }

        $current = $user->students()->pluck('students.id')->sort()->values()->all();
        sort($children);

        if ($current === $children) {
            return [$email, 'copii', implode(',', $current), 'neschimbat'];
        }

        if ($apply) {
            $user->students()->sync($children);
        }

        $changes++;

        return [$email, 'copii', $current === [] ? '—' : implode(',', $current), implode(',', $children)];
    }
}
