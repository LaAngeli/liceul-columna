import { render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { StatRibbon } from './brand';

/**
 * Comportamentul numeralelor cu count-up (`CountUp`, privată — testată prin `StatRibbon`, care o
 * folosește): pinuit ÎNAINTE de a scoate `setState` sincron din efect.
 */

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'ro', messages: {}, routeSlugs: {} }, url: '/' }),
    Link: ({ children, ...props }: { children?: React.ReactNode }) => <a {...props}>{children}</a>,
}));

/** Stub pentru preferința de mișcare + IntersectionObserver (jsdom n-are niciunul). */
function stubEnvironment(reducedMotion: boolean) {
    vi.stubGlobal('matchMedia', (query: string) => ({
        matches: reducedMotion && query.includes('prefers-reduced-motion'),
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
    }));
    vi.stubGlobal(
        'IntersectionObserver',
        class {
            observe() {}
            disconnect() {}
            unobserve() {}
        },
    );
}

const items = [
    { value: '27', label: 'ani de experiență' },
    { value: '2019', label: 'anul acreditării' },
    { value: '100%', label: 'promovabilitate' },
];

beforeEach(() => {
    vi.unstubAllGlobals();
});

describe('StatRibbon / CountUp', () => {
    it('cu reduced-motion afișează direct valoarea finală (fără animație de la 0)', async () => {
        stubEnvironment(true);

        render(<StatRibbon items={items} />);

        await waitFor(() => expect(screen.getByText('27')).toBeInTheDocument());
    });

    it('anii NU fac count-up — se afișează ca atare', () => {
        stubEnvironment(true);

        render(<StatRibbon items={items} />);

        expect(screen.getByText('2019')).toBeInTheDocument();
    });

    it('valorile ne-numerice rămân neatinse', () => {
        stubEnvironment(true);

        render(<StatRibbon items={items} />);

        expect(screen.getByText('100%')).toBeInTheDocument();
    });

    it('fără reduced-motion, numeralul pornește de la 0 până intră în viewport', () => {
        stubEnvironment(false);

        render(<StatRibbon items={items} />);

        // IntersectionObserver-ul stub nu declanșează nimic → contorul rămâne la start.
        expect(screen.getByText('0')).toBeInTheDocument();
        expect(screen.queryByText('27')).not.toBeInTheDocument();
    });
});
