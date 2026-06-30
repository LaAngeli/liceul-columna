import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type StudentStatusValue = 'promovat' | 'corigent' | 'amanat' | null;

/**
 * Pastila de statut elev — încapsulează logica de „risc" (corigent/amânat) vs „în regulă" + traducere.
 * Caller-ul pasează doar valoarea enum (`status`); textul vine din `cabinet.status_*` (chei deja existente).
 * Dacă status e null, afișează „În regulă" (`profile.status_ok`).
 *
 * Folosit în `profile.tsx`, `student-profile.tsx` și (viitor) carduri din dashboard.
 */
export function StudentStatusBadge({
    status,
    className,
}: {
    status: StudentStatusValue;
    className?: string;
}) {
    const t = useTranslations();
    const atRisk = status === 'corigent' || status === 'amanat';

    const label = status === 'corigent'
        ? t('cabinet.status_corigent')
        : status === 'amanat'
            ? t('cabinet.status_amanat')
            : status === 'promovat'
                ? t('cabinet.status_promovat')
                : t('profile.status_ok', 'În regulă');

    return (
        <Badge
            variant={atRisk ? 'destructive' : status === 'promovat' ? 'default' : 'secondary'}
            className={cn('font-semibold', className)}
        >
            {label}
        </Badge>
    );
}
