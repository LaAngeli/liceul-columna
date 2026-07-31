import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Reîmprospătează periodic UN prop Inertia al paginii curente, fără a reface pagina — tiparul
 * „live îndeajuns" (polling nativ Inertia v3), deja folosit pentru clopoțelul de notificări.
 *
 * - `router.reload` vizitează URL-ul CURENT, deci păstrează parametrii (ex. `?copil=`) → firul
 *   rămâne al copilului selectat, chiar dacă între timp s-a schimbat din comutator.
 * - `only: [prop]` limitează cererea la un singur prop (răspuns mic, controllerul rulează, dar
 *   serializează doar acel prop).
 * - Pauzat cât timp fila NU e vizibilă — nu batem serverul degeaba pentru un tab din fundal.
 * - `router.reload` păstrează OricUM scroll-ul și starea (le forțează intern — de aceea nici nu
 *   apar în opțiunile lui): reîmprospătarea de fundal nu urcă pagina și nu resetează starea locală.
 *
 * `intervalMs <= 0` dezactivează polling-ul (util când nu e nimic de reîmprospătat — ex. familia
 * n-are copil selectat), fără a încălca regulile hook-urilor (se apelează mereu, necondiționat).
 */
export function usePollReload(prop: string, intervalMs = 60_000): void {
    useEffect(() => {
        if (intervalMs <= 0) {
            return;
        }

        const id = window.setInterval(() => {
            if (document.visibilityState !== 'visible') {
                return;
            }

            router.reload({ only: [prop] });
        }, intervalMs);

        return () => window.clearInterval(id);
    }, [prop, intervalMs]);
}
