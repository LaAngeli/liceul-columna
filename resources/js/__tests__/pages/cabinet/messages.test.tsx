import { render, screen, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import MessagesPage from '@/pages/cabinet/messages';

/**
 * Sincronizarea deep-link-ului `?fir=` (venit din notificări) cu firul deschis în poștă —
 * pinuită ÎNAINTE de a muta `setState` din efect în randare. Cazul care contează: Inertia NU
 * remontează pagina la o navigare nouă spre același component, deci firul trebuie să se schimbe
 * fără remount.
 */

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'ro', messages: {}, routeSlugs: {} }, url: '/cabinet/mesaje' }),
    router: { get: vi.fn(), post: vi.fn(), visit: vi.fn() },
    Head: () => null,
    // Compose folosește useForm; dublul întoarce forma minimă pe care o consumă componenta.
    useForm: () => ({
        data: { type: 'direct', student_id: '', recipient_id: '', subject: '', body: '', audience_domain: '' },
        setData: vi.fn(),
        post: vi.fn(),
        reset: vi.fn(),
        clearErrors: vi.fn(),
        errors: {},
        processing: false,
        recentlySuccessful: false,
    }),
    Link: ({ children, ...props }: { children?: ReactNode }) => <a {...props}>{children}</a>,
    Form: ({ children }: { children: (s: { processing: boolean; errors: Record<string, string>; recentlySuccessful: boolean }) => ReactNode }) => (
        <form>{children({ processing: false, errors: {}, recentlySuccessful: false })}</form>
    ),
}));

function thread(id: number, subject: string) {
    return {
        id,
        subject,
        type: 'direct' as const,
        student: 'Elev Test',
        studentId: 1,
        withId: 9,
        with: 'Prof. Popescu',
        direction: 'received' as const,
        scheduledAt: null,
        starred: false,
        archived: false,
        trashed: false,
        snippet: `Rezumat ${id}`,
        lastAt: '01.03.2026 10:00',
        unread: 0,
        attachmentCount: 0,
        messages: [{ id: id * 10, body: `Corpul firului ${id}`, mine: false, senderName: 'Prof. Popescu', at: '01.03.2026 10:00', attachments: [] }],
    };
}

const threads = [thread(1, 'Primul fir'), thread(2, 'Al doilea fir')];

const baseProps = {
    folder: 'inbox' as const,
    q: '',
    threads,
    counts: {
        inbox: { total: 2, unread: 0 },
        starred: { total: 0, unread: 0 },
        sent: { total: 0, unread: 0 },
        archive: { total: 0, unread: 0 },
        trash: { total: 0, unread: 0 },
    },
    compose: {
        students: [{ id: 1, name: 'Elev Test', classLabel: 'V A', recipients: [] }],
        canAudience: false,
        audienceDomains: [],
        attachments: { maxFiles: 5, maxFileMb: 8, extensions: ['pdf'] },
    },
};

describe('MessagesPage — deep-link `?fir=`', () => {
    it('fără deep-link nu deschide niciun fir', () => {
        render(<MessagesPage {...baseProps} openThread={null} />);

        expect(screen.queryByText('Corpul firului 1')).not.toBeInTheDocument();
        expect(screen.queryByText('Corpul firului 2')).not.toBeInTheDocument();
    });

    it('cu deep-link deschide FIRUL vizat din primul render', async () => {
        render(<MessagesPage {...baseProps} openThread={threads[0]} />);

        await waitFor(() => expect(screen.getByText('Corpul firului 1')).toBeInTheDocument());
    });

    it('o navigare NOUĂ spre alt `?fir` schimbă firul deschis fără remontare', async () => {
        const { rerender } = render(<MessagesPage {...baseProps} openThread={threads[0]} />);

        await waitFor(() => expect(screen.getByText('Corpul firului 1')).toBeInTheDocument());

        // Inertia păstrează instanța componentei și doar schimbă prop-urile.
        rerender(<MessagesPage {...baseProps} openThread={threads[1]} />);

        await waitFor(() => expect(screen.getByText('Corpul firului 2')).toBeInTheDocument());
        expect(screen.queryByText('Corpul firului 1')).not.toBeInTheDocument();
    });
});
