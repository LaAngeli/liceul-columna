import { Form, Head } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { BrandButton, Container, FourStar } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

const INPUT =
    'w-full rounded-[12px] border keyline bg-background px-3.5 min-h-11 text-base text-brand-dark outline-none transition-colors focus-visible:border-brand-navy focus-visible:ring-2 focus-visible:ring-brand-navy/25';

function Field({ label, name, type = 'text', required = false, error }: { label: string; name: string; type?: string; required?: boolean; error?: string }) {
    return (
        <div className="space-y-1.5">
            <label htmlFor={name} className="block text-sm font-semibold text-brand-navy">
                {label}
                {required && <span className="text-brand-green"> *</span>}
            </label>
            <input id={name} name={name} type={type} required={required} className={INPUT} />
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

export default function Inregistrare() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('admission.title')} />

            <PageBanner
                title={t('admission.title')}
                breadcrumbs={[{ title: t('nav.admission', 'Admitere'), href: '/admitere' }, { title: t('admission.crumb') }]}
                description={t('admission.description')}
            />

            <Container className="py-[clamp(2.5rem,6vw,5rem)]">
                <div className="mx-auto max-w-2xl">
                    {/* Indicator de pas (prietenos, nu formular oficial) */}
                    <div className="mb-7 rounded-[12px] border keyline border-l-[5px] border-l-brand-green bg-brand-navy/[0.03] p-4">
                        <div className="flex items-center gap-2">
                            <FourStar className="size-3 text-brand-green" />
                            <span className="eyebrow text-brand-navy">{t('home.step_progress', 'Pasul 1 din 3 · 100% online')}</span>
                        </div>
                        <div className="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-brand-navy/10" aria-hidden="true">
                            <span className="block h-full w-1/3 rounded-full bg-brand-green" />
                        </div>
                    </div>

                    <Form action="/inregistrarea-student" method="post" resetOnSuccess className="space-y-5">
                        {({ errors, processing, wasSuccessful }) =>
                            wasSuccessful ? (
                                <div className="flex items-start gap-3 rounded-[12px] border keyline border-l-[5px] border-l-brand-green bg-brand-green/10 p-6">
                                    <CheckCircle2 className="mt-0.5 size-6 shrink-0 text-brand-green" />
                                    <div>
                                        <p className="display text-lg text-brand-navy">{t('admission.thanks_title')}</p>
                                        <p className="mt-1 text-sm text-brand-gray">{t('admission.thanks_body')}</p>
                                    </div>
                                </div>
                            ) : (
                                <>
                                    <Field label={t('admission.parent_name')} name="parent_name" required error={errors.parent_name} />
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <Field label={t('admission.phone')} name="phone" type="tel" required error={errors.phone} />
                                        <Field label={t('admission.email')} name="email" type="email" error={errors.email} />
                                    </div>
                                    <Field label={t('admission.child_name')} name="child_name" required error={errors.child_name} />
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <Field label={t('admission.child_age')} name="child_age" type="number" error={errors.child_age} />
                                        <Field label={t('admission.desired_class')} name="desired_class" error={errors.desired_class} />
                                    </div>
                                    <div className="space-y-1.5">
                                        <label htmlFor="preferred_time" className="block text-sm font-semibold text-brand-navy">
                                            {t('admission.preferred_time')}
                                        </label>
                                        <textarea id="preferred_time" name="preferred_time" rows={3} className={INPUT.replace('min-h-11', 'min-h-24') + ' py-2.5'} />
                                        {errors.preferred_time && <p className="text-sm text-destructive">{errors.preferred_time}</p>}
                                    </div>
                                    <BrandButton type="submit" variant="primary" disabled={processing} className="w-full sm:w-auto">
                                        {processing ? t('admission.submitting') : t('admission.submit')}
                                    </BrandButton>
                                    <p className="text-xs text-brand-gray">{t('admission.required_note')}</p>
                                </>
                            )
                        }
                    </Form>
                </div>
            </Container>
        </>
    );
}
