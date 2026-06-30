import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, CheckCircle2, ShieldCheck } from 'lucide-react';
import type { FormEvent, KeyboardEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import type { AdmissionErrors, ContactChildData } from '@/components/public/admission-kit';
import {
    classOptions,
    Field,
    formatLongDate,
    pushDataLayer,
    RecapRow,
    SelectField,
    Stepper,
    toIsoSlot,
    validateChild,
    validateContact,
    VisitScheduler,
} from '@/components/public/admission-kit';
import { BrandButton, Container, FourStar } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useLocale, useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type VisitData = ContactChildData & { preferred_time: string };

export default function ProgramareVizita() {
    const t = useTranslations();
    const locale = useLocale();
    const { data, setData, errors, processing, wasSuccessful, post, reset } = useForm<VisitData>({
        parent_name: '',
        phone: '',
        email: '',
        child_name: '',
        child_age: '',
        desired_class: '',
        preferred_time: '',
    });

    const [step, setStep] = useState(1);
    const [localErrors, setLocalErrors] = useState<AdmissionErrors>({});
    const [visitDate, setVisitDate] = useState<Date | null>(null);
    const [visitTime, setVisitTime] = useState('');
    const [scheduleError, setScheduleError] = useState('');
    const successRef = useRef<HTMLDivElement>(null);

    /* Sincronizare: visitDate+visitTime → preferred_time (ISO compact, parsabil server-side cu Carbon). */
    useEffect(() => {
        const iso = visitDate && visitTime ? toIsoSlot(visitDate, visitTime) : '';

        setData((prev) => (prev.preferred_time === iso ? prev : { ...prev, preferred_time: iso }));
    }, [visitDate, visitTime, setData]);

    /* Handlere ale calendarului — curăță eroarea „alege data" la prima interacțiune. */
    function handleDateChange(d: Date | null) {
        setVisitDate(d);
        setScheduleError('');
    }

    function handleTimeChange(time: string) {
        setVisitTime(time);
        setScheduleError('');
    }

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

        if (!visitDate || !visitTime) {
            setScheduleError(t('admission.scheduler_required', 'Alege ziua și ora vizitei pentru a continua.'));

            return;
        }

        post('/programeaza-vizita', {
            preserveScroll: true,
            onSuccess: () => {
                pushDataLayer({ event: 'visit_form_submit', form_id: 'visit_request', form_name: 'Programare vizită' });
                reset();
                setVisitDate(null);
                setVisitTime('');
                setStep(1);
                setLocalErrors({});
                setScheduleError('');
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
        { title: t('visit.schedule_title', 'Alege data vizitei'), hint: t('visit.schedule_hint', 'Confirmăm programarea telefonic') },
    ];
    const active = STEPS[step - 1];
    const recapVisit = visitDate && visitTime ? `${formatLongDate(visitDate, locale)} · ${visitTime}` : '';

    return (
        <>
            <Head title={t('visit.title', 'Programează o vizită')} />

            <PageBanner
                title={t('visit.title', 'Programează o vizită')}
                breadcrumbs={[{ title: t('nav.admission', 'Admitere'), href: '/admitere' }, { title: t('visit.crumb', 'Programare vizită') }]}
                description={t('visit.description', 'Alege ziua și ora — te așteptăm să cunoști liceul. Confirmăm programarea telefonic.')}
            />

            <Container className="py-[clamp(2.5rem,6vw,5rem)]">
                <div className="mx-auto max-w-2xl">
                    {wasSuccessful ? (
                        <div
                            ref={successRef}
                            id="visit-success"
                            role="status"
                            aria-live="polite"
                            data-form-id="visit_request"
                            data-form-status="success"
                            className="flex items-start gap-3 rounded-[14px] border keyline border-l-[5px] border-l-brand-green bg-brand-green/10 p-6"
                        >
                            <CheckCircle2 className="mt-0.5 size-6 shrink-0 text-brand-green" />
                            <div>
                                <p className="display text-lg text-brand-navy">{t('visit.thanks_title', 'Mulțumim!')}</p>
                                <p className="mt-1 text-sm text-brand-gray">{t('visit.thanks_body', 'Programarea vizitei a fost trimisă. Te contactăm pentru confirmare.')}</p>
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

                            <form id="visit-form" data-form-id="visit_request" onSubmit={preventFormSubmit} onKeyDown={handleKeyDown} className="space-y-5" noValidate>
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

                                {/* Pasul 3 — programare (calendar + oră) + recapitulare */}
                                {step === 3 && (
                                    <div className="space-y-5">
                                        <div className="space-y-2">
                                            <p className="block text-sm font-semibold text-brand-navy">
                                                {t('admission.scheduler_label', 'Programează vizita la liceu')}
                                            </p>
                                            <p className="text-sm text-brand-gray">
                                                {t('admission.scheduler_hint', 'Programările se fac în zilele de lucru, între orele 9:00 și 17:00.')}
                                            </p>
                                            <VisitScheduler date={visitDate} onDateChange={handleDateChange} time={visitTime} onTimeChange={handleTimeChange} locale={locale} t={t} />
                                            {scheduleError && <p className="text-sm text-destructive">{scheduleError}</p>}
                                        </div>

                                        <div className="rounded-[12px] border keyline bg-brand-navy/[0.03] p-5">
                                            <p className="display text-base text-brand-navy">{t('admission.recap_title', 'Verifică datele')}</p>
                                            <dl className="mt-3 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                                                <RecapRow label={t('admission.parent_name')} value={data.parent_name} empty={t('admission.recap_empty', '—')} />
                                                <RecapRow label={t('admission.phone')} value={data.phone} empty={t('admission.recap_empty', '—')} />
                                                <RecapRow label={t('admission.child_name')} value={data.child_name} empty={t('admission.recap_empty', '—')} />
                                                <RecapRow label={t('admission.desired_class')} value={data.desired_class} empty={t('admission.recap_empty', '—')} />
                                                <div className="sm:col-span-2">
                                                    <RecapRow label={t('admission.scheduler_recap_label', 'Programare vizită')} value={recapVisit} empty={t('visit.recap_empty', 'Nicio dată aleasă încă')} />
                                                </div>
                                            </dl>
                                        </div>

                                        <p className="flex items-start gap-2 text-xs text-brand-gray">
                                            <ShieldCheck className="mt-0.5 size-4 shrink-0 text-brand-green" />
                                            {t('admission.privacy_note', 'Datele sunt folosite doar pentru a programa vizita și nu sunt partajate cu terți.')}
                                        </p>
                                    </div>
                                )}

                                {/* Navigare — UN singur slot de buton primar, ambele type="button" */}
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
                                        {step < 3 ? t('admission.next', 'Continuă') : processing ? t('admission.submitting') : t('visit.submit', 'Trimite programarea')}
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
