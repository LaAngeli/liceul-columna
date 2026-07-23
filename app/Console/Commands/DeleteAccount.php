<?php

namespace App\Console\Commands;

use App\Models\Audit;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ștergerea DEFINITIVĂ a unui cont — singura cale rămasă după ce butonul a fost scos din panou
 * (decizia beneficiarului 2026-07-23: în panou conturile se SUSPENDĂ, nu se șterg).
 *
 * De ce o comandă și nu un buton: ștergerea unui cont e ireversibilă (`User` n-are soft delete) și
 * duce cu ea, prin cascada FK, legăturile părinte–copil. E o operațiune juridică — dreptul la
 * ștergere (L133/2011) — nu o operațiune de zi cu zi, deci cere acces la server, un MOTIV scris și
 * lasă urmă în jurnalul de audit. Fișele legate (elev/profesor) NU se șterg: rămân în registru,
 * doar orfane de cont, exact ca la `app:demo-accounts --remove`.
 */
class DeleteAccount extends Command
{
    protected $signature = 'app:delete-account
        {identificator : Email sau nume de utilizator (username)}
        {--reason= : Motivul ștergerii (obligatoriu — rămâne în jurnalul de audit)}
        {--force : Fără confirmare interactivă}';

    protected $description = 'Șterge DEFINITIV un cont, la cererea explicită a persoanei (dreptul la ștergere). În panou conturile se suspendă.';

    public function handle(): int
    {
        $identifier = (string) $this->argument('identificator');

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if ($user === null) {
            $this->error(sprintf('Nu există niciun cont cu identificatorul „%s”.', $identifier));

            return self::FAILURE;
        }

        $reason = (string) ($this->option('reason') ?? '');

        if (trim($reason) === '') {
            $this->error('Motivul e obligatoriu: --reason="cerere de ștergere din 2026-07-23".');

            return self::FAILURE;
        }

        $student = Student::query()->where('user_id', $user->getKey())->first();
        $teacher = Teacher::query()->where('user_id', $user->getKey())->first();
        $children = $user->students()->pluck('students.id');

        $this->table(['Câmp', 'Valoare'], [
            ['Nume', $user->name],
            ['Email', $user->email ?? '—'],
            ['Utilizator', $user->username ?? '—'],
            ['Roluri', $user->getRoleNames()->implode(', ') ?: '—'],
            ['Fișă de elev legată', $student !== null ? $student->full_name : '—'],
            ['Fișă de profesor legată', $teacher !== null ? $teacher->full_name : '—'],
            ['Copii asociați (legături pierdute)', $children->count()],
        ]);

        $this->warn('Ștergerea e DEFINITIVĂ. Fișele rămân în registru, dar legăturile de părinte se pierd.');

        if (! $this->option('force') && ! $this->confirm('Confirmi ștergerea acestui cont?', false)) {
            $this->info('Anulat.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($user, $reason, $student, $teacher, $children): void {
            // Urma rămâne în jurnal ÎNAINTEA ștergerii: după ea, contul nu mai există nici ca id
            // referențiabil, iar fără această intrare ștergerea ar fi invizibilă la orice audit.
            Audit::query()->create([
                'user_type' => User::class,
                'user_id' => auth()->id(),
                'event' => 'forceDeleted',
                'auditable_type' => User::class,
                'auditable_id' => $user->getKey(),
                'old_values' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'roles' => $user->getRoleNames()->all(),
                    'student_record' => $student?->getKey(),
                    'teacher_record' => $teacher?->getKey(),
                    'guardian_of' => $children->all(),
                ],
                'new_values' => ['reason' => $reason],
                'tags' => 'right-to-erasure',
            ]);

            $user->delete();
        });

        $this->info(sprintf('Contul „%s” a fost șters definitiv. Motivul e consemnat în jurnalul de audit.', $user->name));

        return self::SUCCESS;
    }
}
