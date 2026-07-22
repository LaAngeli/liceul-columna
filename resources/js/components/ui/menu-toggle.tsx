import { cn } from '@/lib/utils';

/**
 * Hamburger animat (☰ ⇄ ✕) pentru meniul mobil: trei linii care se transformă fluid în X.
 * Doar `transform`/`opacity` (GPU) + feedback tactil `active:scale-90`; respectă reduced-motion.
 * Țintă tactilă 44×44px (WCAG 2.5.5). Starea vine din exterior (`open`) ca aceeași componentă
 * să servească atât deschiderea (în header), cât și închiderea (în interiorul meniului).
 */
export function MenuToggle({
    open,
    onClick,
    label,
    className,
}: {
    open: boolean;
    onClick: () => void;
    label: string;
    className?: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-expanded={open}
            aria-label={label}
            data-menu-toggle
            className={cn(
                'inline-flex size-11 shrink-0 items-center justify-center rounded-md text-current',
                'transition-[background-color,transform] duration-200 hover:bg-muted active:scale-90',
                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                'motion-reduce:transition-none',
                className,
            )}
        >
            <span aria-hidden="true" className="relative block h-[14px] w-[18px]">
                <span
                    className={cn(
                        'absolute top-0 left-0 h-[2px] w-full rounded-full bg-current',
                        'transition-transform duration-300 ease-out motion-reduce:transition-none',
                        open && 'translate-y-[6px] rotate-45',
                    )}
                />
                <span
                    className={cn(
                        'absolute top-[6px] left-0 h-[2px] w-full rounded-full bg-current',
                        'transition-[opacity,transform] duration-200 ease-out motion-reduce:transition-none',
                        open && '-translate-x-1 opacity-0',
                    )}
                />
                <span
                    className={cn(
                        'absolute top-[12px] left-0 h-[2px] w-full rounded-full bg-current',
                        'transition-transform duration-300 ease-out motion-reduce:transition-none',
                        open && '-translate-y-[6px] -rotate-45',
                    )}
                />
            </span>
        </button>
    );
}
