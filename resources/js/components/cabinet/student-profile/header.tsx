import { Link, router } from '@inertiajs/react';
import { CalendarDays, Bell, MessageSquare } from 'lucide-react';
import { StudentStatusBadge } from '@/components/cabinet/student-status-badge';
import type { StudentStatusValue } from '@/components/cabinet/student-status-badge';
import { Badge } from '@/components/ui/badge';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/lib/i18n';

interface StudentSummary {
    id: number;
    name: string;
    class: string | null;
    grades_count: number;
    absences_count: number;
    average: number | null;
}

interface StudentStatus {
    status: 'promovat' | 'corigent' | 'repetent' | 'amanat' | null;
    label: string | null;
    failingSubjects: string[];
    official: boolean;
    orderReference: string | null;
}

interface Sibling {
    id: number;
    name: string;
}

/** Statistică din antet — cu `onPress` devine buton (salt la secțiunea din Situație), altfel simplu text. */
function HeaderStat({
    value,
    label,
    valueClassName = '',
    onPress,
}: {
    value: string | number;
    label: string;
    valueClassName?: string;
    onPress?: () => void;
}) {
    const content = (
        <>
            <div className={`text-2xl font-semibold ${valueClassName}`}>{value}</div>
            <p className="text-xs text-muted-foreground">{label}</p>
        </>
    );

    if (onPress) {
        return (
            <button
                type="button"
                onClick={onPress}
                className="rounded-lg px-2.5 py-1.5 text-center transition-[background-color,box-shadow] duration-150 hover:bg-muted hover:ring-1 hover:ring-primary/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring motion-reduce:transition-none"
            >
                {content}
            </button>
        );
    }

    return <div className="px-2.5 py-1.5">{content}</div>;
}

/**
 * Header persistent al profilului elev — vizibil deasupra TabBar-ului, NU se schimbă la swap de tab.
 * Conține identitate (avatar+nume+clasă), badge status, badge „oficial", switcher copil (părinte cu mai
 * mulți copii) + acțiuni rapide.
 */
export function ProfileHeader({
    student,
    status,
    totals,
    siblings = [],
    isFamily = true,
    onOpenSection,
}: {
    student: StudentSummary;
    status: StudentStatus;
    totals: {
        absencesTotal: number;
        absencesMotivated: number;
        absencesUnmotivated: number;
    };
    siblings?: Sibling[];
    isFamily?: boolean;
    /** Statisticile antetului devin apăsabile: salt la secțiunea indicată din tabul Situație. */
    onOpenSection?: (section: 'note' | 'absente') => void;
}) {
    const t = useTranslations();
    const getInitials = useInitials();

    // Audit § profil #6 — la schimbarea copilului păstrăm tabul activ (?tab=...) din URL.
    function switchChild(id: number) {
        if (id === student.id) {
            return;
        }

        const search = typeof window !== 'undefined' ? window.location.search : '';
        router.get(`/cabinet/elev/${id}${search}`);
    }

    return (
        <div className="flex flex-wrap items-center gap-4 rounded-xl border bg-card p-5 text-card-foreground shadow-sm">
            <span className="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary/10 text-2xl font-semibold text-primary">
                {getInitials(student.name)}
            </span>
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h1 className="truncate text-xl font-semibold">{student.name}</h1>
                    {siblings.length > 1 && (
                        <label className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span className="sr-only">{t('cabinet.tab_switch_child')}</span>
                            <select
                                value={student.id}
                                onChange={(e) => switchChild(Number(e.target.value))}
                                aria-label={t('cabinet.tab_switch_child')}
                                className="h-8 rounded-md border border-input bg-background px-2 text-xs text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                {siblings.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                </div>
                <p className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                    <span>{student.class ?? t('cabinet.class_unassigned')}</span>
                    {status.status !== null && (
                        <StudentStatusBadge status={status.status as StudentStatusValue} />
                    )}
                    {status.official && (
                        <Badge variant="default" className="bg-sky-500/15 text-sky-700 hover:bg-sky-500/15 dark:text-sky-300" title={status.orderReference ?? undefined}>
                            ✓ {t('cabinet.status_official')}
                        </Badge>
                    )}
                    {(status.status === 'corigent' || status.status === 'repetent') && status.failingSubjects.length > 0 && (
                        <span className="text-xs text-destructive">{status.failingSubjects.join(', ')}</span>
                    )}
                </p>
            </div>

            {/* Statisticile duc la secțiunea lor din „Situație" (media → note, absențele → tabelul
                de absențe) — butoane cu salt în pagină, vizibile din ORICE tab (antetul persistă). */}
            <div className="ml-auto flex items-center gap-2 text-center">
                <HeaderStat
                    value={student.average ?? '—'}
                    label={t('cabinet.average_general')}
                    valueClassName="text-primary"
                    onPress={onOpenSection ? () => onOpenSection('note') : undefined}
                />
                <HeaderStat
                    value={totals.absencesTotal}
                    label={t('cabinet.absences')}
                    onPress={onOpenSection ? () => onOpenSection('absente') : undefined}
                />
            </div>

            {/* Acțiuni rapide — DOAR pentru familie: sunt scurtături spre cabinetul personal
                (calendar/mesaje/notificări), care pentru personal redirecționează la /admin. */}
            {isFamily && (
                <div className="flex w-full flex-wrap gap-2 sm:w-auto">
                    <Link
                        href="/cabinet/calendar"
                        className="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted"
                    >
                        <CalendarDays className="size-4" aria-hidden="true" />
                        {t('ccal.title')}
                    </Link>
                    <Link
                        href="/cabinet/mesaje"
                        className="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted"
                    >
                        <MessageSquare className="size-4" aria-hidden="true" />
                        {t('cabinet.messages_title')}
                    </Link>
                    <Link
                        href="/cabinet/notificari"
                        className="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted"
                    >
                        <Bell className="size-4" aria-hidden="true" />
                        {t('cabinet.notif_title')}
                    </Link>
                </div>
            )}
        </div>
    );
}
