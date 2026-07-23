import { render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { LeadershipGrid } from './leadership-grid';
import type { LeadershipMember } from './leadership-grid';

/**
 * Comportamentul grilei „Personal" — pinuit ÎNAINTE de a muta amestecul din efect în
 * inițializator, ca refactorizarea să nu schimbe ce vede vizitatorul.
 */

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'ro', messages: {}, routeSlugs: {} }, url: '/' }),
    Link: ({ children, ...props }: { children?: React.ReactNode }) => <a {...props}>{children}</a>,
}));

const member = (n: number): LeadershipMember => ({
    name: `Membru ${n}`,
    role: `Rol ${n}`,
    slug: `membru-${n}`,
    photo: null,
});

/** members[0] = directorul (fixat pe prima poziție); restul e „bazinul" care rotește. */
const members: LeadershipMember[] = [{ name: 'Daniță Ghenadie', role: 'Director', slug: 'director', photo: null }, ...Array.from({ length: 8 }, (_, i) => member(i + 1))];

beforeEach(() => {
    // jsdom nu implementează matchMedia, iar componenta îl folosește pentru prefers-reduced-motion.
    // Îl declarăm cu „reduce" ACTIV → un singur amestec, fără rotație: testăm starea inițială
    // determinist, fără temporizatoare.
    vi.stubGlobal('matchMedia', (query: string) => ({
        matches: true,
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
    }));
});

/** Numele afișate, în ordinea plăcilor. */
const shownNames = () => screen.getAllByRole('article').map((el) => el.textContent ?? '');

describe('LeadershipGrid', () => {
    it('afișează directorul fixat pe prima poziție + 3 sloturi rotative', async () => {
        render(<LeadershipGrid members={members} />);

        await waitFor(() => expect(screen.getAllByRole('article')).toHaveLength(4));
        expect(shownNames()[0]).toContain('Daniță Ghenadie');
    });

    it('cele 3 sloturi arată membri DISTINCȚI, toți din bazin (niciodată directorul)', async () => {
        render(<LeadershipGrid members={members} />);

        await waitFor(() => expect(screen.getAllByRole('article')).toHaveLength(4));

        const rotating = shownNames().slice(1);
        const names = rotating.map((text) => /Membru \d+/.exec(text)?.[0] ?? '');

        expect(names.filter(Boolean)).toHaveLength(3);
        expect(new Set(names).size).toBe(3); // fără dubluri vizibile
        expect(rotating.join(' ')).not.toContain('Daniță Ghenadie');
    });

    it('fără membri nu randează nimic (fără director → null)', () => {
        const { container } = render(<LeadershipGrid members={[]} />);

        expect(container).toBeEmptyDOMElement();
    });
});
