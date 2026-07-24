import { router } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { EmptyState } from '@/components/cabinet/empty-state';
import { TabBar } from '@/components/cabinet/tab-bar';
import type { TabItem } from '@/components/cabinet/tab-bar';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export interface ModuleStudent {
    id: number;
    name: string;
    classLabel: string | null;
}

export interface ModuleContext {
    students: ModuleStudent[];
    currentId: number | null;
    section: string;
}

/**
 * Scheletul COMUN al modulelor de catalog (Note / Absențe / Orar / Teme): antet uniform,
 * comutator de copil (părinte cu mai mulți copii) și subsecțiuni adresabile prin `?sectiune=`
 * (aceleași ținte ca sub-linkurile din sidebar). Navigarea = vizită Inertia pe aceeași rută cu
 * `copil` + `sectiune` — serverul întoarce DOAR datele secțiunii active.
 */
export function ModuleShell({
    url,
    title,
    hint,
    module,
    sections = [],
    children,
}: {
    /** Ruta modulului (ex. `/cabinet/note`) — ținta navigării copil/secțiune. */
    url: string;
    title: string;
    hint?: string;
    module: ModuleContext;
    sections?: TabItem[];
    children: React.ReactNode;
}) {
    const t = useTranslations();
    const getInitials = useInitials();

    function visit(params: { copil?: number; sectiune?: string }) {
        router.get(
            url,
            {
                // Copilul rămâne selectat la schimbarea secțiunii (și invers).
                copil: params.copil ?? module.currentId ?? undefined,
                ...(sections.length > 0 ? { sectiune: params.sectiune ?? module.section } : {}),
            },
            { preserveScroll: true, preserveState: true },
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4">
            <header>
                <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                {hint && <p className="mt-0.5 text-sm text-muted-foreground">{hint}</p>}
            </header>

            {module.students.length === 0 ? (
                <EmptyState icon={GraduationCap} title={t('dashboard.no_profile')} className="flex-1" />
            ) : (
                <>
                    {/* Comutatorul de copil — doar pentru părintele cu mai mulți copii.
                        Până la 3 copii: UN SINGUR rând, fără scroll și fără înfășurare — pastilele se
                        MICȘOREAZĂ cât e nevoie (eticheta se trunchiază pe ecran îngust, avatarul rămâne),
                        deci aceeași dispunere pe orice dispozitiv. De la 4 în sus, unde micșorarea ar
                        face pastilele ilizibile, rândul devine scrollabil. */}
                    {module.students.length > 1 && (
                        <div
                            className={cn('flex gap-1.5', module.students.length > 3 && 'overflow-x-auto pb-1')}
                            role="group"
                            aria-label={t('cabinet.messages_child')}
                        >
                            {module.students.map((student) => {
                                const active = student.id === module.currentId;

                                return (
                                    <button
                                        key={student.id}
                                        type="button"
                                        onClick={() => visit({ copil: student.id })}
                                        aria-pressed={active}
                                        className={cn(
                                            // min-h-11: țintă tactilă ≥44px (regula proiectului).
                                            // min-w-0: permite pastilei să se micșoreze sub lățimea conținutului
                                            // (fără el, min-width:auto ar forța overflow/scroll).
                                            'inline-flex min-h-11 min-w-0 items-center gap-2 rounded-full border py-1.5 pr-3.5 pl-1.5 text-sm font-medium transition-colors',
                                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                            // La 4+ copii nu se micșorează — lățime naturală + scroll pe container.
                                            module.students.length > 3 && 'shrink-0',
                                            active
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : 'border-border text-muted-foreground hover:bg-muted hover:text-foreground',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'flex size-6 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold',
                                                active ? 'bg-primary text-primary-foreground' : 'bg-primary/10 text-primary',
                                            )}
                                        >
                                            {getInitials(student.name)}
                                        </span>
                                        {/* Prioritate la strângere: PRENUMELE rămâne mereu lizibil (`shrink-0`),
                                            iar eticheta de clasă e cea care se scurtează cu „…" (`truncate`) —
                                            numele copilului contează mai mult decât clasa când spațiul e mic. */}
                                        <span className="flex min-w-0 items-baseline">
                                            <span className="shrink-0">{student.name.split(' ')[0]}</span>
                                            {student.classLabel && (
                                                <span className={cn('ml-1 truncate text-xs', active ? 'text-primary/70' : 'text-muted-foreground/70')}>
                                                    · {student.classLabel}
                                                </span>
                                            )}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    )}

                    {/* Subsecțiunile modulului — aceleași ținte ca sub-linkurile din sidebar. */}
                    {sections.length > 0 && (
                        <TabBar
                            items={sections}
                            active={module.section}
                            onChange={(value) => visit({ sectiune: value })}
                            ariaLabel={title}
                        />
                    )}

                    {children}
                </>
            )}
        </div>
    );
}
