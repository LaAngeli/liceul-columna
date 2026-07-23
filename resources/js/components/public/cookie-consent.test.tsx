import { render, screen, waitFor } from '@testing-library/react';
import { act } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CookieConsent } from './cookie-consent';

/**
 * Comportamentul bannerului de consimțământ — pinuit ÎNAINTE de a scoate `setState` sincron din
 * efect, ca refactorizarea să nu schimbe ce vede vizitatorul.
 */

// `useTranslations`/`LocaleLink` citesc props-urile paginii Inertia; în test nu există un
// <App> Inertia, deci înlocuim modulul cu un dublu minim.
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'ro', messages: {}, routeSlugs: {} }, url: '/' }),
    Link: ({ children, ...props }: { children?: React.ReactNode }) => <a {...props}>{children}</a>,
}));

const CONSENT_KEY = 'cookie-consent';

/** Bannerul e prezent? (rolul `dialog` e ancora lui stabilă.) */
const banner = () => screen.queryByRole('dialog');

beforeEach(() => {
    localStorage.clear();
});

describe('CookieConsent', () => {
    it('se afișează vizitatorului fără consimțământ salvat', async () => {
        render(<CookieConsent />);

        await waitFor(() => expect(banner()).toBeInTheDocument());
        expect(screen.getByText(/Respectăm confidențialitatea ta/)).toBeInTheDocument();
    });

    it('rămâne ascuns când consimțământul e deja salvat', async () => {
        localStorage.setItem(
            CONSENT_KEY,
            JSON.stringify({ v: 1, necessary: true, preferences: true, analytics: false, marketing: false, ts: Date.now() }),
        );

        render(<CookieConsent />);

        // Așteptăm un tick ca eventualele efecte să apuce să ruleze — abia apoi afirmăm absența.
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(banner()).not.toBeInTheDocument();
    });

    it('se redeschide la evenimentul „cookie-settings:open" (linkul din footer), cu preferințele salvate', async () => {
        localStorage.setItem(
            CONSENT_KEY,
            JSON.stringify({ v: 1, necessary: true, preferences: true, analytics: true, marketing: false, ts: Date.now() }),
        );

        render(<CookieConsent />);
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(banner()).not.toBeInTheDocument();

        act(() => {
            window.dispatchEvent(new CustomEvent('cookie-settings:open'));
        });

        await waitFor(() => expect(banner()).toBeInTheDocument());
        // Redeschiderea intră direct în panoul de preferințe (nu în bannerul scurt).
        expect(screen.getByText(/Statistici/)).toBeInTheDocument();
    });
});
