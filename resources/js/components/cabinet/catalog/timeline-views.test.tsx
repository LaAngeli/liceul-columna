import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ActivityTimeline } from './timeline-views';
import type { ActivityTimelineData, TimelineEntry } from './timeline-views';

/**
 * „Cronologie" — promisiunea modulului: notele și absențele stau pe ACELAȘI fir, în ordinea
 * producerii, grupate pe zile, CONTINUU peste tot anul (fără împărțire pe semestre).
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
                        gb_grades_one: 'notă',
                        gb_grades_other: 'note',
                        abs_one: 'absență',
                        abs_many: 'absențe',
                        abs_lesson: 'Lecția',
                        motivated_one: 'motivată',
                        unmotivated_one: 'nemotivată',
                        unmotivated_other: 'nemotivate',
                        tl_empty: 'Nicio activitate înregistrată în acest an școlar.',
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

const fixture: ActivityTimelineData = {
    entries: [
        // Ziua nouă: notă + absență nemotivată (serverul le trimite deja în ordinea asta).
        entry({ key: 'g-2', kind: 'grade', iso: '2026-03-12', date: '12.03.2026', label: '10', value: 10, typeLabel: 'Notă curentă' }),
        entry({ key: 'a-1', kind: 'absence', iso: '2026-03-12', date: '12.03.2026', motivated: false, lesson: { number: 3, room: null } }),
        // Ziua veche, din altă lună → separator de lună propriu.
        entry({
            key: 'g-1',
            kind: 'grade',
            iso: '2026-02-10',
            date: '10.02.2026',
            monthLabel: 'Februarie 2026',
            label: '9',
            value: 9,
            typeLabel: 'Notă curentă',
        }),
    ],
};

describe('ActivityTimeline', () => {
    it('randează firul: separatoarele de lună, zilele și AMBELE tipuri de intrări', () => {
        render(<ActivityTimeline timeline={fixture} />);

        // Firul e continuu: ambele luni ale anului apar, fără comutator de perioadă.
        expect(screen.getByText('Martie 2026')).toBeInTheDocument();
        expect(screen.getByText('Februarie 2026')).toBeInTheDocument();
        expect(screen.getByText('12.03.2026')).toBeInTheDocument();
        expect(screen.getByText('10.02.2026')).toBeInTheDocument();

        // Nota: pastila cu valoarea; absența: chip-ul de status + lecția dedusă din orar.
        expect(screen.getByText('10')).toBeInTheDocument();
        expect(screen.getByText('9')).toBeInTheDocument();
        expect(screen.getByText(/Lecția\s*3/)).toBeInTheDocument();
    });

    it('eticheta absenței e la SINGULAR — chip-ul califică o absență, nu un grup', () => {
        render(<ActivityTimeline timeline={fixture} />);

        expect(screen.getByText('nemotivată')).toBeInTheDocument();
        expect(screen.queryByText('nemotivate')).not.toBeInTheDocument();
    });

    it('NU afișează comutator de semestru (firul e continuu peste tot anul)', () => {
        render(<ActivityTimeline timeline={fixture} />);

        expect(screen.queryByRole('button', { name: /Semestrul/ })).not.toBeInTheDocument();
        expect(screen.queryAllByRole('button')).toHaveLength(0);
    });

    it('bilanțul anului numără notele, absențele și nemotivatele', () => {
        const { container } = render(<ActivityTimeline timeline={fixture} />);
        const text = container.textContent ?? '';

        expect(text).toContain('2 note');
        expect(text).toContain('1 absență');
        expect(text).toContain('1 nemotivată');
    });

    it('anul fără activitate arată starea goală, nu un ecran alb', () => {
        render(<ActivityTimeline timeline={{ entries: [] }} />);

        expect(screen.getByText('Nicio activitate înregistrată în acest an școlar.')).toBeInTheDocument();
    });
});
