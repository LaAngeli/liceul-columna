<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ⚠️ OBSOLETĂ ÎN FAZA DE TEST — subordonată zonei demo (2026-07-27).
 *
 * Leagă conturile demo de FIȘELE REALE, aceleași pe orice mediu. Problema pe care o rezolva era
 * reală (2026-07-24): conturile de prezentare arătau alte date pe local decât pe producție, fiindcă
 * pe prod rulase `app:seed-demo-zone` și se legaseră de elevii FICTIVI ai zonei, iar pe local
 * rămăseseră pe elevi reali din importul legacy. Demonstrai un ecran, clientul vedea altul.
 *
 * Dar METODA era greșită, și contrazice principiul care guvernează întreaga fază de test:
 * DELIMITAREA demo/real, ca la go-live curățarea să fie totală și sigură. Cu conturile demo pe fișe
 * reale, testerii produc note, absențe și mesaje pe elevi REALI — iar ce fac prin INTERFAȚĂ nu intră
 * în niciun manifest, deci nu se mai poate identifica, darămite curăța.
 *
 * Răspunsul corect la aceeași problemă de paritate e `app:seed-demo-zone`: aceeași școală demo pe
 * ambele medii, integral prefixată „[DEMO]", cu legăturile reale salvate în manifest și restaurate
 * la `--remove`. Comanda de față REFUZĂ acum să ruleze cât timp zona demo există ({@see handle}),
 * ca să nu scoată conturile din zonă și s-o lase orfană.
 *
 * Rămâne utilă doar pe un mediu FĂRĂ zonă demo. E IDEMPOTENTĂ și nu atinge nicio dată academică:
 * rescrie doar `students.user_id`, `teachers.user_id` și pivotul părinte–copil.
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
        // ZONA DEMO ARE ÎNTÂIETATE (2026-07-27). Comanda asta rezolva o problemă reală — conturile
        // demo arătau alte date pe local decât pe producție — dar prin metoda GREȘITĂ: legându-le
        // de fișe REALE. Consecința e exact ce trebuie evitat până la go-live: testerii produc note,
        // absențe și mesaje pe elevi REALI, iar ce fac prin interfață nu e urmărit de niciun
        // manifest, deci nu se mai poate curăța fără să atingi date reale.
        //
        // Răspunsul corect la aceeași problemă e `app:seed-demo-zone`: o școală demo paralelă,
        // integral prefixată „[DEMO]", în care conturile aterizează — curățare 100% reversibilă.
        // Cât timp zona există, comanda asta ar rupe-o (ar scoate conturile din zonă, lăsând-o
        // orfană), deci refuză să ruleze.
        if (file_exists(storage_path('app/demo/zone.json'))) {
            $this->error('Există o zonă demo activă (storage/app/demo/zone.json).');
            $this->line('Conturile demo trebuie să rămână legate de ZONA demo, nu de fișe reale — altfel');
            $this->line('testerii lucrează pe elevi reali și datele lor nu se mai pot curăța la go-live.');
            $this->line('Dacă chiar vrei conturile pe date reale, elimină întâi zona: `php artisan app:seed-demo-zone --remove`.');

            return self::FAILURE;
        }

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
