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

    /**
     * ⚠️ Contul „profesor" trebuie legat de o fișă FĂRĂ dirigenție (2026-07-27, după ce
     * beneficiarul a raportat că un cont etichetat „Profesor" valida motivări de absențe):
     * fișa #1 (Bujor-Cobili Carolina) e dirigintă la XI R, deci contul primea, pe bună dreptate,
     * drepturi de diriginte — dar sub eticheta „Profesor". Rezultatul: rolul de profesor SIMPLU
     * nu se putea testa deloc, iar comportamentul corect părea o breșă.
     * Fișa #34 (Ungureanu Vasile) predă la 16 clase și nu are nicio dirigenție → perimetru bogat
     * pentru testare, fără puteri de diriginte. Dirigenția se testează cu `diriginte@` (fișa #2).
     */
    private const TEACHER_LINKS = [
        'profesor@columna.test' => 34,
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
        $alreadyLinked = (int) $current === $profileId;

        if ($apply) {
            if (! $alreadyLinked) {
                // Eliberăm întâi fișa veche, apoi o legăm pe cea corectă: `user_id` e unic.
                DB::table($table)->where('user_id', $user->id)->update(['user_id' => null]);
                DB::table($table)->where('id', $profileId)->update(['user_id' => $user->id]);
            }

            // Numele contului URMEAZĂ fișa (identitatea persoanei stă în registru, nu pe cont):
            // altfel, după re-legare, contul păstra numele fișei VECHI și apărea în panou ca o
            // persoană care predă disciplinele alteia. Rulează și pe legături deja corecte —
            // altfel un nume rămas în urmă dintr-o rulare veche nu s-ar mai repara niciodată.
            // Marcajul [DEMO] rămâne, ca să nu iasă din plasa de curățare de la go-live.
            $this->syncDemoName($user, $table, $profileId);
        }

        if ($alreadyLinked) {
            return [$email, $label, "#{$profileId}", 'neschimbat'];
        }

        $changes++;

        return [$email, $label, $current ? "#{$current}" : '—', "#{$profileId}"];
    }

    /** Numele contului demo = numele fișei legate, cu marcajul [DEMO] păstrat. */
    private function syncDemoName(User $user, string $table, int $profileId): void
    {
        $profileName = DB::table($table)
            ->where('id', $profileId)
            ->selectRaw("TRIM(CONCAT(COALESCE(last_name, ''), ' ', COALESCE(first_name, ''))) AS full_name")
            ->value('full_name');

        if (blank($profileName)) {
            return;
        }

        $expected = DemoAccounts::MARKER.' '.$profileName;

        if ($user->name !== $expected) {
            DB::table('users')->where('id', $user->id)->update(['name' => $expected]);
        }
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
