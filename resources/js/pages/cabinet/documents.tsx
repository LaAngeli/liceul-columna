import { Head } from '@inertiajs/react';
import { Download, FileBarChart, FileText, FolderOpen, GraduationCap, Inbox, Layers } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/cabinet/empty-state';
import { TabBar, TabPanel, type TabItem } from '@/components/cabinet/tab-bar';
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
    note: string | null;
}

interface Child {
    id: number;
    name: string;
    class: string | null;
    generated: GeneratedDoc[];
    requests: RequestDoc[];
    // Totalul real de pe server — `requests` e plafonată la cele mai recente 15.
    requestsTotal: number;
}

interface Category {
    key: string;
    label: string;
}

interface Props {
    categories: Category[];
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

export default function DocumentsPage({ categories, schoolDocuments, children }: Props) {
    const t = useTranslations();

    // Documentele statice ale școlii, indexate pe categorie pentru filtrarea per-tab.
    const schoolByCategory: Record<string, SchoolDoc[]> = {};
    for (const group of schoolDocuments) {
        schoolByCategory[group.category] = group.items;
    }

    // Specificul cabinetului: generatele copilului aparțin categoriei „Rapoarte", cererile depuse
    // categoriei „Cereri". Restul categoriilor conțin doar documentele școlii.
    const childCountFor = (key: string): number => {
        if (key === 'reports') {
            return children.reduce((total, child) => total + child.generated.length, 0);
        }
        if (key === 'requests') {
            return children.reduce((total, child) => total + child.requestsTotal, 0);
        }
        return 0;
    };
    const countFor = (key: string): number => (schoolByCategory[key]?.length ?? 0) + childCountFor(key);

    const tabs: TabItem[] = categories.map((category) => ({
        value: category.key,
        label: category.label,
        icon: CATEGORY_ICONS[category.key] ?? FileText,
        // Badge ca string ⇒ se afișează inclusiv „0" pentru categoriile goale (cerință: taburile
        // goale indică zero, nu dispar — cum se întâmpla înainte cu grupurile fără documente).
        badge: String(countFor(category.key)),
    }));

    const [active, setActive] = useState<string>(categories[0]?.key ?? 'reports');

    // Antet de copil — afișat doar când familia are mai mulți copii (altfel e redundant).
    const showChildHeader = children.length > 1;

    const childHeader = (child: Child) =>
        showChildHeader ? (
            <div className="flex items-baseline gap-2">
                <h2 className="text-base font-semibold text-primary">{child.name}</h2>
                {child.class && <span className="text-sm text-muted-foreground">{child.class}</span>}
            </div>
        ) : null;

    const generatedGrid = (child: Child) => (
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
    );

    const requestsList = (child: Child) => (
        <div className="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border">
            <ul className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                {child.requests.map((req) => (
                    <li key={req.id} className="px-4 py-2.5 text-sm">
                        <div className="flex items-center gap-3">
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
                        </div>
                        {/* Răspunsul secretariatului — aceeași informație ca în profilul elevului. */}
                        {req.note && (
                            <p className="mt-1.5 rounded-md bg-muted/50 px-2.5 py-1.5 text-xs text-muted-foreground">
                                <span className="font-medium">{t('cabinet.requests_note')}:</span> {req.note}
                            </p>
                        )}
                    </li>
                ))}
            </ul>
            {/* Lista e plafonată la cele mai recente 15 — anunțăm dacă sunt mai multe (#37). */}
            {child.requestsTotal > child.requests.length && (
                <p className="border-t border-sidebar-border/70 px-4 py-2 text-xs text-muted-foreground dark:border-sidebar-border">
                    {t('cabinet.documents_requests_truncated')
                        .replace('{shown}', String(child.requests.length))
                        .replace('{total}', String(child.requestsTotal))}
                </p>
            )}
        </div>
    );

    const schoolGrid = (items: SchoolDoc[]) => (
        <ul className="grid gap-2 sm:grid-cols-2">
            {items.map((doc) => {
                // Document publicat FĂRĂ fișier (url null) → card NON-interactiv, nu un <a href="#">
                // care ar deschide un tab duplicat al paginii (download stricat). (#37)
                const content = (
                    <>
                        <FileText className="mt-0.5 size-5 shrink-0 text-primary" aria-hidden="true" />
                        <div className="min-w-0 flex-1">
                            <p className="font-medium">{doc.title}</p>
                            {doc.description && <p className="mt-0.5 text-sm text-muted-foreground">{doc.description}</p>}
                            <p className="mt-1 flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                                {doc.version && <span>{doc.version}</span>}
                                {doc.size && <span>{doc.size}</span>}
                                {!doc.url && <span className="italic">{t('cabinet.documents_soon')}</span>}
                            </p>
                        </div>
                        {doc.url && <Download className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />}
                    </>
                );

                return (
                    <li key={doc.id}>
                        {doc.url ? (
                            <a
                                href={doc.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-start gap-3 rounded-xl border border-sidebar-border/70 bg-card px-4 py-3 transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 dark:border-sidebar-border"
                                aria-label={`${t('cabinet.documents_download')}: ${doc.title}`}
                            >
                                {content}
                            </a>
                        ) : (
                            <div className="flex items-start gap-3 rounded-xl border border-sidebar-border/70 bg-card px-4 py-3 opacity-70 dark:border-sidebar-border">
                                {content}
                            </div>
                        )}
                    </li>
                );
            })}
        </ul>
    );

    const schoolBlock = (items: SchoolDoc[]) =>
        items.length > 0 ? (
            <section className="flex flex-col gap-2">
                <h3 className="text-sm font-semibold text-muted-foreground">{t('cabinet.documents_school')}</h3>
                {schoolGrid(items)}
            </section>
        ) : null;

    const renderTab = (key: string) => {
        const school = schoolByCategory[key] ?? [];

        // „Rapoarte" — generatele fiecărui copil (foaie matricolă, situația) + rapoartele școlii.
        if (key === 'reports') {
            const childrenWithReports = children.filter((child) => child.generated.length > 0);

            if (childrenWithReports.length === 0 && school.length === 0) {
                return <EmptyState icon={FileBarChart} title={t('cabinet.documents_empty_tab')} />;
            }

            return (
                <div className="flex flex-col gap-6">
                    {childrenWithReports.map((child) => (
                        <section key={child.id} className="flex flex-col gap-3">
                            {childHeader(child)}
                            {generatedGrid(child)}
                        </section>
                    ))}
                    {schoolBlock(school)}
                </div>
            );
        }

        // „Cereri" — cererile depuse de fiecare copil + formularele de cerere ale școlii.
        if (key === 'requests') {
            const childrenWithRequests = children.filter((child) => child.requests.length > 0);

            if (childrenWithRequests.length === 0 && school.length === 0) {
                return <EmptyState icon={Inbox} title={t('cabinet.documents_empty_tab')} />;
            }

            return (
                <div className="flex flex-col gap-6">
                    {childrenWithRequests.map((child) => (
                        <section key={child.id} className="flex flex-col gap-3">
                            {childHeader(child)}
                            {requestsList(child)}
                        </section>
                    ))}
                    {schoolBlock(school)}
                </div>
            );
        }

        // „Înștiințări" / „Formulare" / „Utile" — doar documentele școlii din categorie.
        if (school.length === 0) {
            return <EmptyState icon={CATEGORY_ICONS[key] ?? FolderOpen} title={t('cabinet.documents_empty_tab')} />;
        }

        return schoolGrid(school);
    };

    return (
        <>
            <Head title={t('cabinet.documents_title')} />
            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold">{t('cabinet.documents_title')}</h1>

                <TabBar items={tabs} active={active} onChange={setActive} ariaLabel={t('cabinet.documents_tabs_aria')} />

                {categories.map((category) => (
                    <TabPanel key={category.key} value={category.key} active={active}>
                        {renderTab(category.key)}
                    </TabPanel>
                ))}
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
