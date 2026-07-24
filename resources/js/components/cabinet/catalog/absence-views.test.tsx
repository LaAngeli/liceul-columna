import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { AbsenceOverview } from './absence-views';
import type { AbsenceEntry, AbsenceOverviewData } from './absence-views';

/**
 * Situația absențelor: comutarea semestrului, filtrele compuse și cele trei citiri ale acelorași
 * date. Etichetele RO sunt injectate mai jos — paritatea lor RO/RU/EN e verificată separat, în PHP.
 */

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            locale: 'ro',
            messages: {
                ro: {
                    cabinet: {
                        gb_term_current: 'în curs',
                        gb_view_subjects: 'Pe discipline',
                        abs_view_timeline: 'Cronologic',
                        abs_view_calendar: 'Calendar',
                        abs_status_all: 'Toate',
                        abs_search: 'Caută disciplină, profesor, dată',
                        abs_no_match: 'Nicio absență nu corespunde filtrelor.',
                        abs_filtered: 'Se afișează :count din :total absențe.',
                        abs_lesson: 'Lecția',
                        abs_room: 'Sala',
                        abs_total: 'Total absențe',
                        abs_one: 'absență',
                        abs_many: 'absențe',
                        abs_evolution: 'Evoluția pe luni',
                        motivated: 'motivate',
                        motivated_one: 'motivată',
                        motivated_other: 'motivate',
                        unmotivated: 'nemotivate',
                        unmotivated_one: 'nemotivată',
                        unmotivated_other: 'nemotivate',
                    },
                },
            },
            routeSlugs: {},
        },
        url: '/',
    }),
    router: { get: vi.fn(), post: vi.fn() },
}));

function entry(over: Partial<AbsenceEntry> & Pick<AbsenceEntry, 'id' | 'term' | 'iso' | 'date'>): AbsenceEntry {
    return {
        subjectId: 1,
        subject: 'Matematică',
        weekday: 'luni',
        monthKey: over.iso.slice(0, 7),
        monthLabel: 'Februarie 2026',
        teacher: 'Popescu Ion',
        lesson: null,
        motivated: false,
        recordedAt: null,
        deadline: null,
        deadlinePassed: false,
        deadlineDays: null,
        locked: false,
        ...over,
    };
}

const data: AbsenceOverviewData = {
    terms: [
        { number: 1, label: 'Semestrul I', current: false },
        { number: 2, label: 'Semestrul II', current: true },
    ],
    currentTerm: 2,
    subjects: [
        { id: 1, name: 'Matematică', teacher: 'Popescu Ion', terms: { 1: { total: 1, motivated: 1, unmotivated: 0 }, 2: { total: 2, motivated: 0, unmotivated: 2 } } },
        { id: 2, name: 'Fizică', teacher: null, terms: { 2: { total: 1, motivated: 1, unmotivated: 0 } } },
    ],
    absences: [
        entry({ id: 30, term: 2, iso: '2026-02-10', date: '10.02.2026', lesson: { number: 3, room: '204' } }),
        entry({ id: 25, term: 2, iso: '2026-02-05', date: '05.02.2026' }),
        entry({ id: 20, term: 2, iso: '2026-02-05', date: '05.02.2026', subjectId: 2, subject: 'Fizică', teacher: null, motivated: true }),
        entry({ id: 10, term: 1, iso: '2025-10-08', date: '08.10.2025', monthLabel: 'Octombrie 2025', motivated: true }),
    ],
    summary: {
        1: { total: 1, motivated: 1, unmotivated: 0, motivatedRate: 100, days: 1, subjectsCount: 1, worstSubject: null, lastDate: '08.10.2025', expiringSoon: 0, locked: 0, previousTotal: null, trend: null },
        2: { total: 3, motivated: 1, unmotivated: 2, motivatedRate: 33, days: 2, subjectsCount: 2, worstSubject: { name: 'Matematică', count: 2 }, lastDate: '10.02.2026', expiringSoon: 0, locked: 0, previousTotal: 1, trend: 'down' },
    },
    months: {
        1: [{ key: '2025-10', label: 'Oct.', total: 1, motivated: 1, unmotivated: 0 }],
        2: [
            { key: '2026-01', label: 'Ian.', total: 0, motivated: 0, unmotivated: 0 },
            { key: '2026-02', label: 'Feb.', total: 3, motivated: 1, unmotivated: 2 },
        ],
    },
};

describe('AbsenceOverview', () => {
    it('pornește pe semestrul curent și nu amestecă absențele celuilalt', async () => {
        render(<AbsenceOverview overview={data} />);

        expect(screen.getByRole('button', { name: /Semestrul II/ })).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getAllByText('10.02').length).toBeGreaterThan(0);
        expect(screen.queryByText('08.10')).not.toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: /Semestrul I$/ }));

        expect(screen.getByText('08.10')).toBeInTheDocument();
        expect(screen.queryByText('10.02')).not.toBeInTheDocument();
        // Disciplina care apare doar în Sem. II nu are ce căuta în Sem. I.
        expect(screen.queryByText('Fizică')).not.toBeInTheDocument();
    });

    it('afișează lecția și sala acolo unde orarul le poate spune', () => {
        render(<AbsenceOverview overview={data} />);

        expect(screen.getByText(/Lecția/)).toBeInTheDocument();
        expect(screen.getByText(/204/)).toBeInTheDocument();
    });

    it('filtrează pe status și anunță câte rânduri au rămas', async () => {
        render(<AbsenceOverview overview={data} />);

        // Înainte de filtrare Fizica apare de două ori: pastila de filtrare + cardul disciplinei.
        expect(screen.getAllByText('Fizică')).toHaveLength(2);

        await userEvent.click(screen.getByRole('button', { name: /^nemotivate$/ }));

        expect(screen.getByRole('status')).toHaveTextContent('Se afișează 2 din 3 absențe.');
        // Cardul dispare (n-are absențe nemotivate), dar pastila RĂMÂNE — altfel n-ai cum
        // să te întorci la disciplina pe care tocmai ai filtrat-o afară.
        expect(screen.getAllByText('Fizică')).toHaveLength(1);
    });

    it('caută după profesor și spune clar când nu găsește nimic', async () => {
        render(<AbsenceOverview overview={data} />);
        const search = screen.getByPlaceholderText('Caută disciplină, profesor, dată');

        await userEvent.type(search, 'Popescu');
        expect(screen.getByRole('status')).toHaveTextContent('Se afișează 2 din 3 absențe.');

        await userEvent.clear(search);
        await userEvent.type(search, 'Xilofon');
        expect(screen.getByText('Nicio absență nu corespunde filtrelor.')).toBeInTheDocument();
    });

    it('grupează pe zi în vederea cronologică — o zi cu două lecții pierdute e un singur bloc', async () => {
        render(<AbsenceOverview overview={data} />);

        await userEvent.click(screen.getByRole('button', { name: /Cronologic/ }));

        // 05.02 are două absențe (Matematică + Fizică) → un antet, două rânduri.
        const headers = screen.getAllByText('05.02.2026');
        expect(headers).toHaveLength(1);
        // Antetul zilei numără absențele ei și evidențiază separat câte au rămas nemotivate.
        expect(screen.getByText((_, el) => el?.tagName === 'P' && /^2 absențe/.test(el.textContent ?? ''))).toHaveTextContent(
            '1 nemotivată',
        );
    });

    it('desenează calendarul doar pentru lunile cu absențe, cu zilele marcate', async () => {
        render(<AbsenceOverview overview={data} />);

        await userEvent.click(screen.getByRole('button', { name: /Calendar/ }));

        // Ianuarie e în serie cu total 0 → primește o bară în grafic, dar NU o grilă de calendar.
        const headings = screen.getAllByRole('heading', { level: 3 }).map((h) => h.textContent);
        expect(headings).toContain('Feb.');
        expect(headings).not.toContain('Ian.');

        const grid = screen.getAllByRole('heading', { level: 3 }).find((h) => h.textContent === 'Feb.')!
            .parentElement as HTMLElement;
        expect(within(grid).getByText('10')).toBeInTheDocument();
    });
});
