import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import HomeworkDetailPage from './tema';

/**
 * Pagina de detaliu a temei: resursele interactive + gărzile de securitate din frontend —
 * un link care nu e http/https NU devine niciodată `href`, iar previzualizarea apare DOAR
 * când serverul a decis-o din conținutul fișierului.
 */

vi.mock('@inertiajs/react', () => ({
     
    Link: ({ children, href, ...props }: any) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
    Head: () => null,
    usePage: () => ({
        props: {
            locale: 'ro',
            messages: {
                ro: {
                    cabinet: {
                        required: 'Obligatoriu:',
                        optional: 'Suplimentar:',
                        hw_back: 'Înapoi la teme',
                        hw_status_today: 'Pentru azi',
                        hw_status_upcoming: 'De făcut',
                        hw_links: 'Linkuri',
                        hw_printed: 'Resurse tipărite',
                        hw_files: 'Fișiere atașate',
                        hw_download: 'Descarcă',
                        hw_preview_show: 'Previzualizează',
                        hw_preview_hide: 'Ascunde previzualizarea',
                    },
                },
            },
            routeSlugs: {},
        },
        url: '/cabinet/teme/7',
    }),
    router: { get: vi.fn(), post: vi.fn() },
}));

const base = {
    id: 7,
    date: '12.03.2026',
    dayLabel: 'Joi, 12 martie',
    status: 'upcoming' as const,
    subject: 'Matematică',
    teacher: 'Damian Iu.',
    topic: 'Recapitulare',
    required: 'Ex. 1–3',
    optional: null,
    links: [],
    resources: [],
    files: [],
};

describe('HomeworkDetailPage', () => {
    it('randează tema complet: identitate, cerință, resurse', () => {
        render(
            <HomeworkDetailPage
                homework={{
                    ...base,
                    links: ['https://manual.example/cap4'],
                    resources: ['Culegerea, p. 12'],
                }}
            />,
        );

        expect(screen.getByText('Recapitulare')).toBeInTheDocument();
        expect(screen.getByText('Ex. 1–3')).toBeInTheDocument();
        expect(screen.getByText('Damian Iu.')).toBeInTheDocument();
        expect(screen.getByText('Culegerea, p. 12')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Înapoi la teme/ })).toHaveAttribute('href', '/cabinet/teme');
    });

    it('linkul http/https se deschide în tab nou, cu noopener; orice altă schemă rămâne INERTĂ', () => {
        render(
            <HomeworkDetailPage
                homework={{
                    ...base,
                     
                    links: ['https://manual.example/cap4', 'javascript:alert(1)', 'Manualul digital, cap. 4'],
                }}
            />,
        );

        const safe = screen.getByRole('link', { name: /manual\.example/ });
        expect(safe).toHaveAttribute('target', '_blank');
        expect(safe).toHaveAttribute('rel', 'noopener noreferrer');

        // Schema periculoasă și textul liber NU devin ancore — chip inert, fără href.
        expect(screen.getByText('javascript:alert(1)').closest('a')).toBeNull();
        expect(screen.getByText('Manualul digital, cap. 4').closest('a')).toBeNull();
    });

    it('previzualizarea apare DOAR când serverul a aprobat-o din conținut', () => {
        render(
            <HomeworkDetailPage
                homework={{
                    ...base,
                    files: [
                        { name: 'fisa.png', url: '/dl/0', preview: 'image' as const, previewUrl: '/vezi/0' },
                        { name: 'inselator.png', url: '/dl/1', preview: null, previewUrl: '/vezi/1' },
                    ],
                }}
            />,
        );

        // Fișierul aprobat: butonul există, iar imaginea se randează abia la cerere.
        const toggles = screen.getAllByRole('button', { name: /Previzualizează/ });
        expect(toggles).toHaveLength(1);

        fireEvent.click(toggles[0]);
        expect(screen.getByRole('img', { name: 'fisa.png' })).toHaveAttribute('src', '/vezi/0');

        // Fișierul respins de server: doar descărcare — interfața nu promite ce transportul refuză.
        expect(screen.getAllByRole('link', { name: /Descarcă/ })).toHaveLength(2);
    });
});
