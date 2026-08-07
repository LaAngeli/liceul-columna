<?php

namespace App\Http\Controllers;

use App\Models\ConsentAcknowledgment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Luarea la cunoștință a notei de informare (Legea 133/2011 §7): elevul/părintele o confirmă la prima
 * logare (sau la schimbarea versiunii). Confirmarea se înregistrează ca DOVADĂ — versiune, dată, IP.
 */
class PrivacyConsentController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('auth/privacy-consent', [
            'version' => (string) config('privacy.notice_version'),
            'notice' => trans('privacy'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user('web');
        $version = (string) config('privacy.notice_version');

        // Istoricul (dovada) + marcajul rapid pe cont (verificat de middleware).
        ConsentAcknowledgment::create([
            'user_id' => $user->id,
            'document_version' => $version,
            'acknowledged_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        $user->update([
            'privacy_acknowledged_version' => $version,
            'privacy_acknowledged_at' => now(),
        ]);

        // Acasă la ROLUL LUI, niciodată `intended()`. Ținta memorată în sesiune e a browserului, nu
        // a contului: dacă cineva a atins /admin cât nu era autentificat, Laravel a reținut-o acolo,
        // iar logarea o ignoră deliberat ({@see \App\Http\Responses\LoginResponse}) — dar NU o
        // consumă. Confirmarea notei, ultimul pas al onboardingului, o culegea și trimitea elevul
        // nou în panou → 403 la prima intrare, dispărut la refresh (ținta se consumă o dată).
        // Raportat 07.08.2026: „doar pentru elevii noi creați".
        return redirect()->to($user->homePath());
    }
}
