import { router } from '@inertiajs/react';
import { act, renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { usePollReload } from './use-poll-reload';

/**
 * Polling-ul „live îndeajuns": la fiecare interval reîmprospătează DOAR prop-ul cerut, cât timp
 * fila e vizibilă; interval 0 = oprit. (Fără userEvent → fake timers nu se blochează.)
 */

vi.mock('@inertiajs/react', () => ({ router: { reload: vi.fn() } }));

function setVisibility(state: 'visible' | 'hidden') {
    Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => state });
}

afterEach(() => {
    vi.useRealTimers();
    vi.mocked(router.reload).mockClear();
});

describe('usePollReload', () => {
    it('reîmprospătează doar prop-ul cerut la fiecare interval, cât timp fila e vizibilă', () => {
        vi.useFakeTimers();
        setVisibility('visible');

        renderHook(() => usePollReload('timeline', 1000));

        // Nimic la montare — abia după ce trece intervalul.
        expect(router.reload).not.toHaveBeenCalled();

        act(() => vi.advanceTimersByTime(1000));
        // `reload` păstrează scroll+state intern — trimitem doar `only`.
        expect(router.reload).toHaveBeenCalledWith({ only: ['timeline'] });

        act(() => vi.advanceTimersByTime(1000));
        expect(router.reload).toHaveBeenCalledTimes(2);
    });

    it('NU reîmprospătează cât timp fila e ascunsă (tab din fundal)', () => {
        vi.useFakeTimers();
        setVisibility('hidden');

        renderHook(() => usePollReload('timeline', 1000));
        act(() => vi.advanceTimersByTime(3000));

        expect(router.reload).not.toHaveBeenCalled();
    });

    it('interval 0 dezactivează polling-ul (nimic de reîmprospătat)', () => {
        vi.useFakeTimers();
        setVisibility('visible');

        renderHook(() => usePollReload('timeline', 0));
        act(() => vi.advanceTimersByTime(5000));

        expect(router.reload).not.toHaveBeenCalled();
    });

    it('la demontare oprește intervalul (fără cereri după plecarea de pe pagină)', () => {
        vi.useFakeTimers();
        setVisibility('visible');

        const { unmount } = renderHook(() => usePollReload('timeline', 1000));
        unmount();
        act(() => vi.advanceTimersByTime(3000));

        expect(router.reload).not.toHaveBeenCalled();
    });
});
