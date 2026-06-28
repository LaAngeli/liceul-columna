/**
 * Marca „Liceul Columna" pentru navbar — lockup ORIZONTAL (emblemă + wordmark, brandbook).
 * Theme-aware: varianta COLOR (navy + verde, `0018`) pe fundal deschis, varianta ALBĂ (`0011`)
 * pe fundal întunecat. WebP optimizat (~12-18 KB). NICIODATĂ recolorat/distorsionat (brandbook p17).
 * Înălțimea vine din `imgClassName` (setată de header, scalată responsiv).
 */
import { cn } from '@/lib/utils';

const COLOR = '/images/logo/columna-wordmark.webp';
const WHITE = '/images/logo/columna-wordmark-white.webp';

export function LogoLockup({
    className,
    imgClassName,
    tone = 'auto',
    label = 'Liceul Columna',
}: {
    className?: string;
    imgClassName?: string;
    tone?: 'auto' | 'color' | 'white';
    label?: string;
}) {
    if (tone === 'white') {
        return (
            <span className={cn('inline-flex items-center', className)}>
                <img src={WHITE} alt={label} className={cn('w-auto', imgClassName)} />
            </span>
        );
    }
    if (tone === 'color') {
        return (
            <span className={cn('inline-flex items-center', className)}>
                <img src={COLOR} alt={label} className={cn('w-auto', imgClassName)} />
            </span>
        );
    }
    return (
        <span className={cn('inline-flex items-center', className)}>
            <img src={COLOR} alt={label} className={cn('w-auto dark:hidden', imgClassName)} />
            <img src={WHITE} alt={label} aria-hidden="true" className={cn('hidden w-auto dark:block', imgClassName)} />
        </span>
    );
}
