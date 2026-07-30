<?php

namespace App\Http\Controllers\Dev;

use App\Console\Commands\DemoAccounts;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DemoSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Login de DEZVOLTARE pentru conturile demo, ca să se poată testa dashboard-ul / cabinetul
 * fiecărui rol fără a introduce parole. STRICT limitat la mediul local — ruta nici nu se
 * înregistrează în producție (vezi routes/web.php), iar controllerul refuză oricum orice alt mediu.
 *
 * ⚠️ De eliminat la deploy împreună cu conturile demo (`php artisan app:demo-accounts --remove`).
 * Loghează doar conturi marcate [DEMO]; sare peste provocarea 2FA (contul are 2FA formal, deci
 * gate-ul de obligativitate e satisfăcut), fiindcă securitatea nu se exersează pe conturile de test.
 */
class DemoLoginController extends Controller
{
    public function __invoke(string $role): RedirectResponse
    {
        // Local + testing DOAR. Niciun mediu de deploy (production, staging) nu ajunge aici.
        abort_unless(app()->environment(['local', 'testing']), 404);

        $userRole = UserRole::tryFrom($role);
        abort_if($userRole === null, 404, 'Rol necunoscut.');

        // ÎNTÂI după username (== slug-ul rolului): sub multi-rol, căutarea pe rol devine ambiguă —
        // directorul-vitrină poartă ȘI rolurile profesor/diriginte, deci `/_demo/login/diriginte`
        // l-ar nimeri pe el în loc de contul dedicat. Username-ul e neechivoc; rolul rămâne fallback
        // (conturi demo ad-hoc create în teste, fără username-convenție).
        $user = User::query()
            ->where('name', 'like', DemoAccounts::MARKER.'%')
            ->where('username', $userRole->value)
            ->first()
            ?? User::query()
                ->where('name', 'like', DemoAccounts::MARKER.'%')
                ->whereHas('roles', fn ($q) => $q->where('name', $userRole->value))
                ->orderBy('id')
                ->first();

        abort_if($user === null, 404, "Nu există un cont demo pentru rolul „{$role}”. Rulează: php artisan db:seed --class=DemoRoleAccountsSeeder");

        // AUTO-VINDECARE (2026-07-31): garantăm trecerea de securitate CHIAR ACUM, nu ne bazăm pe
        // seed. Altfel provocarea de cod pe email reapărea de câte ori contul își pierdea starea
        // (recreat pe altă cale, resetat) sau configul de obligativitate 2FA era cache-uit pe true.
        DemoSecurity::pass($user);

        Auth::guard('web')->login($user);

        // Parola tocmai a fost „dovedită" prin acest login de dev → marcăm confirmarea (ca la login-ul
        // real), altfel endpoint-urile sub password.confirm ar cere parola imediat după intrare.
        session(['auth.password_confirmed_at' => time()]);

        return redirect()->to($user->homePath());
    }
}
