<?php

namespace App\Console\Commands;

use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class DemoAccounts extends Command
{
    protected $signature = 'app:demo-accounts
        {--remove : Șterge definitiv toate conturile demo}
        {--reset-2fa : Dezactivează 2FA pe conturile demo (le deblochează pentru testare)}';

    protected $description = 'Evidența conturilor demo/test (marcate [DEMO]): le listează, le deblochează 2FA sau, cu --remove, le șterge curat.';

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

        if ($this->option('reset-2fa')) {
            return $this->resetTwoFactor($accounts);
        }

        if (! $this->option('remove')) {
            $this->info($accounts->count().' cont(uri) demo. Rulează `php artisan app:demo-accounts --remove` pentru a le șterge.');
            $this->line('Blocat la logare de 2FA? `php artisan app:demo-accounts --reset-2fa`.');

            return self::SUCCESS;
        }

        // Dezlegăm fișele reale (elev/profesor) și pivotul părinte-copil înainte de ștergere,
        // ca să nu depindem de comportamentul FK al motorului. Fișele rămân, doar user_id se golește.
        $freedStudents = collect();
        $freedTeachers = collect();
        $accounts->each(function (User $user) use ($freedStudents, $freedTeachers): void {
            $freedStudents->push(...Student::query()->where('user_id', $user->id)->pluck('id')->all());
            $freedTeachers->push(...Teacher::query()->where('user_id', $user->id)->pluck('id')->all());
            Student::query()->where('user_id', $user->id)->update(['user_id' => null]);
            Teacher::query()->where('user_id', $user->id)->update(['user_id' => null]);
            $user->students()->detach();
            $user->syncRoles([]);
            $user->delete();
        });

        $this->warn("Șterse: {$accounts->count()} cont(uri) demo. Fișele de elev/profesor au rămas (user_id golit).");

        // Conturile demo se leagă de fișe REALE (ca demo-ul să aibă date) — persoana respectivă
        // rămâne între timp cu contul ei orfan. La curățare, fișa eliberată se RE-LEAGĂ de contul
        // real al persoanei (același nume, fără marcaj demo, încă fără fișă) — altfel go-live-ul ar
        // lăsa oameni reali fără acces la propriile date (audit fidelitate legacy, 2026-07-19).
        $this->relinkFreedProfiles($freedStudents->all(), $freedTeachers->all());

        return self::SUCCESS;
    }

    /**
     * Re-leagă fișele eliberate de conturile demo la conturile reale ale persoanelor (după numele
     * complet, doar când candidatul e UNIC și fără altă fișă — ambiguitățile se listează pentru
     * operare manuală din panou, nu se ghicesc).
     *
     * @param  array<int>  $studentIds
     * @param  array<int>  $teacherIds
     */
    private function relinkFreedProfiles(array $studentIds, array $teacherIds): void
    {
        $relinked = 0;
        $manual = [];

        $profiles = Student::query()->whereKey($studentIds)->get()
            ->map(fn (Student $s): array => ['profil' => $s, 'eticheta' => "elev {$s->last_name} {$s->first_name}", 'tabel' => 'students'])
            ->concat(Teacher::query()->whereKey($teacherIds)->get()
                ->map(fn (Teacher $t): array => ['profil' => $t, 'eticheta' => "profesor {$t->last_name} {$t->first_name}", 'tabel' => 'teachers']));

        foreach ($profiles as $entry) {
            $profile = $entry['profil'];
            $outcome = $this->relinkOne(
                $profile->last_name.' '.$profile->first_name,
                fn (int $userId) => $profile->update(['user_id' => $userId]),
                $entry['tabel'],
            );

            if ($outcome === true) {
                $relinked++;
            } elseif (is_string($outcome)) {
                $manual[] = $entry['eticheta'].': '.$outcome;
            }
        }

        if ($relinked > 0) {
            $this->info("Fișe re-legate la conturile reale ale persoanelor: {$relinked}.");
        }
        foreach ($manual as $line) {
            $this->warn('De legat MANUAL din panou (Utilizatori) — '.$line);
        }
    }

    /**
     * @param  callable(int): mixed  $assign
     * @param  string  $profileTable  Tabelul fișei (students/teachers) — candidatul nu trebuie să aibă deja una.
     * @return bool|string true = legat; false = persoana nu are cont (nimic de făcut); string = motiv de operare manuală
     */
    private function relinkOne(string $fullName, callable $assign, string $profileTable): bool|string
    {
        $candidates = User::query()
            ->where('name', trim($fullName))
            ->where('name', 'not like', self::MARKER.'%')
            ->whereNotExists(fn ($q) => $q->select('id')->from($profileTable)->whereColumn('user_id', 'users.id'))
            ->get();

        if ($candidates->isEmpty()) {
            return false;
        }
        if ($candidates->count() > 1) {
            return 'mai multe conturi cu acest nume ('.$candidates->pluck('username')->filter()->implode(', ').')';
        }

        $assign((int) $candidates->first()->id);

        return true;
    }

    /**
     * Dezactivează 2FA pe conturile demo, ca să nu blocheze testarea.
     *
     * ⚠️ Comutatoarele `SECURITY_2FA_STAFF/CABINET` din `.env` opresc DOAR obligativitatea de a-ți
     * CONFIGURA 2FA (gate-ul {@see EnsureTwoFactorEnrolled}). Provocarea de
     * la login (`/two-factor-challenge`) vine din STAREA CONTULUI: dacă are `two_factor_secret` sau
     * `two_factor_email_enabled_at`, Fortify o cere indiferent de config. Sunt două lucruri diferite,
     * iar confuzia dintre ele costă o sesiune de „de ce tot îmi cere cod?".
     *
     * Atinge EXCLUSIV conturile marcate {@see MARKER} — conturile reale își păstrează 2FA.
     *
     * @param  Collection<int, User>  $accounts
     */
    private function resetTwoFactor($accounts): int
    {
        $blocked = $accounts->filter(
            fn (User $user): bool => $user->two_factor_secret !== null
                || $user->two_factor_email_enabled_at !== null
        );

        if ($blocked->isEmpty()) {
            $this->info('Niciun cont demo nu are 2FA activ — toate se loghează direct.');

            return self::SUCCESS;
        }

        foreach ($blocked as $user) {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_email_enabled_at' => null,
            ])->save();
        }

        $this->info('2FA dezactivat pe '.$blocked->count().' cont(uri) demo:');
        foreach ($blocked as $user) {
            $this->line('  · '.($user->email ?: $user->username));
        }

        return self::SUCCESS;
    }
}
