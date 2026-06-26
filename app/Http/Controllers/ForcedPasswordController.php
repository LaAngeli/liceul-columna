<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Schimbarea obligatorie a parolei la prima logare (userii migrați din vechiul sistem).
 */
class ForcedPasswordController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('auth/must-change-password', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        // Cast-ul `hashed` aplică bcrypt; setăm must_change_password=false ca să deblocăm accesul.
        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        return redirect()->to($user->homePath());
    }
}
