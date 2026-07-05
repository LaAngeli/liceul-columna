<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Locale;
use App\Support\RouteSlugs;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pe site-ul public, limba e dată de prefixul URL: /ru, /en → ru/en; altfel ro.
 * URL-ul rămâne AUTORITAR pentru SEO și linkuri partajabile pe limbă.
 *
 * ÎN PLUS, ca limba aleasă ORIUNDE (cabinet, panou staff, site) să fie respectată GLOBAL: dacă
 * cererea e pe un URL NON-prefixat (implicit RO) dar utilizatorul are o preferință salvată pentru
 * o limbă non-implicită (user.locale dacă e logat, altfel cookie-ul „locale"), redirectăm (302) la
 * varianta prefixată + cu slug tradus a ACELEIAȘI pagini. URL-urile deja prefixate NU se redirectează
 * invers (rămân autoritare) → fără bucle, iar linkurile partajate pe o limbă se deschid mereu în ea.
 * Așa se repară sincronizarea cabinet/staff → site (ex. logo/„vezi site-ul" din zona autentificată).
 */
class SetPublicLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $segment = $request->segment(1);
        $isPrefixed = in_array($segment, Locale::prefixed(), true);

        if (! $isPrefixed && $request->isMethod('GET')) {
            $preferred = $this->preferredLocale($request);

            if ($preferred !== null && $preferred !== Locale::default()) {
                return redirect($this->prefixedTarget($request, $preferred));
            }
        }

        app()->setLocale($isPrefixed ? (string) $segment : Locale::default());

        return $next($request);
    }

    /**
     * Preferința de limbă salvată: user.locale (dacă e logat) → cookie „locale".
     * null dacă lipsește sau nu e suportată.
     */
    private function preferredLocale(Request $request): ?string
    {
        $user = $request->user('web');
        $userLocale = $user instanceof User ? $user->locale : null;
        $cookie = $request->cookie('locale');

        $candidate = is_string($userLocale) && $userLocale !== ''
            ? $userLocale
            : (is_string($cookie) && $cookie !== '' ? $cookie : null);

        return Locale::isSupported($candidate) ? $candidate : null;
    }

    /**
     * Varianta prefixată + cu slug tradus a paginii curente, păstrând query string-ul
     * (ex. „/scoala-primara?x=1" + ru → „/ru/scoala-primara?x=1"; „/" + ru → „/ru").
     */
    private function prefixedTarget(Request $request, string $locale): string
    {
        $translated = RouteSlugs::translatePath($request->getPathInfo(), $locale);
        $target = '/'.$locale.($translated === '/' ? '' : $translated);
        $query = $request->getQueryString();

        return $query !== null && $query !== '' ? $target.'?'.$query : $target;
    }
}
