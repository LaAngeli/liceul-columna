import { Head } from '@inertiajs/react';
import { ArrowUpRight, CalendarClock } from 'lucide-react';
import { useMemo, useState } from 'react';
import { LocaleLink } from '@/components/locale-link';
import { Band, FourStar, Reveal, SectionHeader, StatRibbon } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

interface Table {
    label: string;
    headers: string[];
    rows: string[][];
}
interface ScheduleType {
    key: string;
    i18n: string;
    label: string;
    count: number;
    tables: Table[];
}
interface Crumb {
    title: string;
    href?: string;
}
interface Props {
    title: string;
    description?: string;
    breadcrumbs?: Crumb[];
    scheduleTypes: ScheduleType[];
}

const ROMAN: Record<string, number> = { I: 1, II: 2, III: 3, IV: 4, V: 5, VI: 6, VII: 7, VIII: 8, IX: 9, X: 10, XI: 11, XII: 12 };
const RO_DAYS: Record<number, string> = { 1: 'Luni', 2: 'Marți', 3: 'Miercuri', 4: 'Joi', 5: 'Vineri' };
const LEVELS = ['primar', 'gimnaziu', 'liceu'] as const;
type Level = (typeof LEVELS)[number];

function classInfo(label: string): { short: string; grade: number; level: Level } {
    const stripped = label.replace(/^clasa\s+/i, '').trim();
    const grade = ROMAN[stripped.split(/\s+/)[0]?.toUpperCase()] ?? 0;
    const level: Level = grade <= 4 ? 'primar' : grade <= 9 ? 'gimnaziu' : 'liceu';
    return { short: stripped || label, grade, level };
}

/** Tabel de orar în stilul de brand; coloana zilei curente e evidențiată subtil („Azi"). */
function ScheduleTable({ table, todayCol, todayLabel }: { table: Table; todayCol: number; todayLabel: string }) {
    return (
        <div className="overflow-x-auto rounded-[12px] border keyline">
            <table className="w-full border-collapse text-sm">
                <thead>
                    <tr className="bg-surface-navy text-[color:var(--brand-navy-foreground)]">
                        {table.headers.map((h, i) => (
                            <th
                                key={i}
                                className={cn('px-3 py-2.5 text-left font-semibold whitespace-nowrap', i === todayCol && 'bg-brand-green text-[color:var(--brand-green-foreground)]')}
                            >
                                {h}
                                {i === todayCol && <span className="ml-1.5 text-[0.65rem] font-bold tracking-wide uppercase opacity-80">· {todayLabel}</span>}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {table.rows.map((row, ri) => (
                        <tr key={ri} className="even:bg-brand-navy/[0.03]">
                            {row.map((cell, ci) => (
                                <td
                                    key={ci}
                                    className={cn(
                                        'border-t keyline px-3 py-2 align-top break-words',
                                        ci === 0 ? 'font-semibold text-brand-navy' : 'text-brand-dark/90',
                                        ci === todayCol && 'bg-brand-green/[0.08]',
                                    )}
                                >
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Calendar({ title, description, breadcrumbs = [], scheduleTypes }: Props) {
    const t = useTranslations();
    const typeLabel = (ty: ScheduleType) => t(`calendar.${ty.i18n}`, ty.label);

    const firstWithData = scheduleTypes.find((ty) => ty.count > 0) ?? scheduleTypes[0];
    const [activeKey, setActiveKey] = useState(firstWithData?.key ?? '');
    const [classIdx, setClassIdx] = useState(0);

    const active = scheduleTypes.find((ty) => ty.key === activeKey) ?? firstWithData;
    const isLessons = active?.key === 'orarul-lectiilor';

    const [todayName] = useState(() => RO_DAYS[new Date().getDay()] ?? '');

    // Clasele grupate pe treaptă (doar pentru orarul lecțiilor).
    const classGroups = useMemo(() => {
        if (!isLessons || !active) return [];
        const withIdx = active.tables.map((tbl, idx) => ({ idx, label: tbl.label, ...classInfo(tbl.label) }));
        return LEVELS.map((lvl) => ({
            level: lvl,
            classes: withIdx.filter((c) => c.level === lvl).sort((a, b) => a.grade - b.grade || a.short.localeCompare(b.short, 'ro')),
        })).filter((g) => g.classes.length > 0);
    }, [isLessons, active]);

    const todayCol = (headers: string[]) => (todayName ? headers.findIndex((h) => h.trim().toLowerCase() === todayName.toLowerCase()) : -1);

    const activeTypes = scheduleTypes.filter((ty) => ty.count > 0).length;
    const lessonsCount = scheduleTypes.find((ty) => ty.key === 'orarul-lectiilor')?.count ?? 0;
    const totalTables = scheduleTypes.reduce((s, ty) => s + ty.count, 0);
    const stats = [
        { value: String(activeTypes), label: t('calendar.types_active', 'tipuri de orar') },
        { value: String(lessonsCount), label: t('calendar.classes', 'orare pe clase') },
        { value: String(totalTables), label: t('calendar.programs', 'programe publicate'), accent: true },
    ];

    const selectType = (key: string) => {
        setActiveKey(key);
        setClassIdx(0);
    };

    const selectedClass = isLessons && active ? active.tables[classIdx] : null;

    return (
        <>
            <Head title={title} />

            <PageBanner title={title} breadcrumbs={breadcrumbs} description={t('calendar.intro', description)} />

            {/* Statistici + selectorul de tip */}
            <Band className="!py-[clamp(2rem,4vw,3.5rem)]">
                <StatRibbon items={stats} />

                <div className="mt-8 flex flex-wrap gap-2">
                    {scheduleTypes.map((ty) => {
                        const isActive = ty.key === activeKey;
                        const empty = ty.count === 0;
                        return (
                            <button
                                key={ty.key}
                                type="button"
                                onClick={() => selectType(ty.key)}
                                className={cn(
                                    'inline-flex min-h-9 items-center gap-2 rounded-full border px-3.5 text-sm font-semibold transition-colors',
                                    isActive ? 'border-brand-navy bg-surface-navy text-[color:var(--brand-navy-foreground)]' : 'keyline bg-card text-brand-navy hover:border-brand-navy',
                                    empty && !isActive && 'opacity-55',
                                )}
                            >
                                {typeLabel(ty)}
                                <span className={cn('numeral text-xs', isActive ? 'text-white/70' : 'text-brand-gray')}>{ty.count}</span>
                            </button>
                        );
                    })}
                </div>
            </Band>

            <Band variant="light" className="!pt-0">
                {active && (
                    <Reveal>
                        <div className="mb-6 flex flex-wrap items-end justify-between gap-4 border-b keyline pb-5">
                            <SectionHeader index="01" label={`${active.count} ${t('calendar.programs', 'programe')}`} title={typeLabel(active)} />
                            <LocaleLink
                                href={`/${active.key}`}
                                className="inline-flex min-h-9 items-center gap-1.5 text-sm font-semibold text-brand-navy underline decoration-brand-green decoration-2 underline-offset-4 hover:decoration-[3px]"
                            >
                                {t('calendar.open_full', 'Pagina completă')} <ArrowUpRight className="size-4" />
                            </LocaleLink>
                        </div>

                        {active.count === 0 ? (
                            <div className="flex flex-col items-center gap-3 rounded-[14px] border border-dashed keyline bg-card px-6 py-16 text-center">
                                <CalendarClock className="size-9 text-brand-navy/30" />
                                <p className="max-w-sm text-brand-gray">{t('calendar.empty', 'Acest program va fi publicat în curând.')}</p>
                            </div>
                        ) : isLessons ? (
                            <div className="space-y-7">
                                {/* Selector de clasă, grupat pe treaptă */}
                                <div className="space-y-4">
                                    <span className="inline-flex items-center gap-1.5 text-xs font-semibold tracking-wide text-brand-gray uppercase">
                                        <FourStar className="size-3 text-brand-green" /> {t('calendar.pick_class', 'Alege clasa')}
                                    </span>
                                    {classGroups.map((group) => (
                                        <div key={group.level} className="flex flex-wrap items-center gap-2">
                                            <span className="w-20 shrink-0 text-xs font-semibold text-brand-navy/70">{t(`calendar.${group.level}`, group.level)}</span>
                                            {group.classes.map((c) => (
                                                <button
                                                    key={c.idx}
                                                    type="button"
                                                    onClick={() => setClassIdx(c.idx)}
                                                    className={cn(
                                                        'inline-flex min-h-9 min-w-11 items-center justify-center rounded-md border px-2.5 text-sm font-semibold transition-colors',
                                                        classIdx === c.idx
                                                            ? 'border-brand-green bg-brand-green text-[color:var(--brand-green-foreground)]'
                                                            : 'keyline bg-card text-brand-navy hover:border-brand-navy',
                                                    )}
                                                    style={{ fontFamily: 'var(--font-display)' }}
                                                >
                                                    {c.short}
                                                </button>
                                            ))}
                                        </div>
                                    ))}
                                </div>

                                {selectedClass && (
                                    <figure>
                                        <figcaption className="mb-3 display text-[1.25rem] text-brand-navy">{selectedClass.label}</figcaption>
                                        <ScheduleTable table={selectedClass} todayCol={todayCol(selectedClass.headers)} todayLabel={t('calendar.today', 'Azi')} />
                                    </figure>
                                )}
                            </div>
                        ) : (
                            <div className="space-y-8">
                                {active.tables.map((tbl, i) => (
                                    <figure key={i}>
                                        {tbl.label && <figcaption className="mb-3 display text-[1.25rem] text-brand-navy">{tbl.label}</figcaption>}
                                        <ScheduleTable table={tbl} todayCol={todayCol(tbl.headers)} todayLabel={t('calendar.today', 'Azi')} />
                                    </figure>
                                ))}
                            </div>
                        )}

                        <p className="mt-10 max-w-3xl border-l-2 border-l-brand-green/50 pl-4 text-xs leading-relaxed text-brand-gray">
                            {t('calendar.updated')}
                        </p>
                    </Reveal>
                )}
            </Band>
        </>
    );
}
