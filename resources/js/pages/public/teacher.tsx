import { Head } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
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

            <section className="mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-12">
                <div className="flex flex-col gap-8 sm:flex-row">
                    <div className="shrink-0">
                        {photo ? (
                            <img src={photo} alt={name} className="size-32 rounded-2xl object-cover" />
                        ) : (
                            <span className="flex size-32 items-center justify-center rounded-2xl bg-primary/10 text-4xl font-semibold text-primary">
                                {getInitials(name)}
                            </span>
                        )}
                    </div>
                    <div className="min-w-0">
                        <h2 className="font-serif text-xl font-bold tracking-tight sm:text-2xl">{name}</h2>
                        {role && <p className="mt-1 font-medium text-primary">{role}</p>}
                        <div className="mt-6 space-y-3 leading-relaxed text-muted-foreground">
                            <p>{t('teacher.bio_soon', 'Biografia și activitatea didactică ale acestui cadru vor fi disponibile în curând.')}</p>
                        </div>
                        <LocaleLink href="/personal" className="mt-8 inline-flex min-h-11 items-center gap-1.5 py-2 text-sm font-medium text-primary hover:underline">
                            <ArrowLeft className="size-4" /> {t('teacher.back', 'Înapoi la Personal')}
                        </LocaleLink>
                    </div>
                </div>
            </section>
        </>
    );
}
