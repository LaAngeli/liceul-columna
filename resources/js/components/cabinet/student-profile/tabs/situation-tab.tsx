import {
    AbsenceRegister,
    AbsenceTotals,
    GradesTable,
    MotivationsPanel,
} from '@/components/cabinet/catalog/situation-views';
import type { AbsenceRegisterData, MotivationItem, MotivationWindow, SubjectGrades } from '@/components/cabinet/catalog/situation-views';
import { SectionHeading } from '@/components/cabinet/section-heading';
import { SkeletonGrid, SkeletonTable } from '@/components/cabinet/student-profile/skeletons';
import { useTranslations } from '@/lib/i18n';

/**
 * Tab Situație — note + absențe + motivări. COMPUS din vederile partajate `catalog/situation-views`
 * (aceleași componente ca modulele „Note"/„Absențe" din meniu → design garantat identic).
 * Prop-urile defer (subjects, absenceRegister, motivations) sosesc progresiv; skeleton până atunci.
 */
export function SituationTab({
    studentId,
    subjects,
    absenceRegister,
    absencesMotivated,
    absencesUnmotivated,
    motivations,
    motivationWindow,
    canRequestMotivation,
    onContestGrade,
}: {
    studentId: number;
    subjects?: SubjectGrades[];
    absenceRegister?: AbsenceRegisterData;
    absencesMotivated: number;
    absencesUnmotivated: number;
    motivations?: MotivationItem[];
    motivationWindow: MotivationWindow | null;
    canRequestMotivation: boolean;
    /** Familia poate porni o contestație direct din chip-ul notei (pre-completează cererea). */
    onContestGrade?: (gradeId: number) => void;
}) {
    const t = useTranslations();

    return (
        <div className="flex flex-col gap-6">
            {/* === NOTE === */}
            {/* Ancorele `sectiune-*`: țintele deep-link-urilor din tile-urile cockpitului
                (?tab=situation&sectiune=…); scroll-mt lasă loc sub marginea viewportului. */}
            <section id="sectiune-note" className="scroll-mt-20">
                <SectionHeading title={t('cabinet.grades_by_subject')} />
                <details className="mb-3 rounded-lg border bg-muted/30 px-3 py-2 text-xs">
                    <summary className="cursor-pointer font-medium text-muted-foreground">
                        ℹ️ {t('cabinet.info_procedure')}
                    </summary>
                    <p className="mt-1.5 text-muted-foreground">{t('cabinet.extract_grades')}</p>
                </details>

                {subjects === undefined ? (
                    <SkeletonTable rows={6} />
                ) : (
                    <GradesTable subjects={subjects} onContestGrade={onContestGrade} />
                )}
            </section>

            {/* === ABSENȚE === */}
            <section id="sectiune-absente" className="scroll-mt-20">
                <SectionHeading
                    title={t('cabinet.reg_title')}
                    actions={<AbsenceTotals motivated={absencesMotivated} unmotivated={absencesUnmotivated} />}
                />
                <details className="mb-3 rounded-lg border bg-muted/30 px-3 py-2 text-xs">
                    <summary className="cursor-pointer font-medium text-muted-foreground">
                        ℹ️ {t('cabinet.info_procedure')}
                    </summary>
                    <p className="mt-1.5 text-muted-foreground">{t('cabinet.extract_absences')}</p>
                </details>

                {absenceRegister === undefined ? (
                    <SkeletonGrid count={6} columns={3} />
                ) : (
                    <AbsenceRegister register={absenceRegister} />
                )}
            </section>

            {/* === MOTIVĂRI === */}
            {(canRequestMotivation || (motivations && motivations.length > 0)) && (
                <section id="sectiune-motivari" className="scroll-mt-20">
                    <SectionHeading title={t('cabinet.motivations_title')} />
                    {motivations === undefined ? (
                        <SkeletonTable rows={3} />
                    ) : (
                        <MotivationsPanel
                            studentId={studentId}
                            motivations={motivations}
                            canRequest={canRequestMotivation}
                            window={motivationWindow}
                        />
                    )}
                </section>
            )}
        </div>
    );
}
