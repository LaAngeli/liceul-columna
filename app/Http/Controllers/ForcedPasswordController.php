<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Schimbarea obligatorie a parolei la prima logare (userii migrați din vechiul sistem).
 *
 * SECURITATE (#37): endpoint-ul e EXCLUSIV fluxul forțat — accesibil DOAR userilor cu
 * `must_change_password=true`, la care parola veche tocmai a fost dovedită la login. Un user deja
 * onboardat (flag=false) e respins: altfel oricine cu o sesiune deschisă (dispozitiv nesupravegheat,
 * cookie capturat) ar putea seta o parolă nouă FĂRĂ să o cunoască pe cea curentă → preluare de cont.
 * Schimbarea voluntară a parolei nu e expusă în cabinet (doctrina view-only); se face prin „am uitat
 * parola" (dovada = emailul) sau de către personal.
 */
class ForcedPasswordController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->guardForcedFlow($request)) {
            return $redirect;
        }

        return Inertia::render('auth/must-change-password', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if ($redirect = $this->guardForcedFlow($request)) {
            return $redirect;
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user('web');
        // Cast-ul `hashed` aplică bcrypt; setăm must_change_password=false ca să deblocăm accesul.
        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        return redirect()->to($user->homePath());
    }

    /**
     * Respinge accesul dacă userul NU e în fluxul forțat (flag stins) — îl trimite acasă.
     */
    private function guardForcedFlow(Request $request): ?RedirectResponse
    {
        $user = $request->user('web');

        if ($user === null || ! $user->must_change_password) {
            return redirect()->to($user?->homePath() ?? '/');
        }

        return null;
    }
}
