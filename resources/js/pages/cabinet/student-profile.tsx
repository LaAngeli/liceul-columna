import { Head } from '@inertiajs/react';
import { dashboard } from '@/routes';

interface StudentSummary {
    id: number;
    name: string;
    class: string | null;
    grades_count: number;
    absences_count: number;
    average: number | null;
}

interface GradeItem {
    value: string | null;
    calificativ: string | null;
    date: string | null;
    term: number | null;
}

interface SubjectGrades {
    subject: string;
    average: number | null;
    items: GradeItem[];
}

interface Props {
    student: StudentSummary;
    subjects: SubjectGrades[];
    absencesBySubject: { subject: string; count: number }[];
    absencesTotal: number;
}

function gradeLabel(item: GradeItem): string {
    if (item.value !== null) {
        return String(Number(item.value));
    }
    return item.calificativ ?? '—';
}

export default function StudentProfile({ student, subjects, absencesBySubject, absencesTotal }: Props) {
    return (
        <>
            <Head title={student.name} />
            <div className="flex flex-col gap-6 p-4">
                {/* Antet */}
                <div className="flex flex-wrap items-center gap-4 rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <span className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-2xl font-semibold text-primary">
                        {student.name.charAt(0)}
                    </span>
                    <div>
                        <h1 className="text-xl font-semibold">{student.name}</h1>
                        <p className="text-sm text-muted-foreground">{student.class ?? 'Clasă nealocată'}</p>
                    </div>
                    <div className="ml-auto flex gap-6 text-center">
                        <div>
                            <div className="text-2xl font-semibold text-primary">{student.average ?? '—'}</div>
                            <p className="text-xs text-muted-foreground">Media generală</p>
                        </div>
                        <div>
                            <div className="text-2xl font-semibold">{student.grades_count}</div>
                            <p className="text-xs text-muted-foreground">Note</p>
                        </div>
                        <div>
                            <div className="text-2xl font-semibold">{absencesTotal}</div>
                            <p className="text-xs text-muted-foreground">Absențe</p>
                        </div>
                    </div>
                </div>

                {/* Note pe discipline */}
                <section>
                    <h2 className="mb-3 text-lg font-semibold">Note pe discipline</h2>
                    <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">Disciplina</th>
                                    <th className="px-4 py-2 font-medium">Note</th>
                                    <th className="px-4 py-2 text-right font-medium">Media</th>
                                </tr>
                            </thead>
                            <tbody>
                                {subjects.map((s) => (
                                    <tr key={s.subject} className="border-t border-sidebar-border/70 dark:border-sidebar-border">
                                        <td className="px-4 py-3 font-medium">{s.subject}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1.5">
                                                {s.items.map((item, i) => (
                                                    <span
                                                        key={i}
                                                        className="inline-flex min-w-7 items-center justify-center rounded-md bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary"
                                                        title={item.date ?? undefined}
                                                    >
                                                        {gradeLabel(item)}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right font-semibold">{s.average ?? '—'}</td>
                                    </tr>
                                ))}
                                {subjects.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="px-4 py-6 text-center text-muted-foreground">
                                            Nicio notă înregistrată.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {/* Absențe */}
                {absencesBySubject.length > 0 && (
                    <section>
                        <h2 className="mb-3 text-lg font-semibold">Absențe pe discipline</h2>
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {absencesBySubject.map((a) => (
                                <div
                                    key={a.subject}
                                    className="flex items-center justify-between rounded-lg border border-sidebar-border/70 bg-card px-4 py-2 dark:border-sidebar-border"
                                >
                                    <span className="truncate text-sm">{a.subject}</span>
                                    <span className="ml-2 rounded-md bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive">{a.count}</span>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

StudentProfile.layout = {
    breadcrumbs: [
        { title: 'Cabinet', href: dashboard() },
        { title: 'Profil elev', href: '#' },
    ],
};
