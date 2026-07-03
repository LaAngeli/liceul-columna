<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * În zonele autentificate (panou staff, cabinet), limba urmează preferința
 * utilizatorului (sau cookie-ul), nu URL-ul — nu există prefix de limbă acolo.
 */
class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $cookie = $request->cookie('locale');

        $locale = (auth()->check() ? $request->user('web')->locale : null)
            ?? (is_string($cookie) ? $cookie : null)
            ?? Locale::default();

        if (! Locale::isSupported($locale)) {
            $locale = Locale::default();
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
