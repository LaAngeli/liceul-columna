import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, CalendarX, GraduationCap, TrendingUp } from 'lucide-react';
import { dashboard } from '@/routes';

interface StudentSummary {
    id: number;
    name: string;
    class: string | null;
    grades_count: number;
    absences_count: number;
    average: number | null;
}

interface DashboardProps {
    cabinet: {
        children: StudentSummary[];
        self: StudentSummary | null;
    };
}

function StudentCard({ student }: { student: StudentSummary }) {
    return (
        <Link
            href={`/cabinet/elev/${student.id}`}
            className="group flex flex-col rounded-xl border border-sidebar-border/70 bg-card p-5 transition-colors hover:border-primary dark:border-sidebar-border"
        >
            <div className="flex items-center gap-3">
                <span className="flex size-12 items-center justify-center rounded-full bg-primary/10 text-lg font-semibold text-primary">
                    {student.name.charAt(0)}
                </span>
                <div className="min-w-0">
                    <h3 className="truncate font-semibold">{student.name}</h3>
                    <p className="text-sm text-muted-foreground">{student.class ?? 'Clasă nealocată'}</p>
                </div>
                <ArrowUpRight className="ml-auto size-5 text-muted-foreground group-hover:text-primary" />
            </div>
            <div className="mt-4 grid grid-cols-3 gap-2 text-center">
                <div className="rounded-md bg-muted/50 p-2">
                    <div className="flex items-center justify-center gap-1 text-sm font-semibold">
                        <TrendingUp className="size-3.5" /> {student.average ?? '—'}
                    </div>
                    <p className="text-xs text-muted-foreground">Media</p>
                </div>
                <div className="rounded-md bg-muted/50 p-2">
                    <div className="text-sm font-semibold">{student.grades_count}</div>
                    <p className="text-xs text-muted-foreground">Note</p>
                </div>
                <div className="rounded-md bg-muted/50 p-2">
                    <div className="flex items-center justify-center gap-1 text-sm font-semibold">
                        <CalendarX className="size-3.5" /> {student.absences_count}
                    </div>
                    <p className="text-xs text-muted-foreground">Absențe</p>
                </div>
            </div>
        </Link>
    );
}

export default function Dashboard({ cabinet }: DashboardProps) {
    const { auth } = usePage().props;
    const hasCabinet = cabinet.children.length > 0 || cabinet.self;

    return (
        <>
            <Head title="Cabinet" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Bun venit, {auth.user.name}</h1>
                    <p className="text-sm text-muted-foreground">Cabinetul tău personal — Liceul Columna.</p>
                </div>

                {cabinet.self && (
                    <section>
                        <h2 className="mb-3 text-lg font-semibold">Profilul meu</h2>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <StudentCard student={cabinet.self} />
                        </div>
                    </section>
                )}

                {cabinet.children.length > 0 && (
                    <section>
                        <h2 className="mb-3 text-lg font-semibold">Copiii mei</h2>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {cabinet.children.map((child) => (
                                <StudentCard key={child.id} student={child} />
                            ))}
                        </div>
                    </section>
                )}

                {!hasCabinet && (
                    <div className="flex flex-1 items-center justify-center rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center text-muted-foreground dark:border-sidebar-border">
                        <div className="flex flex-col items-center gap-2">
                            <GraduationCap className="size-8" />
                            <p>Niciun profil asociat încă. Contactează administrația.</p>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Cabinet',
            href: dashboard(),
        },
    ],
};
