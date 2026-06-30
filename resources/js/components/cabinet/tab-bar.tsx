import type { LucideIcon } from 'lucide-react';
import { useRef  } from 'react';
import type {KeyboardEvent} from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export interface TabItem {
    value: string;
    label: string;
    icon?: LucideIcon;
    badge?: number | string;
}

/**
 * Bară de taburi adresabilă (caller-ul controlează prin `active` + `onChange`; tipic URL searchParam `?tab=...`).
 * Pe mobil bară-scroll orizontal (overflow-x-auto). Țintă tactilă >=44px.
 *
 * Pattern WAI-ARIA Tabs complet (audit § a11y #11):
 *  • `role=tablist/tab/tabpanel` + `aria-selected`, `aria-controls`, `aria-labelledby` (deja existente)
 *  • navigare cu tastatura: ArrowLeft/Right (cu wrap), Home/End — mută tabul activ și focusul
 *  • roving tabindex: doar tabul activ are `tabIndex=0` (intră în ordinea Tab); restul `-1` (focus doar via săgeți)
 *
 * Folosită în `student-profile.tsx` pentru cele 5 taburi comasate (Prezentare, Situație, Orar&Teme, Istoric, Cereri).
 */
export function TabBar({
    items,
    active,
    onChange,
    ariaLabel,
    className,
}: {
    items: TabItem[];
    active: string;
    onChange: (value: string) => void;
    ariaLabel?: string;
    className?: string;
}) {
    const listRef = useRef<HTMLDivElement>(null);
    const activeIndex = Math.max(0, items.findIndex((i) => i.value === active));

    function focusTab(idx: number): void {
        const value = items[idx]?.value;

        if (value === undefined) {
            return;
        }

        const btn = listRef.current?.querySelector<HTMLButtonElement>(
            `[id="tab-${CSS.escape(value)}"]`,
        );
        btn?.focus();
    }

    function handleKeyDown(event: KeyboardEvent<HTMLDivElement>): void {
        const len = items.length;

        if (len === 0) {
            return;
        }

        let next = activeIndex;

        switch (event.key) {
            case 'ArrowRight':
                next = (activeIndex + 1) % len;
                break;
            case 'ArrowLeft':
                next = (activeIndex - 1 + len) % len;
                break;
            case 'Home':
                next = 0;
                break;
            case 'End':
                next = len - 1;
                break;
            default:
                return;
        }

        event.preventDefault();
        const value = items[next]?.value;

        if (value !== undefined && value !== active) {
            onChange(value);
        }

        focusTab(next);
    }

    return (
        <div
            ref={listRef}
            role="tablist"
            aria-label={ariaLabel}
            onKeyDown={handleKeyDown}
            className={cn(
                '-mx-1 flex gap-1 overflow-x-auto border-b border-sidebar-border/70 px-1 dark:border-sidebar-border',
                className,
            )}
        >
            {items.map((item) => {
                const isActive = item.value === active;

                return (
                    <button
                        key={item.value}
                        type="button"
                        role="tab"
                        aria-selected={isActive}
                        aria-controls={`tab-panel-${item.value}`}
                        id={`tab-${item.value}`}
                        tabIndex={isActive ? 0 : -1}
                        onClick={() => onChange(item.value)}
                        className={cn(
                            'inline-flex h-11 shrink-0 items-center gap-2 border-b-2 px-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                            isActive
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {item.icon && <item.icon className="size-4" aria-hidden="true" />}
                        <span>{item.label}</span>
                        {item.badge !== undefined && item.badge !== 0 && (
                            <Badge variant={isActive ? 'default' : 'secondary'} className="px-1.5 py-0">
                                {item.badge}
                            </Badge>
                        )}
                    </button>
                );
            })}
        </div>
    );
}

/**
 * Panou-conținut asociat cu un tab; ascuns când nu e activ. Păstrează DOM-ul (state-preserving).
 * Folosește `id={`tab-panel-<value>`}` pentru ARIA association cu `<TabBar>`.
 */
export function TabPanel({
    value,
    active,
    children,
}: {
    value: string;
    active: string;
    children: React.ReactNode;
}) {
    const isActive = value === active;

    return (
        <div
            role="tabpanel"
            id={`tab-panel-${value}`}
            aria-labelledby={`tab-${value}`}
            hidden={!isActive}
        >
            {isActive && children}
        </div>
    );
}
