<?php

namespace App\Http\Controllers;

use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    /**
     * Comută limba: salvează preferința (cookie + user, dacă e logat) și revine
     * la pagina indicată. Sursă unică de adevăr pentru site, panou și cabinet.
     */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        abort_unless(Locale::isSupported($locale), 404);

        if ($user = $request->user()) {
            $user->update(['locale' => $locale]);
        }

        $redirect = $request->query('redirect');
        $target = is_string($redirect) && str_starts_with($redirect, '/') ? $redirect : '/';

        return redirect($target)->withCookie(Cookie::make('locale', $locale, 60 * 24 * 365));
    }
}
