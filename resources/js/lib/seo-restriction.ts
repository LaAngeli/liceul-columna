/**
 * Regim SEO MINIMAL — gardă centrală, temporară și complet reversibilă.
 *
 * Comutatorul e `config/seo.php` → `SEO_MINIMAL` în `.env`, injectat server-side de
 * `app.blade.php` ca `window.__seoMinimal`. Nu se citește din `import.meta.env`: acolo
 * valoarea s-ar „coace" în bundle la build, iar comutarea ar cere rebuild pe producție.
 *
 * DE CE O GARDĂ CENTRALĂ, nu 20 de editări în pagini:
 * configurația SEO reală (`<meta name="description">` în fiecare pagină + cheile
 * `*.meta_description` din `lang/{ro,ru,en}/site.php`) rămâne INTACTĂ în cod. Regimul doar
 * o împiedică să ajungă în DOM. Dezactivarea flag-ului o reactivează integral, fără să fi
 * fost ștearsă sau rescrisă vreodată — exact cerința de „comment/disable", nu de ștergere.
 *
 * CUM: `<Head>` din Inertia scrie meta-urile în `<head>` DUPĂ hidratare (proiectul NU are
 * SSR — `ssr.tsx` nu există, iar HTML-ul livrat de server nu conține meta description).
 * Un `MutationObserver` pe `<head>` prinde fiecare inserție și o retrage imediat, inclusiv
 * la navigările SPA următoare, când Inertia rescrie head-ul paginii noi.
 *
 * Documentație: `docs/SEO-REGIM-MINIMAL.md`
 */

/** Semnale SEO suprimate în regim minimal. `theme-color`, `viewport`, icon-urile și meta-urile
 *  PWA (`application-name`, `apple-*`, `msapplication-*`) NU sunt vizate: țin de afișare și de
 *  instalarea pe ecranul principal, nu de indexare. */
const SUPPRESSED = [
    'meta[name="description"]',
    'meta[name="keywords"]',
    'meta[name="robots"]',
    'meta[property^="og:"]',
    'meta[name^="og:"]',
    'meta[name^="twitter:"]',
    'meta[property^="twitter:"]',
    'script[type="application/ld+json"]',
    'link[rel="canonical"]',
    'link[rel="alternate"][hreflang]',
].join(',');

export function isSeoMinimal(): boolean {
    return typeof window !== 'undefined' && window.__seoMinimal === true;
}

/** Compune titlul din tab. În regim minimal = EXCLUSIV denumirea paginii, fără brand/slogan. */
export function composeTitle(title: string, appName: string): string {
    if (isSeoMinimal()) {
        return title ?? '';
    }

    return title ? `${title} - ${appName}` : appName;
}

/** Pornește garda. Idempotentă — o a doua chemare nu montează un al doilea observer. */
export function startSeoRestriction(): void {
    if (!isSeoMinimal() || typeof document === 'undefined' || window.__seoRestrictionActive) {
        return;
    }

    window.__seoRestrictionActive = true;

    const strip = (root: ParentNode) => {
        root.querySelectorAll(SUPPRESSED).forEach((el) => el.remove());
    };

    strip(document.head);

    // `subtree: true` — Inertia poate insera prin noduri intermediare, nu doar direct în <head>.
    new MutationObserver((records) => {
        for (const record of records) {
            for (const node of record.addedNodes) {
                if (!(node instanceof Element)) {
                    continue;
                }

                if (node.matches(SUPPRESSED)) {
                    node.remove();
                } else {
                    strip(node);
                }
            }
        }
    }).observe(document.head, { childList: true, subtree: true });
}
