import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, CalendarDays, CheckCircle2, ShieldCheck } from 'lucide-react';
import type { FormEvent, KeyboardEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { LocaleLink } from '@/components/locale-link';
import type { AdmissionErrors, ContactChildData } from '@/components/public/admission-kit';
import {
    classOptions,
    Field,
    pushDataLayer,
    RecapRow,
    SelectField,
    Stepper,
    validateChild,
    validateContact,
} from '@/components/public/admission-kit';
import { BrandButton, Container, FourStar } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export default function Inregistrare() {
    const t = useTranslations();
    /* URL-ul curent păstrează prefixul de limbă (/ru/, /en/), ca SetPublicLocale să pună locale-ul
       corect pe POST → confirmarea de mulțumire ajunge în limba pe care a navigat utilizatorul. */
    const postUrl = usePage().url.split('?')[0];
    const { data, setData, errors, processing, wasSuccessful, post, reset } = useForm<ContactChildData>({
        parent_name: '',
        phone: '',
        email: '',
        child_name: '',
        child_age: '',
        desired_class: '',
    });

    const [step, setStep] = useState(1);
    const [localErrors, setLocalErrors] = useState<AdmissionErrors>({});
    const successRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (wasSuccessful) {
            successRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, [wasSuccessful]);

    const set = (name: keyof ContactChildData) => (value: string) => {
        setData((prev) => ({ ...prev, [name]: value }));

        if (localErrors[name]) {
            setLocalErrors((prev) => {
                const next = { ...prev };

                delete next[name];

                return next;
            });
        }
    };

    const err = (name: keyof ContactChildData): string | undefined => localErrors[name] ?? errors[name];

    function validateStep(s: number): AdmissionErrors {
        if (s === 1) {
            return validateContact(data, t);
        }

        if (s === 2) {
            return validateChild(data, t);
        }

        return {};
    }

    function goNext() {
        const e = validateStep(step);

        if (Object.keys(e).length > 0) {
            setLocalErrors(e);

            return;
        }

        setLocalErrors({});
        setStep((s) => Math.min(3, s + 1));
    }

    function goBack() {
        setLocalErrors({});
        setStep((s) => Math.max(1, s - 1));
    }

    function submitForm() {
        const e = { ...validateContact(data, t), ...validateChild(data, t) };

        if (Object.keys(e).length > 0) {
            setLocalErrors(e);
            setStep(e.parent_name || e.phone || e.email ? 1 : 2);

            return;
        }

        post(postUrl, {
            preserveScroll: true,
            onSuccess: () => {
                pushDataLayer({ event: 'enrollment_form_submit', form_id: 'enrollment_request', form_name: 'Cerere de înmatriculare' });
                reset();
                setStep(1);
                setLocalErrors({});
            },
        });
    }

    function handleKeyDown(event: KeyboardEvent<HTMLFormElement>) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();

        if (step < 3) {
            goNext();
        } else {
            submitForm();
        }
    }

    function preventFormSubmit(event: FormEvent) {
        event.preventDefault();
    }

    const STEPS = [
        { title: t('admission.step1_title', 'Date de contact'), hint: t('admission.step1_hint', 'Cum te putem contacta') },
        { title: t('admission.step2_title', 'Despre copil'), hint: t('admission.step2_hint', 'Câteva detalii despre viitorul elev') },
        { title: t('admission.review_title', 'Verificare și trimitere'), hint: t('admission.review_hint', 'Confirmă datele înainte de a trimite cererea') },
    ];
    const active = STEPS[step - 1];

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
                    {wasSuccessful ? (
                        <div
                            ref={successRef}
                            id="enrollment-success"
                            role="status"
                            aria-live="polite"
                            data-form-id="enrollment_request"
                            data-form-status="success"
                            className="flex items-start gap-3 rounded-[14px] border keyline border-l-[5px] border-l-brand-green bg-brand-green/10 p-6"
                        >
                            <CheckCircle2 className="mt-0.5 size-6 shrink-0 text-brand-green" />
                            <div>
                                <p className="display text-lg text-brand-navy">{t('admission.thanks_title')}</p>
                                <p className="mt-1 text-sm text-brand-gray">{t('admission.thanks_body')}</p>
                            </div>
                        </div>
                    ) : (
                        <>
                            {/* Antet wizard */}
                            <div className="mb-7 rounded-[14px] border keyline border-l-[5px] border-l-brand-green bg-brand-navy/[0.03] p-5">
                                <div className="flex items-center gap-2">
                                    <FourStar className="size-3 text-brand-green" />
                                    <span className="eyebrow text-brand-navy">
                                        {t('admission.wizard_step', 'Pasul')} {step} {t('admission.wizard_of', 'din')} 3 ·{' '}
                                        {t('admission.wizard_online', '100% online')}
                                    </span>
                                </div>
                                <Stepper step={step} />
                            </div>

                            <form id="enrollment-form" data-form-id="enrollment_request" onSubmit={preventFormSubmit} onKeyDown={handleKeyDown} className="space-y-5" noValidate>
                                <div>
                                    <h2 className="display text-xl text-brand-navy">{active.title}</h2>
                                    <p className="mt-1 text-sm text-brand-gray">{active.hint}</p>
                                </div>

                                {/* Pasul 1 — date de contact */}
                                {step === 1 && (
                                    <div className="space-y-5">
                                        <Field
                                            label={t('admission.parent_name')}
                                            name="parent_name"
                                            value={data.parent_name}
                                            onChange={set('parent_name')}
                                            required
                                            autoComplete="name"
                                            maxLength={120}
                                            error={err('parent_name')}
                                        />
                                        <div className="grid gap-5 sm:grid-cols-2">
                                            <Field
                                                label={t('admission.phone')}
                                                name="phone"
                                                type="tel"
                                                inputMode="tel"
                                                value={data.phone}
                                                onChange={set('phone')}
                                                required
                                                autoComplete="tel"
                                                pattern="[\d+()\s-]{7,20}"
                                                maxLength={20}
                                                error={err('phone')}
                                            />
                                            <Field
                                                label={t('admission.email')}
                                                name="email"
                                                type="email"
                                                inputMode="email"
                                                value={data.email}
                                                onChange={set('email')}
                                                autoComplete="email"
                                                maxLength={120}
                                                error={err('email')}
                                            />
                                        </div>
                                    </div>
                                )}

                                {/* Pasul 2 — despre copil */}
                                {step === 2 && (
                                    <div className="space-y-5">
                                        <Field
                                            label={t('admission.child_name')}
                                            name="child_name"
                                            value={data.child_name}
                                            onChange={set('child_name')}
                                            required
                                            maxLength={120}
                                            error={err('child_name')}
                                        />
                                        <div className="grid gap-5 sm:grid-cols-2">
                                            <Field
                                                label={t('admission.child_age')}
                                                name="child_age"
                                                type="number"
                                                inputMode="numeric"
                                                value={data.child_age}
                                                onChange={set('child_age')}
                                                min={3}
                                                max={20}
                                                error={err('child_age')}
                                            />
                                            <SelectField
                                                label={t('admission.desired_class')}
                                                name="desired_class"
                                                value={data.desired_class}
                                                onChange={set('desired_class')}
                                                placeholder={t('admission.class_placeholder', 'Selectează clasa')}
                                                options={classOptions(t)}
                                                error={err('desired_class')}
                                            />
                                        </div>
                                    </div>
                                )}

                                {/* Pasul 3 — verificare + trimitere */}
                                {step === 3 && (
                                    <div className="space-y-5">
                                        <div className="rounded-[12px] border keyline bg-brand-navy/[0.03] p-5">
                                            <p className="display text-base text-brand-navy">{t('admission.recap_title', 'Verifică datele')}</p>
                                            <dl className="mt-3 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                                                <RecapRow label={t('admission.parent_name')} value={data.parent_name} empty={t('admission.recap_empty', '—')} />
                                                <RecapRow label={t('admission.phone')} value={data.phone} empty={t('admission.recap_empty', '—')} />
                                                <RecapRow label={t('admission.email')} value={data.email} empty={t('admission.recap_empty', '—')} />
                                                <RecapRow label={t('admission.child_name')} value={data.child_name} empty={t('admission.recap_empty', '—')} />
                                                <RecapRow label={t('admission.child_age')} value={data.child_age} empty={t('admission.recap_empty', '—')} />
                                                <RecapRow label={t('admission.desired_class')} value={data.desired_class} empty={t('admission.recap_empty', '—')} />
                                            </dl>
                                        </div>

                                        {/* Cross-link: vrei întâi să vezi liceul? */}
                                        <LocaleLink
                                            href="/programeaza-vizita"
                                            className="group flex items-center gap-3 rounded-[12px] border keyline bg-card p-4 transition-colors hover:border-brand-green"
                                        >
                                            <span className="grid size-9 shrink-0 place-items-center rounded-full bg-brand-green/15 text-brand-green">
                                                <CalendarDays className="size-4" />
                                            </span>
                                            <span className="min-w-0 flex-1 text-sm text-brand-dark">{t('admission.visit_crosslink', 'Vrei întâi să cunoști liceul? Programează o vizită.')}</span>
                                            <ArrowRight className="size-4 shrink-0 text-brand-navy transition-transform group-hover:translate-x-0.5" />
                                        </LocaleLink>

                                        <p className="flex items-start gap-2 text-xs text-brand-gray">
                                            <ShieldCheck className="mt-0.5 size-4 shrink-0 text-brand-green" />
                                            {t('admission.privacy_note', 'Datele sunt folosite doar pentru a programa vizita și nu sunt partajate cu terți.')}
                                        </p>
                                    </div>
                                )}

                                {/* Navigare — UN singur slot de buton primar (acelaşi DOM element, props variate), ambele type="button" */}
                                <div className={cn('flex flex-col-reverse gap-3 pt-2 sm:flex-row', step === 1 ? 'sm:justify-end' : 'sm:justify-between')}>
                                    {step > 1 && (
                                        <BrandButton type="button" variant="ghost" onClick={goBack} className="w-full sm:w-auto">
                                            <ArrowLeft className="size-4 shrink-0" />
                                            {t('admission.back', 'Înapoi')}
                                        </BrandButton>
                                    )}
                                    <BrandButton
                                        type="button"
                                        variant="primary"
                                        icon={step < 3 ? ArrowRight : undefined}
                                        onClick={step < 3 ? goNext : submitForm}
                                        disabled={step === 3 && processing}
                                        className="w-full sm:w-auto"
                                    >
                                        {step < 3 ? t('admission.next', 'Continuă') : processing ? t('admission.submitting') : t('admission.submit_enroll', 'Trimite cererea')}
                                    </BrandButton>
                                </div>

                                <p className="text-xs text-brand-gray">{t('admission.required_note')}</p>
                            </form>
                        </>
                    )}
                </div>
            </Container>
        </>
    );
}
