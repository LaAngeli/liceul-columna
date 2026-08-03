import type { Auth } from '@/types/auth';

declare global {
    interface Window {
        /** Regim SEO minimal — injectat server-side de `app.blade.php` din `config/seo.php`. */
        __seoMinimal?: boolean;
        /** Garda SEO e deja montată (idempotență) — vezi `lib/seo-restriction.ts`. */
        __seoRestrictionActive?: boolean;
    }
}

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            locale: string;
            locales: Record<string, string>;
            messages: Record<string, Record<string, unknown>>;
            /** Traduceri de slug URL (RU/EN) pe segment canonic RO — vezi App\Support\RouteSlugs. */
            routeSlugs: Record<string, Partial<Record<'ru' | 'en', string>>>;
            [key: string]: unknown;
        };
    }
}
