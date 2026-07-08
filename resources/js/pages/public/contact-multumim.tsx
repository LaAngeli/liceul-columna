import { Head } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, Home } from 'lucide-react';
import { Band, BrandButton, FourStar } from '@/components/public/brand';
import { useTranslations } from '@/lib/i18n';

export default function ContactMultumim({ name }: { name: string }) {
    const t = useTranslations();
    const heading = t('contact.thanks_heading', 'Mulțumim, :name!').replace(':name', name);

    return (
        <>
            <Head title={heading} />

            <Band variant="light" pattern="signature" className="!py-[clamp(4rem,10vw,8rem)]">
                <div className="mx-auto max-w-xl text-center">
                    <span className="mx-auto flex size-16 items-center justify-center rounded-full bg-brand-green/15 text-brand-green">
                        <CheckCircle2 className="size-9" />
                    </span>

                    <span className="eyebrow mt-6 inline-flex items-center justify-center gap-2 text-brand-navy">
                        <FourStar className="size-3 text-brand-green" /> {t('contact.heading', 'Contacte')}
                    </span>
                    <h1 className="display mt-2 text-[clamp(1.75rem,4vw,2.75rem)] text-brand-navy">{heading}</h1>
                    <p className="mt-4 text-[clamp(1.125rem,1.6vw,1.25rem)] leading-relaxed text-brand-gray">{t('contact.thanks_text')}</p>

                    <div className="mt-8 flex flex-wrap justify-center gap-3">
                        <BrandButton href="/" variant="ghost" icon={Home}>
                            {t('contact.thanks_home', 'Înapoi acasă')}
                        </BrandButton>
                        <BrandButton href="/admitere" variant="primary" icon={ArrowRight}>
                            {t('contact.visit_cta', 'Vezi pașii de admitere')}
                        </BrandButton>
                    </div>
                </div>
            </Band>
        </>
    );
}
