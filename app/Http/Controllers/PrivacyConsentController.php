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

        return redirect()->intended(route('dashboard'));
    }
}
