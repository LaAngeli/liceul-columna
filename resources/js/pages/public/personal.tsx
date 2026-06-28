import { Head } from '@inertiajs/react';
import { LocaleLink } from '@/components/locale-link';
import { Container, FourStar, Reveal } from '@/components/public/brand';
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

            <Container className="py-[clamp(2.5rem,6vw,5rem)]">
                <div className="space-y-14">
                    {groups.map((group, gi) => (
                        <div key={group.title}>
                            <div className="flex items-center gap-3">
                                <span className="eyebrow text-brand-navy">{String(gi + 1).padStart(2, '0')}</span>
                                <FourStar className="size-3 text-brand-green" />
                                <h2 className="display text-[clamp(1.375rem,2.6vw,1.875rem)] text-brand-navy">{group.title}</h2>
                            </div>
                            <span className="mt-3 block h-px w-full keyline border-t" aria-hidden="true" />
                            <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {group.members.map((member) => {
                                    const inner = (
                                        <>
                                            {member.photo ? (
                                                <img src={member.photo} alt={member.name} loading="lazy" className="size-14 shrink-0 rounded-full border keyline object-cover" />
                                            ) : (
                                                <span className="flex size-14 shrink-0 items-center justify-center rounded-full border keyline bg-brand-navy/8 font-semibold text-brand-navy">
                                                    {getInitials(member.name)}
                                                </span>
                                            )}
                                            <span className="min-w-0">
                                                <span className="heading-dynamic block text-[1.0625rem] leading-tight text-brand-navy">{member.name}</span>
                                                <span className="mt-1 block text-sm text-brand-gray">{member.role}</span>
                                            </span>
                                        </>
                                    );
                                    const base = 'flex items-center gap-4 rounded-[12px] border keyline border-l-[5px] border-l-brand-navy bg-card p-4';
                                    return (
                                        <Reveal key={member.name} as="article">
                                            {member.slug ? (
                                                <LocaleLink href={`/${member.slug}`} className={`${base} group transition-all hover:-translate-y-0.5 hover:border-l-brand-green`}>
                                                    {inner}
                                                </LocaleLink>
                                            ) : (
                                                <div className={base}>{inner}</div>
                                            )}
                                        </Reveal>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>
            </Container>
        </>
    );
}
