import { Monitor, Moon, Smartphone, Sun } from 'lucide-react';
import { type ComponentType, useEffect, useRef, useState } from 'react';
import { type Appearance, useAppearance } from '@/hooks/use-appearance';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Variant = 'icon' | 'tabs';
type IconType = ComponentType<{ className?: string }>;

/**
 * Comutator de temă (light / dark / system), sincronizat cu panourile prin `useAppearance`
 * (cheia localStorage `theme`, partajată cu Filament).
 *
 * - `variant="icon"` (implicit, desktop): O SINGURĂ iconiță + dropdown animat.
 * - `variant="tabs"` (responsiv/mobil): pastilă segmentată compactă (doar iconițe) cu indicator
 *   glisant — identică ca înălțime/lățime cu comutatorul de limbă; „system" = iconiță smartphone.
 */
export function ThemeToggle({ className, variant = 'icon' }: { className?: string; variant?: Variant }) {
    const { appearance, updateAppearance } = useAppearance();
    const t = useTranslations();
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }
        const onPointer = (event: MouseEvent) => {
            if (ref.current && !ref.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', onPointer);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onPointer);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    // În varianta desfășurată „system" e reprezentat de smartphone (context responsiv), nu de PC.
    const systemIcon: IconType = variant === 'tabs' ? Smartphone : Monitor;
    const options: { value: Appearance; icon: IconType; tKey: string; fallback: string }[] = [
        { value: 'light', icon: Sun, tKey: 'theme.light', fallback: 'Luminos' },
        { value: 'dark', icon: Moon, tKey: 'theme.dark', fallback: 'Întunecat' },
        { value: 'system', icon: systemIcon, tKey: 'theme.system', fallback: 'Sistem' },
    ];

    if (variant === 'tabs') {
        const activeIndex = Math.max(
            0,
            options.findIndex((option) => option.value === appearance),
        );
        return (
            <div
                className={cn('relative inline-flex rounded-full border border-border bg-background/60 p-0.5 backdrop-blur', className)}
                role="group"
                aria-label={t('theme.label', 'Temă')}
            >
                <span
                    className="absolute top-0.5 bottom-0.5 left-0.5 w-9 rounded-full bg-primary shadow-sm transition-transform duration-300 ease-out"
                    style={{ transform: `translateX(${activeIndex * 100}%)` }}
                    aria-hidden
                />
                {options.map((option) => {
                    const Icon = option.icon;
                    const active = appearance === option.value;
                    const optionLabel = t(option.tKey, option.fallback);
                    return (
                        <button
                            key={option.value}
                            type="button"
                            onClick={() => updateAppearance(option.value)}
                            aria-pressed={active}
                            aria-label={optionLabel}
                            title={optionLabel}
                            className={cn(
                                'relative z-10 inline-flex w-9 items-center justify-center rounded-full py-3 transition-colors',
                                active ? 'text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icon className="size-4" />
                        </button>
                    );
                })}
            </div>
        );
    }

    const Current = options.find((option) => option.value === appearance)?.icon ?? Monitor;

    return (
        <div ref={ref} className={cn('relative', className)}>
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-label={t('theme.label', 'Temă')}
                aria-haspopup="menu"
                aria-expanded={open}
                className="inline-flex size-9 items-center justify-center rounded-md border border-border text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
            >
                <Current className={cn('size-4 transition-transform duration-300 ease-out', open && 'rotate-12 scale-110')} />
            </button>

            <div
                role="menu"
                className={cn(
                    'absolute right-0 top-full z-50 mt-2 w-40 origin-top-right rounded-md border border-border bg-popover p-1 shadow-lg transition-all duration-200 ease-out',
                    open ? 'visible translate-y-0 scale-100 opacity-100' : 'invisible -translate-y-1 scale-95 opacity-0',
                )}
            >
                {options.map((option) => {
                    const Icon = option.icon;
                    const active = appearance === option.value;
                    return (
                        <button
                            key={option.value}
                            type="button"
                            role="menuitemradio"
                            aria-checked={active}
                            onClick={() => {
                                updateAppearance(option.value);
                                setOpen(false);
                            }}
                            className={cn(
                                'flex w-full items-center gap-2.5 rounded-sm px-3 py-2 text-sm transition-colors hover:bg-accent',
                                active ? 'font-medium text-primary' : 'text-foreground/80',
                            )}
                        >
                            <Icon className="size-4" /> {t(option.tKey, option.fallback)}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
