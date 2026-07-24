import { Head } from '@inertiajs/react';
import { BookOpen, ClipboardList, FileText, History, LayoutDashboard } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { AbsenceOverviewData } from '@/components/cabinet/catalog/absence-views';
import type { GradeBookData } from '@/components/cabinet/catalog/gradebook-views';
import type { HomeworkItem } from '@/components/cabinet/catalog/homework-views';
import type { WeeklyData } from '@/components/cabinet/catalog/schedule-views';
import type { MotivationItem, MotivationWindow } from '@/components/cabinet/catalog/situation-views';
import { ProfileHeader } from '@/components/cabinet/student-profile/header';
import type { Trend } from '@/components/cabinet/student-profile/helpers';
import { HistoryTab } from '@/components/cabinet/student-profile/tabs/history-tab';
import { OverviewTab } from '@/components/cabinet/student-profile/tabs/overview-tab';
import { RequestsTab } from '@/components/cabinet/student-profile/tabs/requests-tab';
import { ScheduleTab } from '@/components/cabinet/student-profile/tabs/schedule-tab';
import { SituationTab } from '@/components/cabinet/student-profile/tabs/situation-tab';
import { TabBar, TabPanel  } from '@/components/cabinet/tab-bar';
import type {TabItem} from '@/components/cabinet/tab-bar';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';

interface StudentSummary {
    id: number;
    name: string;
    class: string | null;
    grades_count: number;
    absences_count: number;
    average: number | null;
    // Data plecării din liceu (dacă a plecat) — banner de semnal (#37).
    departedOn: string | null;
}

interface StudentStatus {
    status: 'promovat' | 'corigent' | 'repetent' | 'amanat' | null;
    label: string | null;
    failingSubjects: string[];
    official: boolean;
    orderReference: string | null;
}

interface StatusAck {
    needed: boolean;
    acknowledged: boolean;
    acknowledgedAt: string | null;
    acknowledgedBy: string | null;
    canAcknowledge: boolean;
}

interface Dynamics {
    general: { level: number; average: number }[];
    subjects: { subject: string; points: { level: number; value: number }[]; trend: Trend }[];
    current: {
        average: number | null;
        historyAverage: number | null;
        previousYearSameTerm: number | null;
        trend: Trend;
        alert: boolean;
    };
}

interface Props {
    // Eager (vin la prima încărcare)
    student: StudentSummary;
    status: StudentStatus;
    statusAck: StatusAck;
    absencesTotal: number;
    absencesMotivated: number;
    absencesUnmotivated: number;
    requestTypes: Record<string, string>;
    canRequestMotivation: boolean;
    // Lista copiilor părintelui (gol pentru elev/personal) — pentru switcher în header (audit #6).
    siblings: { id: number; name: string }[];

    // Fereastra de depunere a motivării (anul curent → azi) — eager, pentru min/max în formular.
    motivationWindow: MotivationWindow | null;

    // Defer (sosesc progresiv într-un al 2-lea request după mount)
    gradebook?: GradeBookData;
    absenceOverview?: AbsenceOverviewData;
    transcript?: { grade_level: number; subjects: { subject: string; sem1: string | null; sem2: string | null; annual: string | null }[] }[];
    homework?: HomeworkItem[];
    dynamics?: Dynamics;
    // Orarul săptămânal NORMALIZAT (publicat/structurat, o singură formă — App\Support\WeeklySchedule).
    weekly?: WeeklyData | null;
    deferralRisk?: { risks: { subject: string; absences: number; scheduled: number }[]; undetermined: string[]; noTimetable: boolean };
    motivations?: MotivationItem[];
    corigentaExams?: {
        id: number;
        subject: string;
        season: string;
        scheduledOn: string | null;
        commission: string | null;
        sessionType: string | null;
        mark: string | null;
        passed: boolean | null;
    }[];
    documentRequests?: {
        id: number;
        type: string;
        date: string | null;
        status: 'pending' | 'approved' | 'rejected';
        statusLabel: string;
        pdfUrl: string | null;
        attachmentUrl?: string | null;
        note: string | null;
        grade?: string | null;
        canWithdraw?: boolean;
    }[];
    // Totalul real al cererilor — lista de mai sus e plafonată la 15 (indicator de trunchiere).
    documentRequestsTotal?: number;
    // Notele contestabile (doar familia) — alimentează selectul obligatoriu al contestației.
    contestableGrades?: { id: number; label: string }[];
}

type TabValue = 'overview' | 'situation' | 'schedule' | 'history' | 'requests';
const VALID_TABS: ReadonlyArray<TabValue> = ['overview', 'situation', 'schedule', 'history', 'requests'];

function readInitialTab(): TabValue {
    if (typeof window === 'undefined') {
        return 'overview';
    }

    const param = new URLSearchParams(window.location.search).get('tab');

    return param !== null && (VALID_TABS as readonly string[]).includes(param) ? (param as TabValue) : 'overview';
}

// Secțiunea-țintă din tabul Situație (?sectiune=note|absente|motivari) — deep-link-urile
// tile-urilor din cockpit („Ultima notă" / „Absențe noi" / „Motivări") aterizează exact
// pe blocul indicat, nu doar pe tab. Validat: orice altă valoare se ignoră.
const VALID_SECTIONS = ['note', 'absente', 'motivari'] as const;

function readSectionParam(): string | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const param = new URLSearchParams(window.location.search).get('sectiune');

    return param !== null && (VALID_SECTIONS as readonly string[]).includes(param) ? param : null;
}

export default function StudentProfile(props: Props) {
    const t = useTranslations();
    const [activeTab, setActiveTab] = useState<TabValue>(readInitialTab);
    // Intenția „Contestă această notă" pornită din chip-ul unei note (tab Situație): comută pe
    // tabul Cereri cu formularul pre-completat. Token-ul crește la fiecare click, ca aceeași notă
    // să poată re-declanșa pre-completarea după un submit.
    const [contestIntent, setContestIntent] = useState<{ gradeId: number; token: number } | null>(null);

    // Sincronizare cu butoanele back/forward din browser (URL bookmark-abil).
    useEffect(() => {
        const onPop = () => setActiveTab(readInitialTab());
        window.addEventListener('popstate', onPop);

        return () => window.removeEventListener('popstate', onPop);
    }, []);

    // Intenția de secțiune din URL: derulează la blocul-țintă din tabul Situație (ancorele
    // `sectiune-*`). Tabelele de note/absențe sosesc DEFERRED și cresc layout-ul de deasupra
    // țintei — de aceea poziția se re-ajustează la fiecare sosire de date, iar intenția se
    // consumă abia când blocurile dinaintea țintei sunt stabile. Navigarea manuală pe taburi
    // nu re-derulează (changeTab șterge parametrul, iar intenția e consumată).
    const pendingSection = useRef<string | null>(readSectionParam());

    useEffect(() => {
        if (activeTab !== 'situation' || pendingSection.current === null) {
            return;
        }

        const target = document.getElementById(`sectiune-${pendingSection.current}`);

        if (!target) {
            pendingSection.current = null;

            return;
        }

        // Ancorele de sub „Note" se mută doar când sosesc notele și absențele (secțiunile de
        // deasupra lor) — după ambele, poziția e definitivă și intenția s-a consumat.
        if (props.gradebook !== undefined && props.absenceOverview !== undefined) {
            pendingSection.current = null;
        }

        // După paint — altfel ținta încă nu are layout-ul nou. Fără `smooth`: re-ajustările
        // succesive ar întrerupe animația și ar lăsa viewportul între secțiuni.
        const frame = requestAnimationFrame(() => target.scrollIntoView({ block: 'start' }));

        return () => cancelAnimationFrame(frame);
    }, [activeTab, props.gradebook, props.absenceOverview]);

    function changeTab(next: string) {
        const tab = (VALID_TABS as readonly string[]).includes(next) ? (next as TabValue) : 'overview';
        setActiveTab(tab);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        // Intenția de secțiune era doar pentru aterizare — la navigarea manuală dispare din URL.
        url.searchParams.delete('sectiune');
        // replaceState: nu poluează istoria browserului cu fiecare click pe tab.
        window.history.replaceState({}, '', url);
    }

    function startContestation(gradeId: number) {
        setContestIntent((prev) => ({ gradeId, token: (prev?.token ?? 0) + 1 }));
        changeTab('requests');
    }

    /** Salt în pagină din tile-urile Prezentării/antetului: tabul Situație + scroll la secțiunea-
        țintă (refolosește intenția `pendingSection` — efectul de derulare rulează la schimbarea
        tabului). Deja în Situație? Derulăm direct — starea nu se schimbă, efectul n-ar re-rula. */
    function openSituationSection(section: (typeof VALID_SECTIONS)[number]) {
        if (activeTab === 'situation') {
            document.getElementById(`sectiune-${section}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });

            return;
        }

        pendingSection.current = section;
        changeTab('situation');
    }

    const tabs: TabItem[] = [
        { value: 'overview', label: t('cabinet.tab_overview'), icon: LayoutDashboard },
        { value: 'situation', label: t('cabinet.tab_situation'), icon: ClipboardList },
        { value: 'schedule', label: t('cabinet.tab_schedule'), icon: BookOpen },
        { value: 'history', label: t('cabinet.tab_history'), icon: History },
        { value: 'requests', label: t('cabinet.tab_requests'), icon: FileText },
    ];

    return (
        <>
            <Head title={props.student.name} />
            <div className="flex flex-col gap-6 p-4">
                {props.student.departedOn && (
                    <div className="rounded-lg border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200" role="status">
                        {t('cabinet.student_departed').replace('{date}', props.student.departedOn)}
                    </div>
                )}
                <ProfileHeader
                    student={props.student}
                    status={props.status}
                    totals={{
                        absencesTotal: props.absencesTotal,
                        absencesMotivated: props.absencesMotivated,
                        absencesUnmotivated: props.absencesUnmotivated,
                    }}
                    siblings={props.siblings}
                    isFamily={props.canRequestMotivation}
                    onOpenSection={openSituationSection}
                />

                <TabBar items={tabs} active={activeTab} onChange={changeTab} ariaLabel={props.student.name} />

                <TabPanel value="overview" active={activeTab}>
                    <OverviewTab
                        studentId={props.student.id}
                        studentAverage={props.student.average}
                        absencesTotal={props.absencesTotal}
                        absencesMotivated={props.absencesMotivated}
                        absencesUnmotivated={props.absencesUnmotivated}
                        status={props.status}
                        statusAck={props.statusAck}
                        deferralRisk={props.deferralRisk}
                        dynamics={props.dynamics}
                        // „Revizuiește notele" din confirmarea de statut aterizează fix pe note.
                        onShowDetails={() => openSituationSection('note')}
                        onOpenSection={openSituationSection}
                    />
                </TabPanel>

                <TabPanel value="situation" active={activeTab}>
                    <SituationTab
                        studentId={props.student.id}
                        gradebook={props.gradebook}
                        absenceOverview={props.absenceOverview}
                        absencesMotivated={props.absencesMotivated}
                        absencesUnmotivated={props.absencesUnmotivated}
                        motivations={props.motivations}
                        motivationWindow={props.motivationWindow}
                        canRequestMotivation={props.canRequestMotivation}
                        onContestGrade={props.canRequestMotivation ? startContestation : undefined}
                    />
                </TabPanel>

                <TabPanel value="schedule" active={activeTab}>
                    <ScheduleTab weekly={props.weekly} homework={props.homework} />
                </TabPanel>

                <TabPanel value="history" active={activeTab}>
                    <HistoryTab transcript={props.transcript} dynamics={props.dynamics} />
                </TabPanel>

                <TabPanel value="requests" active={activeTab}>
                    <RequestsTab
                        studentId={props.student.id}
                        documentRequests={props.documentRequests}
                        documentRequestsTotal={props.documentRequestsTotal}
                        corigentaExams={props.corigentaExams}
                        requestTypes={props.requestTypes}
                        canRequestMotivation={props.canRequestMotivation}
                        contestableGrades={props.contestableGrades}
                        contestIntent={contestIntent}
                    />
                </TabPanel>
            </div>
        </>
    );
}

StudentProfile.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'cabinet.profile', href: '#' },
    ],
};
