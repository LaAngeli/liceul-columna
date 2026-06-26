<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pe site-ul public, limba e dată de prefixul URL: /ru, /en → ru/en; altfel ro.
 * URL-ul e autoritar (pentru SEO și linkuri partajabile pe limbă).
 */
class SetPublicLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $segment = $request->segment(1);
        $locale = in_array($segment, Locale::prefixed(), true) ? $segment : Locale::default();

        app()->setLocale($locale);

        return $next($request);
    }
}
