import { Head } from '@inertiajs/react';
import { BookOpen, ClipboardList, FileText, History, LayoutDashboard } from 'lucide-react';
import { useEffect, useState } from 'react';
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
    canAcknowledge: boolean;
}

interface GradeItem {
    value: string | null;
    calificativ: string | null;
    date: string | null;
    term: number | null;
    type?: string;
    typeLabel?: string;
    isSummative?: boolean;
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

    // Defer (sosesc progresiv într-un al 2-lea request după mount)
    subjects?: { subject: string; average: number | null; mc?: number | null; summative?: number | null; items: GradeItem[] }[];
    absencesBySubject?: { subject: string; count: number }[];
    transcript?: { grade_level: number; subjects: { subject: string; sem1: string | null; sem2: string | null; annual: string | null }[] }[];
    homework?: {
        id: number;
        date: string;
        subject: string;
        topic: string | null;
        required: string | null;
        optional: string | null;
        links: string[];
    }[];
    dynamics?: Dynamics;
    timetable?: {
        days: { value: number; label: string; short: string }[];
        maxLesson: number;
        grid: Record<string, { subject: string; teacher: string | null; room: string | null }>;
    } | null;
    lessonsSchedule?: {
        rows: { lesson: number; start: string | null; end: string | null }[];
    } | null;
    deferralRisk?: { subject: string; absences: number; scheduled: number }[];
    motivations?: {
        id: number;
        reason: string;
        period: string;
        status: 'pending' | 'approved' | 'rejected';
        statusLabel: string;
        isException: boolean;
        documentUrl: string | null;
    }[];
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
    }[];
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

export default function StudentProfile(props: Props) {
    const t = useTranslations();
    const [activeTab, setActiveTab] = useState<TabValue>(readInitialTab);

    // Sincronizare cu butoanele back/forward din browser (URL bookmark-abil).
    useEffect(() => {
        const onPop = () => setActiveTab(readInitialTab());
        window.addEventListener('popstate', onPop);

        return () => window.removeEventListener('popstate', onPop);
    }, []);

    function changeTab(next: string) {
        const tab = (VALID_TABS as readonly string[]).includes(next) ? (next as TabValue) : 'overview';
        setActiveTab(tab);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        // replaceState: nu poluează istoria browserului cu fiecare click pe tab.
        window.history.replaceState({}, '', url);
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
                <ProfileHeader
                    student={props.student}
                    status={props.status}
                    totals={{
                        absencesTotal: props.absencesTotal,
                        absencesMotivated: props.absencesMotivated,
                        absencesUnmotivated: props.absencesUnmotivated,
                    }}
                    siblings={props.siblings}
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
                        onShowDetails={() => changeTab('situation')}
                    />
                </TabPanel>

                <TabPanel value="situation" active={activeTab}>
                    <SituationTab
                        studentId={props.student.id}
                        subjects={props.subjects}
                        absencesBySubject={props.absencesBySubject}
                        absencesMotivated={props.absencesMotivated}
                        absencesUnmotivated={props.absencesUnmotivated}
                        motivations={props.motivations}
                        canRequestMotivation={props.canRequestMotivation}
                    />
                </TabPanel>

                <TabPanel value="schedule" active={activeTab}>
                    <ScheduleTab
                        timetable={props.timetable}
                        lessonsSchedule={props.lessonsSchedule}
                        homework={props.homework}
                    />
                </TabPanel>

                <TabPanel value="history" active={activeTab}>
                    <HistoryTab transcript={props.transcript} dynamics={props.dynamics} />
                </TabPanel>

                <TabPanel value="requests" active={activeTab}>
                    <RequestsTab
                        studentId={props.student.id}
                        documentRequests={props.documentRequests}
                        corigentaExams={props.corigentaExams}
                        requestTypes={props.requestTypes}
                        canRequestMotivation={props.canRequestMotivation}
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
