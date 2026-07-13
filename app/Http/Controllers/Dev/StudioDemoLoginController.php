<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Login de DEZVOLTARE pentru contul unic al panoului „Studio de conținut" (/studio, guard `admin`) —
 * ca să se poată testa panoul rapid, fără parolă și fără provocarea 2FA. STRICT local/testing: ruta
 * nici nu se înregistrează în producție (vezi routes/web.php), iar controllerul refuză oricum orice
 * alt mediu. Simetric cu {@see DemoLoginController} (conturile academice, guard `web`).
 *
 * ⚠️ De eliminat la deploy: panoul de producție cere parolă tare (`CMS_ADMIN_PASSWORD`) + MFA
 * obligatoriu. Contul de conținut are deja MFA configurat, deci gate-ul de obligativitate al MFA e
 * satisfăcut de un login direct pe guard — shortcut-ul NU exersează securitatea, doar deblochează
 * testarea locală.
 */
class StudioDemoLoginController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $email = config('cms.admin.email');

        $admin = Admin::query()
            ->when(is_string($email) && $email !== '', fn ($query) => $query->where('email', $email))
            ->orderBy('id')
            ->first();

        abort_if($admin === null, 404, 'Nu există contul de conținut. Rulează: php artisan app:cms-admin');

        Auth::guard('admin')->login($admin);

        // Parola tocmai a fost „dovedită" prin acest login de dev → marcăm confirmarea (ca la login-ul
        // real), altfel endpoint-urile sub password.confirm ar cere parola imediat după intrare.
        session(['auth.password_confirmed_at' => time()]);

        return redirect()->to('/studio');
    }
}
