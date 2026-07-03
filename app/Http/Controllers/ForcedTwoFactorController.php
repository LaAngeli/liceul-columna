<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * Pagina de configurare OBLIGATORIE a 2FA (gate-ul EnsureTwoFactorEnrolled ține segmentul vizat
 * aici până alege o metodă). Aceeași pagină servește și staff-ul (înainte de /admin), și
 * cabinetul — tiparul „schimbării obligatorii de parolă".
 */
class ForcedTwoFactorController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user('web');
        assert($user instanceof User);

        return Inertia::render('auth/two-factor-setup', [
            'twoFactor' => [
                'enabled' => $user->hasEnabledTwoFactorAuthentication(),
                'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
                'email' => [
                    'enabled' => $user->usesEmailTwoFactor(),
                    'address' => $user->email,
                ],
            ],
            'configured' => $user->hasTwoFactorConfigured(),
            'continueTo' => $user->homePath(),
            'status' => $request->session()->get('status'),
        ]);
    }
}
