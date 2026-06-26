import { usePage } from '@inertiajs/react';
import { resolveKey, useLocale } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Etichetă tradusă care REZERVĂ lățimea celei mai lungi variante de limbă.
 * Toate variantele se suprapun într-o singură celulă de grid; doar cea activă
 * e vizibilă, dar containerul ia lățimea celei mai late — așa butoanele nu-și
 * mai schimbă dimensiunea când se schimbă limba.
 */
export function T({ k, fallback }: { k: string; fallback?: string }) {
    const locale = useLocale();
    const messages = usePage().props.messages;
    const codes = Object.keys(messages ?? {});

    if (codes.length === 0) {
        codes.push(locale);
    }

    return (
        <span className="grid justify-items-center">
            {codes.map((code) => (
                <span
                    key={code}
                    aria-hidden={code !== locale}
                    className={cn('col-start-1 row-start-1 whitespace-nowrap', code !== locale && 'invisible')}
                >
                    {resolveKey(messages?.[code], k) ?? (code === locale ? (fallback ?? k) : '')}
                </span>
            ))}
        </span>
    );
}
