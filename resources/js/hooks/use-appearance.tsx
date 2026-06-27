import { useSyncExternalStore } from 'react';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

const listeners = new Set<() => void>();
let currentAppearance: Appearance = 'system';

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredAppearance = (): Appearance => {
    if (typeof window === 'undefined') {
        return 'system';
    }

    // `theme` = cheia partajată cu panoul Filament (same-origin) → sursă unică de adevăr.
    // `appearance` = cheia veche a starter kit-ului (fallback / compatibilitate).
    return (localStorage.getItem('theme') as Appearance) || (localStorage.getItem('appearance') as Appearance) || 'system';
};

const persistAppearance = (mode: Appearance): void => {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.setItem('theme', mode); // sincron cu panoul Filament
    localStorage.setItem('appearance', mode);
    setCookie('appearance', mode);
};

const isDarkMode = (appearance: Appearance): boolean => {
    return appearance === 'dark' || (appearance === 'system' && prefersDark());
};

const applyTheme = (appearance: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = isDarkMode(appearance);

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const mediaQuery = (): MediaQueryList | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const handleSystemThemeChange = (): void => applyTheme(currentAppearance);

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    currentAppearance = getStoredAppearance();
    // Aliniază ambele chei (theme + appearance) la valoarea stocată (preia alegerea din panou).
    persistAppearance(currentAppearance);
    applyTheme(currentAppearance);

    // Schimbarea temei sistemului
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);

    // Sincronizare live cu alte tab-uri / panoul Filament (eveniment storage, same-origin).
    window.addEventListener('storage', (event: StorageEvent) => {
        if (event.key === 'theme' || event.key === 'appearance') {
            currentAppearance = getStoredAppearance();
            applyTheme(currentAppearance);
            notify();
        }
    });
}

export function useAppearance(): UseAppearanceReturn {
    const appearance: Appearance = useSyncExternalStore(
        subscribe,
        () => currentAppearance,
        () => 'system',
    );

    const resolvedAppearance: ResolvedAppearance = isDarkMode(appearance)
        ? 'dark'
        : 'light';

    const updateAppearance = (mode: Appearance): void => {
        currentAppearance = mode;

        // Persistă în ambele chei (theme = partajat cu Filament) + cookie pentru SSR.
        persistAppearance(mode);

        applyTheme(mode);
        notify();
    };

    return { appearance, resolvedAppearance, updateAppearance } as const;
}
