import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { HomeworkByDay, localIso } from './homework-views';
import type { HomeworkItem } from './homework-views';

/**
 * Filtrul de calendar din modulul „Teme": navigarea la o zi anume sau la un interval, compunerea
 * cu filtrul de disciplină și absența lui pe fișa elevului (showCalendar implicit off).
 *
 * Fără fake timers (userEvent se blochează sub ele) — folosim `fireEvent` sincron și construim
 * datele RELATIV la ziua curentă, toate în luna curentă, ca ancorele să fie deterministe indiferent
 * când rulează suita. Etichetele RO sunt injectate mai jos; paritatea RO/RU/EN e verificată în PHP.
 */

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            locale: 'ro',
            messages: {
                ro: {
                    cabinet: {
                        subject: 'Disciplina',
                        homework_today: 'Astăzi',
                        homework_tomorrow: 'Mâine',
                        homework_none_upcoming: 'Nimic de predat momentan.',
                        homework_past: 'Teme anterioare',
                        homework_due_badge: 'de predat',
                        homework_due_on: 'termen',
                        homework_assigned_on: 'atribuită',
                        required: 'Obligatoriu:',
                        optional: 'Suplimentar:',
                        hw_dates_all: 'Toate zilele',
                        hw_date_today: 'Azi',
                        hw_dates_week: 'Săptămâna aceasta',
                        hw_dates_calendar: 'Calendar',
                        hw_dates_hint: 'Alege o zi sau două.',
                        hw_clear_dates: 'Șterge',
                        hw_none_day: 'Nicio temă în această zi.',
                        hw_none_range: 'Nicio temă în perioada aleasă.',
                        hw_has_homework: 'are teme',
                        hw_prev_month: 'Luna anterioară',
                        hw_next_month: 'Luna următoare',
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

const now = new Date();
const todayIso = localIso(now);
/** Ziua `d` a lunii CURENTE ca ISO (d ≤ 28 → există în orice lună). */
const dayIso = (d: number): string => localIso(new Date(now.getFullYear(), now.getMonth(), d));
/** ISO → d.m.Y (pentru a găsi celula/antetul după eticheta afișată). */
const dmy = (iso: string): string => {
    const [y, m, d] = iso.split('-');

    return `${d}.${m}.${y}`;
};
/** Butonul-celulă al unei zile (aria-label începe cu d.m.Y). */
const dayCell = (iso: string): HTMLElement =>
    screen.getByRole('button', { name: new RegExp(`^${dmy(iso).replace(/\./g, '\\.')}`) });

function hw(over: Partial<HomeworkItem> & Pick<HomeworkItem, 'id' | 'effectiveDate' | 'subject'>): HomeworkItem {
    const [y, m, d] = over.effectiveDate.split('-');

    return {
        date: `${d}.${m}.${y}`,
        due: null,
        dayLabel: `${d}.${m}`,
        status: 'past',
        teacher: null,
        topic: `Tema ${over.id}`,
        required: null,
        optional: null,
        links: [],
        ...over,
    };
}

describe('HomeworkByDay — filtrul de calendar', () => {
    it('nu afișează filtrul de dată fără showCalendar (fișa elevului)', () => {
        render(<HomeworkByDay homework={[hw({ id: 1, effectiveDate: dayIso(3), subject: 'Chimie' })]} />);

        expect(screen.queryByRole('button', { name: 'Calendar' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Toate zilele' })).not.toBeInTheDocument();
    });

    it('scurtătura „Azi" arată doar temele zilei curente', () => {
        render(
            <HomeworkByDay
                showCalendar
                homework={[
                    hw({ id: 1, effectiveDate: todayIso, subject: 'Matematică', status: 'today' }),
                    hw({ id: 2, effectiveDate: dayIso(now.getDate() === 5 ? 6 : 5), subject: 'Chimie' }),
                ]}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Azi' }));

        expect(screen.getByText(dmy(todayIso))).toBeInTheDocument();
        expect(screen.getByText('Tema 1')).toBeInTheDocument();
        expect(screen.queryByText('Tema 2')).not.toBeInTheDocument();
    });

    it('selectează o zi din calendar, apoi un interval la al doilea click', () => {
        render(
            <HomeworkByDay
                showCalendar
                homework={[
                    hw({ id: 1, effectiveDate: dayIso(3), subject: 'Chimie' }),
                    hw({ id: 2, effectiveDate: dayIso(3), subject: 'Fizică' }),
                    hw({ id: 3, effectiveDate: dayIso(10), subject: 'Matematică' }),
                    hw({ id: 4, effectiveDate: dayIso(25), subject: 'Istorie' }),
                ]}
            />,
        );
        fireEvent.click(screen.getByRole('button', { name: 'Calendar' }));

        // O zi: cele două teme de pe 3 (Chimie + Fizică); nu cele din 10/25.
        fireEvent.click(dayCell(dayIso(3)));
        expect(screen.getByText(dmy(dayIso(3)))).toBeInTheDocument();
        expect(screen.getByText('Tema 1')).toBeInTheDocument();
        expect(screen.getByText('Tema 2')).toBeInTheDocument();
        expect(screen.queryByText('Tema 3')).not.toBeInTheDocument();

        // Al doilea click (pe 10) închide intervalul 3–10 → intră și tema din 10, nu cea din 25.
        fireEvent.click(dayCell(dayIso(10)));
        expect(screen.getByText(`${dmy(dayIso(3))} – ${dmy(dayIso(10))}`)).toBeInTheDocument();
        expect(screen.getByText('Tema 3')).toBeInTheDocument();
        expect(screen.queryByText('Tema 4')).not.toBeInTheDocument();
    });

    it('compune cu filtrul de disciplină: o zi fără temă la disciplina activă → gol', () => {
        render(
            <HomeworkByDay
                showCalendar
                homework={[
                    hw({ id: 1, effectiveDate: dayIso(3), subject: 'Fizică' }),
                    hw({ id: 2, effectiveDate: dayIso(10), subject: 'Chimie' }),
                ]}
            />,
        );

        // Fizica are temă doar pe 3; alegem ziua de 10 (doar Chimie) → gol pentru Fizică.
        fireEvent.click(screen.getByRole('button', { name: /^Fizică/ }));
        fireEvent.click(screen.getByRole('button', { name: 'Calendar' }));
        fireEvent.click(dayCell(dayIso(10)));

        expect(screen.getByText('Nicio temă în această zi.')).toBeInTheDocument();
    });

    it('„Șterge" readuce vederea implicită', () => {
        render(
            <HomeworkByDay
                showCalendar
                homework={[
                    hw({ id: 1, effectiveDate: todayIso, subject: 'Matematică', status: 'today' }),
                    hw({ id: 2, effectiveDate: dayIso(3), subject: 'Chimie', status: 'past' }),
                ]}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Azi' }));
        const clear = screen.getByRole('button', { name: /Șterge/ });
        expect(clear).toBeInTheDocument();

        fireEvent.click(clear);

        expect(screen.getByRole('button', { name: 'Toate zilele' })).toHaveAttribute('aria-pressed', 'true');
        // Istoricul pliat reapare (item cu status „past").
        expect(screen.getByText(/Teme anterioare/)).toBeInTheDocument();
    });
});
