import { Head } from '@inertiajs/react';
import { LocaleLink } from '@/components/locale-link';
import { PageBanner } from '@/components/public/page-banner';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/lib/i18n';

interface Member {
    name: string;
    role: string;
    slug?: string | null;
    photo?: string | null;
}

interface Group {
    title: string;
    members: Member[];
}

export default function Personal({ groups }: { groups: Group[] }) {
    const getInitials = useInitials();
    const t = useTranslations();
    const staffTitle = t('nav.staff', 'Personal');

    return (
        <>
            <Head title={staffTitle} />

            <PageBanner
                title={staffTitle}
                breadcrumbs={[{ title: staffTitle }]}
                description={t('staff.description', 'Echipa didactică și administrativă a Liceului „Columna” — oameni dedicați educației elevilor noștri.')}
            />

            <section className="mx-auto max-w-7xl px-6 py-12">
                <div className="space-y-12">
                    {groups.map((group) => (
                        <div key={group.title}>
                            <h2 className="font-serif text-2xl font-bold tracking-tight">{group.title}</h2>
                            <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {group.members.map((member) => {
                                    const inner = (
                                        <>
                                            {member.photo ? (
                                                <img
                                                    src={member.photo}
                                                    alt={member.name}
                                                    loading="lazy"
                                                    className="size-14 shrink-0 rounded-full object-cover"
                                                />
                                            ) : (
                                                <span className="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary">
                                                    {getInitials(member.name)}
                                                </span>
                                            )}
                                            <span className="min-w-0">
                                                <span className="block leading-tight font-medium">{member.name}</span>
                                                <span className="mt-1 block text-sm text-muted-foreground">{member.role}</span>
                                            </span>
                                        </>
                                    );

                                    return member.slug ? (
                                        <LocaleLink
                                            key={member.name}
                                            href={`/${member.slug}`}
                                            className="group flex items-center gap-4 rounded-lg border border-border bg-card p-4 transition-colors hover:border-primary"
                                        >
                                            {inner}
                                        </LocaleLink>
                                    ) : (
                                        <div key={member.name} className="flex items-center gap-4 rounded-lg border border-border bg-card p-4">
                                            {inner}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>
            </section>
        </>
    );
}
