/**
 * Utilitare partajate între taburile profilului elev — extrase din vechiul `student-profile.tsx` monolitic.
 */

export type Trend = 'up' | 'stable' | 'down' | null;

export interface GradeItem {
    value: string | null;
    calificativ: string | null;
    date: string | null;
    term: number | null;
}

export function gradeLabel(item: GradeItem): string {
    if (item.value !== null) {
        return String(Number(item.value));
    }

    return item.calificativ ?? '—';
}

export function isUrl(value: string): boolean {
    return /^https?:\/\//i.test(value);
}

export function motivationStatusClass(status: string): string {
    if (status === 'approved') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'rejected') {
        return 'bg-destructive/10 text-destructive';
    }

    return 'bg-amber-500/10 text-amber-700 dark:text-amber-300';
}

export function trendSymbol(trend: Trend): { symbol: string; cls: string } | null {
    if (trend === 'up') {
        return { symbol: '▲', cls: 'text-emerald-700 dark:text-emerald-300' };
    }

    if (trend === 'down') {
        return { symbol: '▼', cls: 'text-destructive' };
    }

    if (trend === 'stable') {
        return { symbol: '▬', cls: 'text-muted-foreground' };
    }

    return null;
}

/** Construiește punctele unei polilinii SVG dintr-o serie de valori (scalată la min/max propriu). */
export function sparklinePoints(values: number[], width: number, height: number): string {
    if (values.length === 0) {
        return '';
    }

    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    const step = values.length > 1 ? width / (values.length - 1) : 0;

    return values
        .map((v, i) => {
            const x = i * step;
            const y = height - ((v - min) / range) * height;

            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
}
