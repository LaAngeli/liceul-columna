import { Head } from '@inertiajs/react';
import { Download, FileBarChart, FileText, FolderOpen, GraduationCap, Inbox, Layers } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { EmptyState } from '@/components/cabinet/empty-state';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';

interface SchoolDoc {
    id: number;
    title: string;
    description: string | null;
    version: string | null;
    size: string | null;
    url: string | null;
}

interface SchoolCategory {
    category: string;
    label: string;
    items: SchoolDoc[];
}

interface GeneratedDoc {
    key: string;
    label: string;
    description: string;
    url: string;
}

interface RequestDoc {
    id: number;
    type: string;
    date: string | null;
    statusLabel: string;
    url: string | null;
}

interface Child {
    id: number;
    name: string;
    class: string | null;
    generated: GeneratedDoc[];
    requests: RequestDoc[];
}

interface Props {
    schoolDocuments: SchoolCategory[];
    children: Child[];
}

// Iconiță per categorie de bibliotecă (oglinda DocumentCategory::icon()); fallback FileText.
const CATEGORY_ICONS: Record<string, LucideIcon> = {
    reports: FileBarChart,
    requests: Inbox,
    notices: FileText,
    forms: Layers,
    useful: FolderOpen,
};

// Iconiță per document generat (oglinda GeneratedDocumentType::icon()).
const GENERATED_ICONS: Record<string, LucideIcon> = {
    transcript: GraduationCap,
    term_situation: FileBarChart,
};

export default function DocumentsPage({ schoolDocuments, children }: Props) {
    const t = useTranslations();

    return (
        <>
            <Head title={t('cabinet.documents_title')} />
            <div className="flex flex-col gap-8 p-4">
                <h1 className="text-xl font-semibold">{t('cabinet.documents_title')}</h1>

                {/* Documentele copilului — generate la cerere + cereri depuse */}
                {children.map((child) => (
                    <section key={child.id} className="flex flex-col gap-4">
                        <div className="flex items-baseline gap-2">
                            <h2 className="text-lg font-semibold text-primary">{child.name}</h2>
                            {child.class && <span className="text-sm text-muted-foreground">{child.class}</span>}
                        </div>

                        {/* Documente generate (foaie matricolă, situația școlară) */}
                        <div className="grid gap-3 sm:grid-cols-2">
                            {child.generated.map((doc) => {
                                const Icon = GENERATED_ICONS[doc.key] ?? FileText;

                                return (
                                    <a
                                        key={doc.key}
                                        href={doc.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="group flex items-start gap-3 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 transition-colors hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">
                                            <Icon className="size-5" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium">{doc.label}</p>
                                            <p className="mt-0.5 text-sm text-muted-foreground">{doc.description}</p>
                                        </div>
                                        <span className="mt-0.5 inline-flex items-center gap-1 text-xs font-medium text-primary" aria-hidden="true">
                                            <Download className="size-4" /> PDF
                                        </span>
                                    </a>
                                );
                            })}
                        </div>

                        {/* Cererile depuse (cu PDF) */}
                        {child.requests.length > 0 && (
                            <div className="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
                                <p className="border-b border-sidebar-border/70 px-4 py-2 text-sm font-medium text-muted-foreground dark:border-sidebar-border">
                                    {t('cabinet.documents_requests')}
                                </p>
                                <ul className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                    {child.requests.map((req) => (
                                        <li key={req.id} className="flex items-center gap-3 px-4 py-2.5 text-sm">
                                            <Inbox className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                            <span className="min-w-0 flex-1 truncate">{req.type}</span>
                                            {req.date && <span className="shrink-0 text-xs text-muted-foreground">{req.date}</span>}
                                            <span className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs">{req.statusLabel}</span>
                                            {req.url && (
                                                <a
                                                    href={req.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="shrink-0 text-primary hover:underline"
                                                    aria-label={`${t('cabinet.documents_download')}: ${req.type}`}
                                                >
                                                    <Download className="size-4" />
                                                </a>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </section>
                ))}

                {/* Documentele școlii — statice, publice sau vizibile rolului familiei */}
                <section className="flex flex-col gap-4">
                    <div>
                        <h2 className="text-lg font-semibold">{t('cabinet.documents_school')}</h2>
                        <p className="text-sm text-muted-foreground">{t('cabinet.documents_school_hint')}</p>
                    </div>

                    {schoolDocuments.length === 0 ? (
                        <EmptyState icon={FolderOpen} title={t('cabinet.documents_empty_school')} />
                    ) : (
                        schoolDocuments.map((group) => {
                            const Icon = CATEGORY_ICONS[group.category] ?? FileText;

                            return (
                                <div key={group.category} className="flex flex-col gap-2">
                                    <h3 className="flex items-center gap-2 text-sm font-semibold text-muted-foreground">
                                        <Icon className="size-4" aria-hidden="true" /> {group.label}
                                    </h3>
                                    <ul className="grid gap-2 sm:grid-cols-2">
                                        {group.items.map((doc) => (
                                            <li key={doc.id}>
                                                <a
                                                    href={doc.url ?? '#'}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="flex items-start gap-3 rounded-xl border border-sidebar-border/70 bg-card px-4 py-3 transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 dark:border-sidebar-border"
                                                    aria-label={`${t('cabinet.documents_download')}: ${doc.title}`}
                                                >
                                                    <FileText className="mt-0.5 size-5 shrink-0 text-primary" aria-hidden="true" />
                                                    <div className="min-w-0 flex-1">
                                                        <p className="font-medium">{doc.title}</p>
                                                        {doc.description && <p className="mt-0.5 text-sm text-muted-foreground">{doc.description}</p>}
                                                        <p className="mt-1 flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                                                            {doc.version && <span>{doc.version}</span>}
                                                            {doc.size && <span>{doc.size}</span>}
                                                        </p>
                                                    </div>
                                                    <Download className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            );
                        })
                    )}
                </section>
            </div>
        </>
    );
}

DocumentsPage.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'cabinet.nav_documents', href: '#' },
    ],
};
