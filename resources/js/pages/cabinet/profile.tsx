import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    CalendarDays,
    CalendarX,
    GraduationCap,
    Languages,
    Lock,
    Mail,
    Shield,
    TrendingUp,
    UserRound,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

interface StudentCard {
    id: number;
    name: string;
    class: string | null;
    grades_count: number;
    absences_count: number;
    average: number | null;
    statusValue: string | null;
    statusLabel: string | null;
}

interface Account {
    name: string;
    username: string | null;
    email: string | null;
    role: string | null;
    memberSince: string | null;
    locale: string | null;
}

interface Props {
    account: Account;
    self: StudentCard | null;
    children: StudentCard[];
}

/** Rând cheie–valoare în cardul de cont (read-only). */
function InfoRow({ icon: Icon, label, value }: { icon: typeof Mail; label: string; value: string }) {
    return (
        <div className="flex items-start gap-3 py-2.5">
            <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <div className="min-w-0 flex-1">
                <dt className="text-xs text-muted-foreground">{label}</dt>
                <dd className="truncate text-sm font-medium">{value}</dd>
            </div>
        </div>
    );
}

/** Badge de situație: corigent/amânat = accent de avertizare; altfel „în regulă”. */
function StatusBadge({ student }: { student: StudentCard }) {
    const t = useTranslations();
    const atRisk = student.statusValue === 'corigent' || student.statusValue === 'amanat';
    const label = student.statusLabel ?? t('profile.status_ok', 'În regulă');

    return (
        <Badge variant={atRisk ? 'destructive' : 'secondary'} className="font-semibold">
            {label}
        </Badge>
    );
}

/** Card compact pentru un copil (părinte), cu link către profilul complet. */
function ChildCard({ student }: { student: StudentCard }) {
    const t = useTranslations();

    return (
        <Link
            href={`/cabinet/elev/${student.id}`}
            className="group flex flex-col rounded-xl border border-sidebar-border/70 bg-card p-5 transition-colors hover:border-primary dark:border-sidebar-border"
        >
            <div className="flex items-center gap-3">
                <span className="flex size-11 items-center justify-center rounded-full bg-primary/10 text-base font-semibold text-primary">
                    {student.name.charAt(0)}
                </span>
                <div className="min-w-0">
                    <h3 className="truncate font-semibold">{student.name}</h3>
                    <p className="text-sm text-muted-foreground">
                        {student.class ?? t('cabinet.class_unassigned', 'Clasă nealocată')}
                    </p>
                </div>
                <ArrowUpRight className="ml-auto size-5 text-muted-foreground group-hover:text-primary" />
            </div>
            <div className="mt-4 flex items-center justify-between">
                <span className="inline-flex items-center gap-1 text-sm font-semibold">
                    <TrendingUp className="size-3.5" /> {student.average ?? '—'}
                </span>
                <StatusBadge student={student} />
            </div>
        </Link>
    );
}

export default function CabinetProfile({ account, self, children: students = [] }: Props) {
    const t = useTranslations();
    const getInitials = useInitials();

    const language = account.locale
        ? t(`profile.lang_${account.locale}`, account.locale.toUpperCase())
        : '—';

    return (
        <>
            <Head title={t('profile.head', 'Profil')} />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight">{t('profile.head', 'Profil')}</h1>
                    <p className="text-sm text-muted-foreground">{t('profile.subtitle', 'Informațiile contului tău')}</p>
                </header>

                {/* Cardul contului (read-only) */}
                <section className="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                    <div className="flex items-center gap-4">
                        <span className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-lg font-semibold text-primary">
                            {getInitials(account.name)}
                        </span>
                        <div className="min-w-0">
                            <h2 className="truncate text-lg font-semibold">{account.name}</h2>
                            {account.role && (
                                <Badge variant="secondary" className="mt-1 font-semibold">
                                    {t(`roles.${account.role}`, account.role)}
                                </Badge>
                            )}
                        </div>
                    </div>

                    <dl className="mt-4 grid gap-x-8 sm:grid-cols-2">
                        <InfoRow icon={UserRound} label={t('profile.username', 'Utilizator')} value={account.username ?? '—'} />
                        <InfoRow icon={Mail} label={t('profile.email', 'E-mail')} value={account.email ?? '—'} />
                        <InfoRow icon={Shield} label={t('profile.role', 'Rol')} value={account.role ? t(`roles.${account.role}`, account.role) : '—'} />
                        <InfoRow icon={CalendarDays} label={t('profile.member_since', 'Membru din')} value={account.memberSince ?? '—'} />
                        <InfoRow icon={Languages} label={t('profile.language', 'Limbă')} value={language} />
                    </dl>

                    <p className="mt-2 flex items-start gap-2 rounded-lg bg-muted/60 p-3 text-xs text-muted-foreground">
                        <Lock className="mt-0.5 size-3.5 shrink-0" />
                        {t('profile.readonly_note', 'Datele contului sunt gestionate de administrația liceului. Pentru orice modificare, contactează secretariatul.')}
                    </p>
                </section>

                {/* Datele mele (elev) */}
                {self && (
                    <section className="rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                        <div className="mb-4 flex items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold">{t('profile.my_data', 'Datele mele')}</h2>
                            <StatusBadge student={self} />
                        </div>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <Stat label={t('profile.class', 'Clasa')} value={self.class ?? '—'} icon={GraduationCap} />
                            <Stat label={t('profile.average', 'Media generală')} value={self.average !== null ? String(self.average) : '—'} icon={TrendingUp} />
                            <Stat label={t('profile.grades', 'Note')} value={String(self.grades_count)} icon={GraduationCap} />
                            <Stat label={t('profile.absences', 'Absențe')} value={String(self.absences_count)} icon={CalendarX} />
                        </div>
                        <Link
                            href={`/cabinet/elev/${self.id}`}
                            className="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                        >
                            {t('profile.view_full', 'Vezi profilul complet')}
                            <ArrowUpRight className="size-4" />
                        </Link>
                    </section>
                )}

                {/* Copiii mei (părinte) */}
                {students.length > 0 && (
                    <section>
                        <h2 className="mb-3 text-lg font-semibold">{t('profile.my_children', 'Copiii mei')}</h2>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {students.map((child) => (
                                <ChildCard key={child.id} student={child} />
                            ))}
                        </div>
                    </section>
                )}

                {!self && students.length === 0 && (
                    <p className="rounded-xl border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border">
                        {t('profile.no_children', 'Nu există elevi asociați acestui cont.')}
                    </p>
                )}
            </div>
        </>
    );
}

/** Mică statistică în „Datele mele”. */
function Stat({ label, value, icon: Icon }: { label: string; value: string; icon: typeof TrendingUp }) {
    return (
        <div className={cn('rounded-lg bg-muted/50 p-3')}>
            <div className="flex items-center gap-1.5 text-base font-semibold">
                <Icon className="size-4 text-muted-foreground" /> {value}
            </div>
            <p className="mt-0.5 text-xs text-muted-foreground">{label}</p>
        </div>
    );
}

CabinetProfile.layout = {
    breadcrumbs: [
        {
            title: 'profile.head',
            href: '/cabinet/profil',
        },
    ],
};
