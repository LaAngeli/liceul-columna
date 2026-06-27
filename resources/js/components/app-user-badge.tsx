import { usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/i18n';

/** Badge cu tipul de utilizator logat (Elev / Părinte / …), tradus din `site.roles.*`. */
export function AppUserBadge() {
    const { auth } = usePage().props;
    const t = useTranslations();

    if (!auth.role) {
        return null;
    }

    return (
        <Badge
            variant="secondary"
            className="hidden font-semibold sm:inline-flex"
        >
            {t(`roles.${auth.role}`, auth.role)}
        </Badge>
    );
}
