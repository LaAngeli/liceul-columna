import { EmptyState } from '@/components/cabinet/empty-state';
import { SectionHeading } from '@/components/cabinet/section-heading';
import { trendSymbol  } from '@/components/cabinet/student-profile/helpers';
import type {Trend} from '@/components/cabinet/student-profile/helpers';
import { SkeletonTable } from '@/components/cabinet/student-profile/skeletons';
import { useTranslations } from '@/lib/i18n';

interface TranscriptSubject {
    subject: string;
    sem1: string | null;
    sem2: string | null;
    annual: string | null;
}

interface TranscriptLevel {
    grade_level: number;
    subjects: TranscriptSubject[];
}

interface DynamicsSubject {
    subject: string;
    points: { level: number; value: number }[];
    trend: Trend;
}

interface Dynamics {
    general: { level: number; average: number }[];
    subjects: DynamicsSubject[];
    current: {
        average: number | null;
        historyAverage: number | null;
        previousYearSameTerm: number | null;
        trend: Trend;
        alert: boolean;
    };
}

/**
 * Tab Istoric — foaie matricolă + dinamica pe discipline (sparkline+tendință).
 */
export function HistoryTab({
    transcript,
    dynamics,
}: {
    transcript?: TranscriptLevel[];
    dynamics?: Dynamics;
}) {
    const t = useTranslations();

    return (
        <div className="flex flex-col gap-6">
            {/* === DINAMICA PE DISCIPLINE === */}
            <section>
                <SectionHeading
                    title={t('cabinet.dynamics_by_subject')}
                    description={dynamics?.current.historyAverage !== undefined && dynamics.current.historyAverage !== null
                        ? `${t('cabinet.dynamics_history')}: ${dynamics.current.historyAverage}`
                        : undefined}
                />
                {dynamics === undefined ? (
                    <SkeletonTable rows={5} />
                ) : dynamics.subjects.length === 0 ? (
                    <EmptyState title={t('cabinet.no_dynamics')} />
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <ul className="divide-y">
                            {dynamics.subjects.map((s) => {
                                const tr = trendSymbol(s.trend);
                                const last = s.points[s.points.length - 1];

                                return (
                                    <li key={s.subject} className="flex items-center justify-between gap-3 px-4 py-2">
                                        <span className="truncate text-sm">{s.subject}</span>
                                        <span className="flex items-center gap-2">
                                            <span className="text-sm font-semibold">{last ? last.value : '—'}</span>
                                            {tr && (
                                                <span
                                                    className={`text-xs ${tr.cls}`}
                                                    title={t(`cabinet.dynamics_trend_${s.trend}`)}
                                                    aria-label={t(`cabinet.dynamics_trend_${s.trend}`)}
                                                >
                                                    {tr.symbol}
                                                </span>
                                            )}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}
            </section>

            {/* === FOAIE MATRICOLĂ === */}
            <section>
                <SectionHeading title={t('cabinet.transcript')} />
                {transcript === undefined ? (
                    <SkeletonTable rows={6} />
                ) : transcript.length === 0 ? (
                    <EmptyState title={t('cabinet.no_transcript')} />
                ) : (
                    <div className="flex flex-col gap-3">
                        {transcript.map((level, idx) => (
                            <details key={level.grade_level} open={idx === 0} className="overflow-hidden rounded-xl border">
                                <summary className="cursor-pointer bg-muted/50 px-4 py-2 text-sm font-medium">
                                    {t('cabinet.class')} {level.grade_level}
                                </summary>
                                <table className="w-full text-sm">
                                    <thead className="text-left text-muted-foreground">
                                        <tr className="border-t">
                                            <th scope="col" className="px-4 py-2 font-medium">{t('cabinet.subject')}</th>
                                            <th scope="col" className="px-4 py-2 text-center font-medium">{t('cabinet.sem1')}</th>
                                            <th scope="col" className="px-4 py-2 text-center font-medium">{t('cabinet.sem2')}</th>
                                            <th scope="col" className="px-4 py-2 text-center font-medium">{t('cabinet.annual')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {level.subjects.map((s) => (
                                            <tr key={s.subject} className="border-t">
                                                <th scope="row" className="px-4 py-2 text-left font-medium">{s.subject}</th>
                                                <td className="px-4 py-2 text-center">{s.sem1 ?? '—'}</td>
                                                <td className="px-4 py-2 text-center">{s.sem2 ?? '—'}</td>
                                                <td className="px-4 py-2 text-center font-semibold">{s.annual ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </details>
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}
