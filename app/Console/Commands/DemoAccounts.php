<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Console\Command;

class DemoAccounts extends Command
{
    protected $signature = 'app:demo-accounts {--remove : Șterge definitiv toate conturile demo}';

    protected $description = 'Evidența conturilor demo/test (marcate [DEMO]): le listează sau, cu --remove, le șterge curat.';

    /**
     * Marcajul care identifică un cont demo — prefix obligatoriu în `name`.
     * Conturile reale (create prin app:create-admin sau din panou) NU îl au, deci supraviețuiesc curățării.
     */
    public const MARKER = '[DEMO]';

    public function handle(): int
    {
        $accounts = User::query()
            ->where('name', 'like', self::MARKER.'%')
            ->orderBy('email')
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('Nu există conturi demo (nume cu prefix '.self::MARKER.').');

            return self::SUCCESS;
        }

        $this->table(
            ['Email', 'Nume', 'Roluri'],
            $accounts->map(fn (User $user): array => [
                $user->email,
                $user->name,
                $user->getRoleNames()->implode(', ') ?: '—',
            ])->all(),
        );

        if (! $this->option('remove')) {
            $this->info($accounts->count().' cont(uri) demo. Rulează `php artisan app:demo-accounts --remove` pentru a le șterge.');

            return self::SUCCESS;
        }

        // Dezlegăm fișele reale (elev/profesor) și pivotul părinte-copil înainte de ștergere,
        // ca să nu depindem de comportamentul FK al motorului. Fișele rămân, doar user_id se golește.
        $accounts->each(function (User $user): void {
            Student::query()->where('user_id', $user->id)->update(['user_id' => null]);
            Teacher::query()->where('user_id', $user->id)->update(['user_id' => null]);
            $user->students()->detach();
            $user->syncRoles([]);
            $user->delete();
        });

        $this->warn("Șterse: {$accounts->count()} cont(uri) demo. Fișele de elev/profesor au rămas (user_id golit).");

        return self::SUCCESS;
    }
}
