import { Head } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { Container } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/lib/i18n';

export default function Teacher({ name, role, photo }: { name: string; role: string; slug: string; photo?: string | null }) {
    const getInitials = useInitials();
    const t = useTranslations();

    return (
        <>
            <Head title={name} />

            <PageBanner title={name} breadcrumbs={[{ title: t('nav.staff', 'Personal'), href: '/personal' }, { title: name }]} />

            <Container className="py-[clamp(2.5rem,6vw,5rem)]">
                <div className="flex max-w-4xl flex-col gap-8 sm:flex-row">
                    <div className="shrink-0">
                        {photo ? (
                            <div className="photo-frame size-36 overflow-hidden rounded-[14px] border keyline">
                                <img src={photo} alt={name} className="h-full w-full object-cover" />
                            </div>
                        ) : (
                            <span className="flex size-36 items-center justify-center rounded-[14px] border keyline bg-brand-navy/8 text-4xl font-bold text-brand-navy" style={{ fontFamily: 'var(--font-display)' }}>
                                {getInitials(name)}
                            </span>
                        )}
                    </div>
                    <div className="min-w-0 border-l-[5px] border-l-brand-navy pl-5">
                        <h2 className="heading-dynamic text-[clamp(1.375rem,3vw,1.875rem)] text-brand-navy">{name}</h2>
                        {role && <p className="eyebrow mt-2 text-brand-green">{role}</p>}
                        <div className="mt-6 max-w-[60ch] space-y-3 leading-relaxed text-brand-gray">
                            <p>{t('teacher.bio_soon', 'Biografia și activitatea didactică ale acestui cadru vor fi disponibile în curând.')}</p>
                        </div>
                        <LocaleLink href="/personal" className="mt-8 inline-flex min-h-11 items-center gap-1.5 font-semibold text-brand-navy underline decoration-brand-green decoration-2 underline-offset-4">
                            <ArrowLeft className="size-4" /> {t('teacher.back', 'Înapoi la Personal')}
                        </LocaleLink>
                    </div>
                </div>
            </Container>
        </>
    );
}
