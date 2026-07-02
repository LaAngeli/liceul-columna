import { Head } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { Container, FourStar, Reveal } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

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

function MemberCard({ member, initials }: { member: Member; initials: string }) {
    const inner = (
        <>
            <span className="relative block size-24 shrink-0 overflow-hidden rounded-full border-2 keyline ring-2 ring-transparent transition-all group-hover:ring-brand-green">
                {member.photo ? (
                    <img src={member.photo} alt={member.name} loading="lazy" decoding="async" className="absolute inset-0 h-full w-full object-cover" />
                ) : (
                    <span className="display flex h-full w-full items-center justify-center bg-brand-navy/8 text-xl text-brand-navy">{initials}</span>
                )}
            </span>
            <span className="display mt-4 text-[1.0625rem] leading-tight text-balance text-brand-navy">{member.name}</span>
            <span className="mt-1 text-sm leading-snug text-balance text-brand-gray">{member.role}</span>
        </>
    );
    const base = 'group flex h-full flex-col items-center rounded-[16px] border keyline bg-card p-5 text-center';

    if (member.slug) {
        return (
            <LocaleLink href={`/${member.slug}`} className={cn(base, 'transition-all hover:-translate-y-1 hover:shadow-[0_18px_40px_-28px_rgba(15,77,119,0.5)]')}>
                {inner}
            </LocaleLink>
        );
    }

    return <div className={base}>{inner}</div>;
}

export default function Personal({ groups }: { groups: Group[] }) {
    const getInitials = useInitials();
    const t = useTranslations();
    const staffTitle = t('nav.staff', 'Personal');

    return (
        <>
            <Head title={staffTitle} />

            {/* HERO — standardizat (PageBanner): fundal alb + crest watermark */}
            <PageBanner title={staffTitle} breadcrumbs={[{ title: staffTitle }]} description={t('staff.description')} />

            {/* Jump-nav — sari direct la un grup (bandă navy) */}
            <nav className="on-navy border-y border-white/10 bg-surface-navy text-[color:var(--brand-navy-foreground)]" aria-label={t('staff.jump_label', 'Sari la:')}>
                <Container className="flex flex-wrap items-center gap-x-2 gap-y-2 py-3">
                    <span className="eyebrow mr-1 hidden text-white/70 sm:inline">{t('staff.jump_label', 'Sari la:')}</span>
                    {groups.map((group, gi) => (
                        <a
                            key={group.title}
                            href={`#grp-${gi}`}
                            className="inline-flex min-h-9 items-center gap-2 rounded-full border border-white/25 bg-white/[0.06] px-3.5 text-sm font-semibold text-[color:var(--brand-navy-foreground)] transition-colors hover:bg-white/15"
                        >
                            {group.title}
                            <span className="inline-flex items-center gap-1 opacity-80">
                                <Users className="size-3.5 text-brand-green" />
                                {group.members.length}
                            </span>
                        </a>
                    ))}
                </Container>
            </nav>

            <Container className="py-[clamp(2.5rem,6vw,5rem)]">
                <div className="space-y-16 sm:space-y-20">
                    {groups.map((group, gi) => (
                        <section key={group.title} id={`grp-${gi}`} className="scroll-mt-32">
                            <Reveal className="flex flex-wrap items-center gap-3">
                                <span className="eyebrow text-brand-navy">{String(gi + 1).padStart(2, '0')}</span>
                                <FourStar className="size-3 text-brand-green" />
                                <h2 className="display text-[clamp(1.375rem,2.6vw,1.875rem)] text-brand-navy">{group.title}</h2>
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-brand-navy/8 px-2.5 py-1 text-xs font-semibold text-brand-navy">
                                    <Users className="size-3.5 text-brand-green" />
                                    {group.members.length}
                                </span>
                            </Reveal>
                            <span className="mt-3 block h-px w-full keyline border-t" aria-hidden="true" />
                            <div className="mt-7 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                                {group.members.map((member) => (
                                    <Reveal as="div" key={member.name} className="h-full">
                                        <MemberCard member={member} initials={getInitials(member.name)} />
                                    </Reveal>
                                ))}
                            </div>
                        </section>
                    ))}
                </div>
            </Container>
        </>
    );
}
