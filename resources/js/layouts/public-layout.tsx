import type {ReactNode} from 'react';
import { CookieConsent } from '@/components/public/cookie-consent';
import { SiteFooter } from '@/components/public/site-footer';
import { SiteHeader } from '@/components/public/site-header';
import { useTranslations } from '@/lib/i18n';

export default function PublicLayout({ children }: { children: ReactNode }) {
    const t = useTranslations();

    return (
        // `.site-shell` = scope-ul sistemului „Columna Civic Editorial" (Proxima Nova + Cervino).
        // Dashboard/Filament rămân pe Inter (în afara acestui scope).
        <div className="site-shell flex min-h-dvh flex-col bg-background text-foreground">
            <a href="#continut" className="skip-link">
                {t('a11y.skip', 'Sari la conținut')}
            </a>
            <SiteHeader />
            <main id="continut" className="flex-1 pb-[env(safe-area-inset-bottom)] md:pb-0">
                {children}
            </main>
            <SiteFooter />
            <CookieConsent />
        </div>
    );
}
