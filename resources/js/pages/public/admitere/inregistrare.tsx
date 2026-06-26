import { Form, Head } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { PageBanner } from '@/components/public/page-banner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/lib/i18n';

function Field({
    label,
    name,
    type = 'text',
    required = false,
    error,
}: {
    label: string;
    name: string;
    type?: string;
    required?: boolean;
    error?: string;
}) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={name}>
                {label}
                {required && ' *'}
            </Label>
            <Input id={name} name={name} type={type} required={required} />
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

            <section className="mx-auto max-w-2xl px-6 py-12">
                <Form action="/inregistrarea-student" method="post" resetOnSuccess className="space-y-5">
                    {({ errors, processing, wasSuccessful }) =>
                        wasSuccessful ? (
                            <div className="flex items-start gap-3 rounded-lg border border-brand-green/40 bg-brand-green/10 p-6">
                                <CheckCircle2 className="mt-0.5 size-6 shrink-0 text-brand-green" />
                                <div>
                                    <p className="font-serif text-lg font-semibold">{t('admission.thanks_title')}</p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {t('admission.thanks_body')}
                                    </p>
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
                                    <Label htmlFor="preferred_time">{t('admission.preferred_time')}</Label>
                                    <textarea
                                        id="preferred_time"
                                        name="preferred_time"
                                        rows={3}
                                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40"
                                    />
                                    {errors.preferred_time && <p className="text-sm text-destructive">{errors.preferred_time}</p>}
                                </div>
                                <Button type="submit" size="lg" disabled={processing}>
                                    {processing ? t('admission.submitting') : t('admission.submit')}
                                </Button>
                                <p className="text-xs text-muted-foreground">{t('admission.required_note')}</p>
                            </>
                        )
                    }
                </Form>
            </section>
        </>
    );
}
