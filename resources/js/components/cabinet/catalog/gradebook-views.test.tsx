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
                        gb_legend_chart_term: 'Linia',
                        gb_legend_chart: 'arată evoluția notelor în timp: cea mai veche în stânga, cea mai nouă în dreapta.',
                        gb_legend_trend_term: 'Săgeata',
                        gb_legend_trend: 'compară media ultimelor note cu a primelor din semestru.',
                        gb_legend_average_term: 'Cifra mare',
                        gb_legend_average: 'este media semestrială oficială.',
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
                1: { average: 7.5, mc: null, summative: null, count: 1, averageSeries: [7], trend: null, lastDate: '10.10.2025', risk: false },
                2: { average: 9, mc: null, summative: null, count: 2, averageSeries: [9, 9], trend: null, lastDate: '05.02.2026', risk: false },
            },
        },
        {
            id: 2,
            name: 'Chimie',
            teachers: [],
            terms: {
                2: { average: 4.5, mc: null, summative: null, count: 1, averageSeries: [4], trend: null, lastDate: '03.02.2026', risk: true },
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

    it('notele se citesc CRONOLOGIC pe card, în aceeași direcție ca linia graficului', () => {
        render(<GradeBook data={data} />);

        // Cardul Matematică are notele din 01.02 și 05.02. Linia graficului curge vechi→nou
        // (stânga→dreapta), deci și pastilele trebuie să înceapă cu cea mai veche — altfel graficul
        // și lista se citesc în oglindă (reclamația beneficiarului).
        const card = screen.getByRole('button', { name: /Vezi toate notele: Matematică/ });
        const text = card.textContent ?? '';

        expect(text.indexOf('01.02')).toBeGreaterThan(-1);
        expect(text.indexOf('01.02')).toBeLessThan(text.indexOf('05.02'));
    });

    it('fișa disciplinei păstrează aceeași ordine cronologică precum cardul', async () => {
        render(<GradeBook data={data} />);

        await userEvent.click(screen.getAllByRole('button', { name: /Vezi toate notele/ })[0]);

        const text = (await screen.findByRole('dialog')).textContent ?? '';
        expect(text.indexOf('01.02.2026')).toBeLessThan(text.indexOf('05.02.2026'));
    });

    it('desenează media pe scara ABSOLUTĂ 1–10, cu pragul de promovare vizibil', () => {
        const withChart: GradeBookData = {
            ...data,
            subjects: [{ ...data.subjects[0], terms: { 2: { ...data.subjects[0].terms[2], count: 4, averageSeries: [6, 7], trend: 'up' } } }],
        };
        const { container } = render(<GradeBook data={withChart} />);

        const chart = container.querySelector('svg[role="img"]');
        expect(chart).not.toBeNull();

        // Scara e FIXĂ (viewBox 0 0 100 34), nu normalizată pe minimul/maximul seriei: altfel
        // 6→7 și 1→10 ar desena exact aceeași pantă, iar cardurile n-ar fi comparabile între ele.
        expect(chart?.getAttribute('viewBox')).toBe('0 0 100 34');

        // Pragul de promovare (5) e desenat — reperul fără de care înălțimea n-ar spune nimic.
        const threshold = chart?.querySelector('line[stroke-dasharray]');
        expect(threshold).not.toBeNull();
        // y pentru 5 pe scara 1–10 înălțime 34: 34 − ((5−1)/9)·34 ≈ 18.9
        expect(Number(threshold?.getAttribute('y1'))).toBeCloseTo(18.9, 1);
    });

    it('explică indicatorii grafici DOAR când există un grafic de explicat', () => {
        // O singură medie în serie → nu se poate desena o evoluție, deci n-are ce lămuri.
        const flat: GradeBookData = {
            ...data,
            subjects: [{ ...data.subjects[0], terms: { 2: { ...data.subjects[0].terms[2], averageSeries: [9] } } }],
        };
        const { unmount } = render(<GradeBook data={flat} />);
        expect(screen.queryByText(/Linia/)).not.toBeInTheDocument();
        unmount();

        const withChart: GradeBookData = {
            ...data,
            subjects: [
                {
                    ...data.subjects[0],
                    terms: { 2: { ...data.subjects[0].terms[2], count: 4, averageSeries: [7, 7.5, 8, 8.5], trend: 'up' } },
                },
            ],
        };
        render(<GradeBook data={withChart} />);

        // Săgeata și linia rămân indescifrabile fără text — legenda stă vizibilă, nu în tooltip
        // (pe telefon nu există hover).
        expect(screen.getByText(/Linia/)).toBeInTheDocument();
        expect(screen.getByText(/Săgeata/)).toBeInTheDocument();
        expect(screen.getByText(/Cifra mare/)).toBeInTheDocument();
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

        // Lista fișei e CRONOLOGICĂ → primul buton aparține celei mai VECHI note (01.02), nu celei
        // mai noi. Dacă ordinea se inversează din nou, aserțiunea asta cade prima.
        expect(onContestGrade).toHaveBeenCalledWith(25);
        // Contestația mută utilizatorul pe tabul „Cereri" — fișa nu rămâne deschisă peste el.
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
});
