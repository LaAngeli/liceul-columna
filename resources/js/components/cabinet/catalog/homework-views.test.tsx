import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { HomeworkBySubject } from './homework-views';
import type { HomeworkItem } from './homework-views';

/**
 * Temele, ORGANIZATE PE DISCIPLINE (ca la Note): întâi lista disciplinelor, apoi toate temele
 * disciplinei alese, cea mai recentă prima. Testul pinuiește exact promisiunile restructurării —
 * o singură cale de navigare, fără filtre de dată, și ordinea descrescătoare înăuntru.
 *
 * Etichetele RO sunt injectate mai jos; paritatea RO/RU/EN e verificată separat, în PHP.
 */

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            locale: 'ro',
            messages: {
                ro: {
                    cabinet: {
                        homework_assigned_on: '· atribuită',
                        required: 'Obligatoriu:',
                        optional: 'Suplimentar:',
                        hw_open_subject: 'Vezi toate temele',
                        hw_last: 'Ultima temă:',
                        hw_pending: 'de făcut',
                        hw_items_one: 'temă',
                        hw_items_other: 'teme',
                    },
                },
            },
            routeSlugs: {},
        },
        url: '/',
    }),
    router: { get: vi.fn(), post: vi.fn() },
}));

function hw(over: Partial<HomeworkItem> & Pick<HomeworkItem, 'id' | 'effectiveDate' | 'subject'>): HomeworkItem {
    const [y, m, d] = over.effectiveDate.split('-');

    return {
        date: `${d}.${m}.${y}`,
        dayLabel: `${d}.${m}`,
        status: 'past',
        teacher: null,
        topic: `Tema ${over.id}`,
        required: null,
        optional: null,
        links: [],
        resources: [],
        ...over,
    };
}

// Serverul trimite lista deja CRONOLOGIC DESCRESCĂTOR — fixtura respectă acel contract.
const homework: HomeworkItem[] = [
    hw({ id: 5, effectiveDate: '2026-05-20', subject: 'Matematică', teacher: 'Popescu Ion', status: 'upcoming' }),
    hw({ id: 4, effectiveDate: '2026-05-18', subject: 'Chimie' }),
    hw({ id: 3, effectiveDate: '2026-05-12', subject: 'Matematică', teacher: 'Popescu Ion' }),
    hw({ id: 2, effectiveDate: '2026-05-05', subject: 'Matematică', teacher: 'Popescu Ion' }),
    hw({ id: 1, effectiveDate: '2026-04-28', subject: 'Chimie' }),
];

const openSubject = (name: string) => fireEvent.click(screen.getByRole('button', { name: new RegExp(`Vezi toate temele: ${name}`) }));

describe('HomeworkBySubject', () => {
    it('arată întâi DISCIPLINELE, cu numărul de teme și ultima dată', () => {
        render(<HomeworkBySubject homework={homework} />);

        const matematica = screen.getByRole('button', { name: /Vezi toate temele: Matematică/ });
        const text = matematica.textContent ?? '';

        expect(text).toContain('3');
        expect(text).toContain('teme');
        expect(text).toContain('Popescu Ion');
        // Ultima temă = cea mai recentă a disciplinei, nu prima din listă.
        expect(text).toContain('20.05.2026');

        // Temele nu se văd până nu alegi disciplina — asta e simplificarea.
        expect(screen.queryByText('Tema 3')).not.toBeInTheDocument();
    });

    it('NU mai oferă filtre de dată (azi / săptămâna aceasta / calendar)', () => {
        render(<HomeworkBySubject homework={homework} />);

        for (const label of [/Azi/, /Săptămâna/, /Calendar/, /Toate zilele/]) {
            expect(screen.queryByRole('button', { name: label })).not.toBeInTheDocument();
        }
    });

    it('deschide disciplina cu TOATE temele ei, cea mai recentă prima', () => {
        render(<HomeworkBySubject homework={homework} />);

        openSubject('Matematică');

        const dialog = screen.getByRole('dialog');
        const text = dialog.textContent ?? '';

        // Doar temele disciplinei alese.
        expect(text).toContain('Tema 5');
        expect(text).toContain('Tema 3');
        expect(text).toContain('Tema 2');
        expect(within(dialog).queryByText('Tema 4')).not.toBeInTheDocument();

        // Ordine cronologică DESCRESCĂTOARE: 20.05 → 12.05 → 05.05.
        expect(text.indexOf('20.05.2026')).toBeLessThan(text.indexOf('12.05.2026'));
        expect(text.indexOf('12.05.2026')).toBeLessThan(text.indexOf('05.05.2026'));
    });

    it('semnalează pe card temele rămase de făcut (azi sau în viitor)', () => {
        render(<HomeworkBySubject homework={homework} />);

        // Matematica are una „upcoming"; Chimia, niciuna.
        expect(screen.getByRole('button', { name: /Vezi toate temele: Matematică/ }).textContent).toContain('de făcut');
        expect(screen.getByRole('button', { name: /Vezi toate temele: Chimie/ }).textContent).not.toContain('de făcut');
    });
});
