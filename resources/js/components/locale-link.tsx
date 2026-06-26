import { Link } from '@inertiajs/react';
import { type ComponentProps } from 'react';
import { localizePath, useLocale } from '@/lib/i18n';

/** Rute de sistem/auth — NU se prefixează cu limba (nu există sub /ru, /en). */
const SYSTEM_PREFIXES = [
    '/admin',
    '/dashboard',
    '/cabinet',
    '/login',
    '/register',
    '/logout',
    '/set-locale',
    '/schimbare-parola',
    '/settings',
    '/forgot-password',
    '/reset-password',
    '/two-factor',
    '/user',
    '/verify-email',
    '/confirm-password',
];

function isLocalizable(href: string): boolean {
    if (!href.startsWith('/') || href.startsWith('//')) {
        return false;
    }

    const path = href.split('?')[0].split('#')[0];

    return !SYSTEM_PREFIXES.some((prefix) => path === prefix || path.startsWith(`${prefix}/`));
}

/**
 * `<Link>` conștient de limbă: prefixează căile publice interne cu limba curentă
 * (ro = fără prefix), astfel încât navigarea rămâne în limba aleasă. Lasă neatinse
 * rutele de sistem/auth, URL-urile externe și ancorele.
 */
export function LocaleLink({ href, ...props }: ComponentProps<typeof Link>) {
    const locale = useLocale();
    const target = typeof href === 'string' && isLocalizable(href) ? localizePath(href, locale) : href;

    return <Link href={target} {...props} />;
}
