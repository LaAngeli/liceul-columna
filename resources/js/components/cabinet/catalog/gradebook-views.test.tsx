import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { GradeBook } from './gradebook-views';
import type { GradeBookData, GradeBookEntry } from './gradebook-views';

/**
 * Catalogul familiei: cele trei lucruri pe care restructurarea le promite utilizatorului —
 * data notei se VEDE (nu stă în tooltip), semestrele nu se amestecă, iar comutarea între cele
 * două citiri e instantă (fără alt request).
 */

// Etichetele RO pe care se sprijină aserțiunile — copiate din `lang/ro/site.php`. Existența lor
// în toate cele 3 limbi e verificată separat, pe partea de PHP (paritatea cheilor `cabinet.*`).
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            locale: 'ro',
            messages: {
                ro: {
                    cabinet: {
                        gb_term_current: 'în curs',
                        gb_view_subjects: 'Pe discipline',
                        gb_view_journal: 'Cronologic',
                        gb_open_subject: 'Vezi toate notele',
                        gb_contest: 'Contestă',
                        gb_grades_one: 'notă',
                        gb_grades_other: 'note',
                    },
                },
            },
            routeSlugs: {},
        },
        url: '/',
    }),
    router: { get: vi.fn(), post: vi.fn() },
}));

function entry(over: Partial<GradeBookEntry> & Pick<GradeBookEntry, 'id' | 'term' | 'label' | 'date'>): GradeBookEntry {
    return {
        subjectId: 1,
        subject: 'Matematică',
        value: Number(over.label) || null,
        iso: '2026-02-01',
        weekday: 'luni',
        monthLabel: 'Februarie 2026',
        typeLabel: 'Curentă',
        isSummative: false,
        teacher: null,
        recordedAt: null,
        ...over,
    };
}

const data: GradeBookData = {
    terms: [
        { number: 1, label: 'Semestrul I', current: false },
        { number: 2, label: 'Semestrul II', current: true },
    ],
    currentTerm: 2,
    subjects: [
        {
            id: 1,
            name: 'Matematică',
            teachers: ['Popescu Ion'],
            terms: {
                1: { average: 7.5, mc: null, summative: null, count: 1, series: [7], trend: null, lastDate: '10.10.2025', risk: false },
                2: { average: 9, mc: null, summative: null, count: 2, series: [9, 9], trend: null, lastDate: '05.02.2026', risk: false },
            },
        },
        {
            id: 2,
            name: 'Chimie',
            teachers: [],
            terms: {
                2: { average: 4.5, mc: null, summative: null, count: 1, series: [4], trend: null, lastDate: '03.02.2026', risk: true },
            },
        },
    ],
    grades: [
        entry({ id: 30, term: 2, label: '9', date: '05.02.2026' }),
        entry({ id: 20, term: 2, label: '4', date: '03.02.2026', subjectId: 2, subject: 'Chimie' }),
        entry({ id: 25, term: 2, label: '9', date: '01.02.2026' }),
        entry({ id: 10, term: 1, label: '7', date: '10.10.2025', monthLabel: 'Octombrie 2025' }),
    ],
    summary: {
        1: { average: 7.5, trend: null, previousAverage: null, gradesCount: 1, subjectsCount: 1, riskCount: 0, lastDate: '10.10.2025' },
        2: { average: 6.75, trend: 'down', previousAverage: 7.5, gradesCount: 3, subjectsCount: 2, riskCount: 1, lastDate: '05.02.2026' },
    },
};

describe('GradeBook', () => {
    it('afișează DATA fiecărei note ca text, nu ascunsă într-un tooltip', () => {
        render(<GradeBook data={data} />);

        // Vechea vedere punea data doar în `title` — invizibilă pe touch, unde nu există hover.
        expect(screen.getAllByText('05.02').length).toBeGreaterThan(0);
        expect(screen.getAllByText('03.02').length).toBeGreaterThan(0);
    });

    it('pornește pe semestrul curent și NU amestecă notele celuilalt semestru', async () => {
        render(<GradeBook data={data} />);

        // Semestrul II: nota de 7 din Sem. I nu are ce căuta aici.
        expect(screen.queryByText('10.10')).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Semestrul II/ })).toHaveAttribute('aria-pressed', 'true');

        await userEvent.click(screen.getByRole('button', { name: /Semestrul I$/ }));

        expect(screen.getByText('10.10')).toBeInTheDocument();
        expect(screen.queryByText('05.02')).not.toBeInTheDocument();
        // Disciplina care începe abia în Sem. II nu apare în Sem. I.
        expect(screen.queryByText('Chimie')).not.toBeInTheDocument();
    });

    it('comută între „pe discipline" și „cronologic" fără alt request', async () => {
        render(<GradeBook data={data} />);

        // Vederea pe discipline = carduri deschizibile.
        expect(screen.getAllByRole('button', { name: /Vezi toate notele/ })).toHaveLength(2);

        await userEvent.click(screen.getByRole('button', { name: /cronologic/i }));

        // Jurnalul grupează pe lună și arată ziua fiecărei note.
        expect(screen.getByText('Februarie 2026')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Vezi toate notele/ })).not.toBeInTheDocument();
    });

    it('deschide fișa disciplinei cu toate notele semestrului', async () => {
        render(<GradeBook data={data} />);

        await userEvent.click(screen.getAllByRole('button', { name: /Vezi toate notele/ })[0]);

        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByText('Matematică')).toBeInTheDocument();
        // Cele două note ale disciplinei în Sem. II, cu data completă (an inclus).
        expect(within(dialog).getByText(/05\.02\.2026/)).toBeInTheDocument();
        expect(within(dialog).getByText(/01\.02\.2026/)).toBeInTheDocument();
        // Nota de la Chimie nu se scurge în fișa altei discipline.
        expect(within(dialog).queryByText(/03\.02\.2026/)).not.toBeInTheDocument();
    });

    it('oferă contestația doar când familia are dreptul, și închide fișa la pornirea ei', async () => {
        const onContestGrade = vi.fn();
        const { unmount } = render(<GradeBook data={data} />);

        await userEvent.click(screen.getAllByRole('button', { name: /Vezi toate notele/ })[0]);
        expect(within(await screen.findByRole('dialog')).queryByRole('button', { name: 'Contestă' })).not.toBeInTheDocument();
        unmount();

        render(<GradeBook data={data} onContestGrade={onContestGrade} />);
        await userEvent.click(screen.getAllByRole('button', { name: /Vezi toate notele/ })[0]);

        const dialog = await screen.findByRole('dialog');
        await userEvent.click(within(dialog).getAllByRole('button', { name: 'Contestă' })[0]);

        expect(onContestGrade).toHaveBeenCalledWith(30);
        // Contestația mută utilizatorul pe tabul „Cereri" — fișa nu rămâne deschisă peste el.
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
});
