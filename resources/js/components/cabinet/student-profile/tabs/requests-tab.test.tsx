import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { RequestsTab } from './requests-tab';

/**
 * Pre-completarea cererii de CONTESTAȚIE din chip-ul unei note (`contestIntent`) — pinuită
 * ÎNAINTE de a muta sincronizarea din efect în randare.
 */

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'ro', messages: {}, routeSlugs: {} }, url: '/' }),
    router: { post: vi.fn(), get: vi.fn() },
    // <Form> primește un render-prop cu starea trimiterii; îl randăm ca <form> simplu.
    Form: ({ children }: { children: (state: { processing: boolean; errors: Record<string, string>; recentlySuccessful: boolean }) => ReactNode }) => (
        <form>{children({ processing: false, errors: {}, recentlySuccessful: false })}</form>
    ),
}));

const requestTypes = {
    invoire: 'Cerere de învoire',
    adeverinta: 'Adeverință',
    contestatie: 'Contestație notă',
};

const contestableGrades = [
    { id: 11, label: 'Matematică — 5 (12.03.2026)' },
    { id: 22, label: 'Istorie — 6 (14.03.2026)' },
];

function renderTab(contestIntent: { gradeId: number; token: number } | null) {
    return render(
        <RequestsTab
            studentId={1}
            requestTypes={requestTypes}
            canRequestMotivation
            contestableGrades={contestableGrades}
            contestIntent={contestIntent}
            documentRequests={[]}
            documentRequestsTotal={0}
            corigentaExams={[]}
        />,
    );
}

/** Selectul de tip al cererii (primul combobox din formular). */
const typeSelect = () => screen.getAllByRole('combobox')[0] as HTMLSelectElement;

describe('RequestsTab — pre-completarea contestației', () => {
    it('fără intenție, formularul pornește gol (fără tip ales)', () => {
        renderTab(null);

        expect(typeSelect().value).toBe('');
        // Selectul de notă apare DOAR la tipul „contestatie".
        expect(screen.getAllByRole('combobox')).toHaveLength(1);
    });

    it('cu intenție, alege tipul „contestație" ȘI nota vizată', async () => {
        renderTab({ gradeId: 22, token: 1 });

        await waitFor(() => expect(typeSelect().value).toBe('contestatie'));

        const gradeSelect = screen.getAllByRole('combobox')[1] as HTMLSelectElement;
        expect(gradeSelect.value).toBe('22');
    });

    it('un token NOU (alt click pe chip) re-aplică pre-completarea peste alegerea manuală', async () => {
        const user = userEvent.setup();
        const { rerender } = renderTab({ gradeId: 11, token: 1 });

        await waitFor(() => expect(typeSelect().value).toBe('contestatie'));

        // Utilizatorul schimbă manual tipul…
        await user.selectOptions(typeSelect(), 'invoire');
        expect(typeSelect().value).toBe('invoire');

        // …apoi dă click pe alt chip de notă → token nou.
        rerender(
            <RequestsTab
                studentId={1}
                requestTypes={requestTypes}
                canRequestMotivation
                contestableGrades={contestableGrades}
                contestIntent={{ gradeId: 22, token: 2 }}
                documentRequests={[]}
                documentRequestsTotal={0}
                corigentaExams={[]}
            />,
        );

        await waitFor(() => expect(typeSelect().value).toBe('contestatie'));
        expect((screen.getAllByRole('combobox')[1] as HTMLSelectElement).value).toBe('22');
    });
});
