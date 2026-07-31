import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ActivityTimeline } from './timeline-views';
import type { ActivityTimelineData, TimelineEntry } from './timeline-views';

/**
 * „Cronologie" — promisiunea modulului: notele și absențele stau pe ACELAȘI fir, în ordinea
 * producerii, grupate pe zile; comutarea semestrului filtrează instant (fără alt request).
 */

// Etichetele RO pe care se sprijină aserțiunile — copiate din `lang/ro/site.php`. Paritatea
// cheilor în cele 3 limbi e verificată separat, pe partea de PHP.
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            locale: 'ro',
            messages: {
                ro: {
                    cabinet: {
                        gb_term: 'Semestrul',
                        gb_term_current: 'în curs',
                        gb_grades_one: 'notă',
                        gb_grades_other: 'note',
                        abs_one: 'absență',
                        abs_many: 'absențe',
                        abs_lesson: 'Lecția',
                        motivated: 'Motivată',
                        unmotivated: 'Nemotivată',
                        unmotivated_one: 'nemotivată',
                        unmotivated_other: 'nemotivate',
                        tl_empty: 'Nicio activitate înregistrată în acest semestru.',
                        summative_legend: 'Notă sumativă (ESS/teză) — pondere 50% în media semestrială.',
                    },
                },
            },
            routeSlugs: {},
        },
        url: '/',
    }),
    router: { get: vi.fn(), post: vi.fn() },
}));

function entry(over: Partial<TimelineEntry> & Pick<TimelineEntry, 'key' | 'kind' | 'iso' | 'date'>): TimelineEntry {
    return {
        term: 1,
        weekday: 'joi',
        monthKey: over.iso.slice(0, 7),
        monthLabel: 'Martie 2026',
        subject: 'Matematica',
        teacher: null,
        label: null,
        value: null,
        typeLabel: null,
        isSummative: false,
        motivated: null,
        lesson: null,
        ...over,
    };
}

function data(entries: TimelineEntry[]): ActivityTimelineData {
    return {
        terms: [
            { number: 1, label: 'Semestrul I', current: true },
            { number: 2, label: 'Semestrul II', current: false },
        ],
        currentTerm: 1,
        entries,
    };
}

const fixture = data([
    // Ziua nouă: notă + absență nemotivată (serverul le trimite deja în ordinea asta).
    entry({ key: 'g-2', kind: 'grade', iso: '2026-03-12', date: '12.03.2026', label: '10', value: 10, typeLabel: 'Notă curentă' }),
    entry({ key: 'a-1', kind: 'absence', iso: '2026-03-12', date: '12.03.2026', motivated: false, lesson: { number: 3, room: null } }),
    // Ziua veche: doar o notă.
    entry({ key: 'g-1', kind: 'grade', iso: '2026-03-10', date: '10.03.2026', label: '9', value: 9, typeLabel: 'Notă curentă' }),
    // Semestrul II — nu trebuie să apară cât timp e selectat Semestrul I.
    entry({ key: 'g-9', kind: 'grade', iso: '2026-06-02', date: '02.06.2026', term: 2, label: '8', value: 8, typeLabel: 'Notă curentă', monthLabel: 'Iunie 2026' }),
]);

describe('ActivityTimeline', () => {
    it('randează firul: separatorul de lună, zilele și AMBELE tipuri de intrări', () => {
        render(<ActivityTimeline timeline={fixture} />);

        // Separatorul de lună + antetele celor două zile.
        expect(screen.getByText('Martie 2026')).toBeInTheDocument();
        expect(screen.getByText('12.03.2026')).toBeInTheDocument();
        expect(screen.getByText('10.03.2026')).toBeInTheDocument();

        // Nota: pastila cu valoarea; absența: chip-ul de status + lecția dedusă din orar.
        expect(screen.getByText('10')).toBeInTheDocument();
        expect(screen.getByText('Nemotivată')).toBeInTheDocument();
        expect(screen.getByText(/Lecția\s*3/)).toBeInTheDocument();

        // Semestrul II rămâne ascuns cât e selectat Semestrul I.
        expect(screen.queryByText('02.06.2026')).not.toBeInTheDocument();
    });

    it('bilanțul semestrului numără notele, absențele și nemotivatele', () => {
        const { container } = render(<ActivityTimeline timeline={fixture} />);
        const text = container.textContent ?? '';

        expect(text).toContain('2 note');
        expect(text).toContain('1 absență');
        expect(text).toContain('1 nemotivată');
    });

    it('comutarea semestrului filtrează instant, fără alt request', () => {
        render(<ActivityTimeline timeline={fixture} />);

        fireEvent.click(screen.getByRole('button', { name: /Semestrul II/ }));

        expect(screen.getByText('02.06.2026')).toBeInTheDocument();
        expect(screen.getByText('8')).toBeInTheDocument();
        expect(screen.queryByText('12.03.2026')).not.toBeInTheDocument();
    });

    it('semestrul fără activitate arată starea goală, nu un ecran alb', () => {
        // Doar Semestrul I are intrări → comutarea pe II trebuie să explice golul.
        const onlyFirst = data([entry({ key: 'g-1', kind: 'grade', iso: '2026-03-10', date: '10.03.2026', label: '9', value: 9 })]);
        render(<ActivityTimeline timeline={onlyFirst} />);

        fireEvent.click(screen.getByRole('button', { name: /Semestrul II/ }));

        expect(screen.getByText('Nicio activitate înregistrată în acest semestru.')).toBeInTheDocument();
    });
});
