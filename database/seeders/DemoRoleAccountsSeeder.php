<?php

namespace Database\Seeders;

use App\Console\Commands\DemoAccounts;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Sursă UNICĂ pentru „un cont demo funcțional per rol", gata de testare a dashboard-ului /
 * cabinetului. Câte un cont pentru fiecare dintre cele 9 roluri, fiecare cu:
 *
 *   • securitatea TRECUTĂ (nu blocată): parola nu mai trebuie schimbată, 2FA e formal configurat
 *     (email) — deci gate-ul obligatoriu de 2FA al personalului e satisfăcut natural, fără să
 *     atingem config-ul de securitate real — și nota de informare e confirmată;
 *   • legături la fișe REALE (profesor/diriginte → Teacher cu clase; elev → Student; părinte →
 *     tutore de 2 elevi), ca scoping-ul să aibă ce arăta;
 *   • marcaj [DEMO] în nume → curățare la deploy cu `php artisan app:demo-accounts --remove`.
 *
 * Accesul de test se face prin login-ul de dev (`_demo/login/{rol}`), disponibil DOAR în mediul
 * local. Conturile trec de securitate; „elementele de securitate" (parolă/2FA reale) NU se
 * testează pe ele — sunt conturi de probă (cerință utilizator).
 *
 * Idempotent. Rulează DUPĂ `app:import-legacy` (are nevoie de fișe reale de profesor/elev).
 */
class DemoRoleAccountsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        // Conturi de ADMINISTRAȚIE — fără fișă (văd tot catalogul / nu au nevoie de scoping).
        // Super-adminul demo reutilizează contul existent `admin@liceul-columna.test` (referențiat
        // de alte seedere ca recenzent) — nu-l dublăm. Contul REAL de producție rămâne cel creat
        // cu `app:create-admin` (fără marcaj [DEMO], deci neatins de curățare).
        $this->account('admin@liceul-columna.test', 'admin', UserRole::Admin, 'Super Administrator');
        $this->account('director@columna.test', 'director', UserRole::Director, 'Director');
        $this->account('vicedirector@columna.test', 'vicedirector', UserRole::PrimVicedirector, 'Prim-vicedirector');
        $this->account('operational@columna.test', 'operational', UserRole::AdministratorOperational, 'Administrator Operațional');
        $this->account('tehnic@columna.test', 'tehnic', UserRole::AdministratorTehnic, 'Administrator Tehnic');

        // Conturi cu FIȘĂ — pentru scoping real.
        $this->teacherAccount('profesor@columna.test', 'profesor', UserRole::Profesor, homeroom: false);
        $this->teacherAccount('diriginte@columna.test', 'diriginte', UserRole::Diriginte, homeroom: true);
        $this->studentAccount();
        $this->parentAccount();

        $this->command->info('');
        $this->command->info('Conturi demo per rol pregătite. Login de test (doar în mediul local):');
        $this->command->info('  '.config('app.url').'/_demo/login/{rol}   (ex. .../_demo/login/director)');
    }

    /** Cont simplu de administrație (fără fișă), cu securitatea trecută. */
    private function account(string $email, string $username, UserRole $role, string $label): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => DemoAccounts::MARKER.' '.$label, 'username' => $username, 'password' => 'password'],
        );
        $user->syncRoles([$role->value]);
        $this->passSecurity($user);

        $this->command->info(sprintf('  %-28s → %s', $email, $label));

        return $user;
    }

    /** Cont legat de o fișă de profesor (reutilizează fișa deja legată sau alege una distinctă). */
    private function teacherAccount(string $email, string $username, UserRole $role, bool $homeroom): void
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => DemoAccounts::MARKER.' Profesor', 'username' => $username, 'password' => 'password'],
        );
        $user->syncRoles([$role->value]);

        $teacher = Teacher::query()->where('user_id', $user->id)->first()
            ?? $this->pickTeacher($homeroom, $user->id);

        if ($teacher !== null) {
            $teacher->update(['user_id' => $user->id]);
            $user->update(['name' => DemoAccounts::MARKER.' '.$teacher->full_name]);
            $this->command->info(sprintf('  %-28s → %s%s', $email, $teacher->full_name, $homeroom ? ' (diriginte)' : ''));
        } else {
            $this->command->warn("  {$email}: fără fișă de profesor disponibilă (rulează app:import-legacy).");
        }

        $this->passSecurity($user);
    }

    /** Cont de elev legat de propria fișă (un elev cu note, pentru cabinet bogat). */
    private function studentAccount(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'elev@columna.test'],
            ['name' => DemoAccounts::MARKER.' Elev', 'username' => 'elev', 'password' => 'password'],
        );
        $user->syncRoles([UserRole::Elev->value]);

        $student = Student::query()->where('user_id', $user->id)->first()
            ?? Student::query()->whereHas('grades', fn ($q) => $q->whereNotNull('value'))
                ->whereNull('user_id')->orderByDesc('id')->first();

        if ($student !== null) {
            $student->update(['user_id' => $user->id]);
            $user->update(['name' => DemoAccounts::MARKER.' '.$student->full_name]);
            $this->command->info(sprintf('  %-28s → %s', 'elev@columna.test', $student->full_name));
        }

        $this->passSecurity($user);
    }

    /** Cont de părinte, tutore a doi elevi cu situație școlară. */
    private function parentAccount(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'parinte@columna.test'],
            ['name' => DemoAccounts::MARKER.' Părinte', 'username' => 'parinte', 'password' => 'password'],
        );
        $user->syncRoles([UserRole::Parinte->value]);

        if ($user->students()->count() < 2) {
            $children = Student::query()
                ->whereHas('grades', fn ($q) => $q->whereNotNull('value'))
                ->orderByDesc('id')->take(2)->pluck('id')->all();

            if (count($children) === 2) {
                $user->students()->sync($children);
            }
        }

        $names = $user->students()->get()->map->full_name->implode(', ');
        $this->command->info(sprintf('  %-28s → copii: %s', 'parinte@columna.test', $names !== '' ? $names : '(niciunul)'));

        $this->passSecurity($user);
    }

    /**
     * Alege o fișă de profesor disponibilă (fără cont legat sau deja al acestui user), preferând
     * una cu / fără dirigenție după caz. Distinctă de fișele deja folosite de alte conturi demo.
     */
    private function pickTeacher(bool $homeroom, int $ownUserId): ?Teacher
    {
        return Teacher::query()
            ->when($homeroom, fn ($q) => $q->whereHas('homeroomClasses'))
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $ownUserId))
            ->orderBy('id')
            ->first();
    }

    /**
     * Trece contul de toate gate-urile de securitate — fără să atingem config-ul de securitate
     * real. Parola e considerată schimbată, 2FA e formal configurat (email) astfel încât
     * EnsureTwoFactorEnrolled să fie satisfăcut, iar nota de informare e confirmată la versiunea
     * curentă. Sunt conturi de test: securitatea reală (parolă tare, TOTP) nu se exersează pe ele.
     */
    private function passSecurity(User $user): void
    {
        $user->forceFill([
            'must_change_password' => false,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'two_factor_email_enabled_at' => now(),
            'privacy_acknowledged_version' => (string) config('privacy.notice_version'),
            'privacy_acknowledged_at' => now(),
        ])->save();
    }
}
