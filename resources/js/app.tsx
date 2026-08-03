import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PublicLayout from '@/layouts/public-layout';
import { composeTitle, startSeoRestriction } from '@/lib/seo-restriction';

// Fallback-ul e numele instituției, NU „Laravel": dacă VITE_APP_NAME lipsește la build
// (producție fără .env, CI, clonă nouă), titlul din tab rămâne corect pentru utilizator.
const appName = import.meta.env.VITE_APP_NAME || 'Liceul Columna';

// Regim SEO minimal (temporar, comutabil din `.env`): pornit ÎNAINTE de `createInertiaApp`,
// ca observer-ul să fie activ deja la prima scriere de meta-uri. Fără flag, nu face nimic.
startSeoRestriction();

createInertiaApp({
    // În regim minimal → EXCLUSIV denumirea paginii; altfel, comportamentul original cu brand.
    title: (title) => composeTitle(title, appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('public/'):
                return PublicLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            // Setările cabinetului folosesc același AppLayout (sidebar principal) — fără sub-nav separat,
            // ca să fie consecvent cu arhitectura panoului staff.
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
