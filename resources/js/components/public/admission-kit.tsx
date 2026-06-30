import { CalendarDays, Check, ChevronLeft, ChevronRight, Clock } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { cn } from '@/lib/utils';

/* ============================================================================
   Admission Kit — primitive + validatori + calendar, partajate de paginile
   /programeaza-vizita (vizită) și /inregistrarea-student (înmatriculare).
   ============================================================================ */

export type Tr = (k: string, f?: string) => string;

export type ContactChildData = {
    parent_name: string;
    phone: string;
    email: string;
    child_name: string;
    child_age: string;
    desired_class: string;
};

export type AdmissionErrors = Partial<Record<keyof ContactChildData | 'preferred_time', string>>;

/* Validatori — Unicode-aware (RO/RU/EN); telefon = digit-count + format permis; email standard. */
const NAME_RE = /^[\p{L}\s'.-]{2,}$/u;
const HAS_LETTER_RE = /\p{L}/u;
const PHONE_RE = /^[\d+()\s-]+$/;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export const ROMAN_CLASSES = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'] as const;

/* Sloturile orare — program admisii liceu (9:00–17:00, pas 30 min). 17 sloturi. */
export const TIME_SLOTS = (() => {
    const slots: string[] = [];

    for (let h = 9; h <= 17; h++) {
        slots.push(`${String(h).padStart(2, '0')}:00`);

        if (h !== 17) {
            slots.push(`${String(h).padStart(2, '0')}:30`);
        }
    }

    return slots;
})();

const SCHEDULER_HORIZON_DAYS = 90;

export const ADMISSION_INPUT =
    'w-full rounded-[12px] border keyline bg-background px-3.5 min-h-11 text-base text-brand-dark outline-none transition-colors focus-visible:border-brand-navy focus-visible:ring-2 focus-visible:ring-brand-navy/25 aria-[invalid=true]:border-destructive aria-[invalid=true]:ring-destructive/25';

export function isValidName(value: string): boolean {
    const v = value.trim();

    return v.length >= 2 && NAME_RE.test(v) && HAS_LETTER_RE.test(v);
}

export function isValidPhone(value: string): boolean {
    const v = value.trim();

    if (!PHONE_RE.test(v)) {
        return false;
    }

    const digits = v.replace(/\D/g, '');

    return digits.length >= 7 && digits.length <= 15;
}

export function isValidEmail(value: string): boolean {
    return EMAIL_RE.test(value.trim());
}

export function isValidAge(value: string): boolean {
    if (!value.trim()) {
        return true;
    }

    const n = Number(value);

    return Number.isInteger(n) && n >= 3 && n <= 20;
}

/* Coerență vârstă ↔ clasă (Moldova): Clasa I ≈ 7 ani → vârsta nominală = N + 6, toleranță ±2 ani. */
export function expectedAgeRange(classValue: string): { min: number; max: number } | null {
    const roman = classValue.replace('Clasa ', '');
    const index = (ROMAN_CLASSES as readonly string[]).indexOf(roman);

    if (index === -1) {
        return null;
    }

    const nominal = index + 1 + 6;

    return { min: nominal - 2, max: nominal + 2 };
}

export function classOptions(t: Tr): { value: string; label: string }[] {
    const prefix = t('admission.class_prefix', 'Clasa');

    return ROMAN_CLASSES.map((roman) => ({ value: `Clasa ${roman}`, label: `${prefix} ${roman}` }));
}

/** Validare pas „date de contact" (parent_name, phone, email). */
export function validateContact(data: ContactChildData, t: Tr): AdmissionErrors {
    const e: AdmissionErrors = {};
    const requiredMsg = t('admission.required_field', 'Acest câmp este obligatoriu.');

    if (!data.parent_name.trim()) {
        e.parent_name = requiredMsg;
    } else if (!isValidName(data.parent_name)) {
        e.parent_name = t('admission.invalid_name', 'Introduceți un nume valid (cel puțin 2 litere; nu sunt acceptate cifre).');
    }

    if (!data.phone.trim()) {
        e.phone = requiredMsg;
    } else if (!isValidPhone(data.phone)) {
        e.phone = t('admission.invalid_phone', 'Introduceți un număr de telefon valid (7–15 cifre, eventual + și spații).');
    }

    if (data.email.trim() && !isValidEmail(data.email)) {
        e.email = t('admission.invalid_email', 'Introduceți o adresă de e-mail validă.');
    }

    return e;
}

/** Validare pas „despre copil" (child_name, vârstă + coerență vârstă ↔ clasă). */
export function validateChild(data: ContactChildData, t: Tr): AdmissionErrors {
    const e: AdmissionErrors = {};

    if (!data.child_name.trim()) {
        e.child_name = t('admission.required_field', 'Acest câmp este obligatoriu.');
    } else if (!isValidName(data.child_name)) {
        e.child_name = t('admission.invalid_name', 'Introduceți un nume valid (cel puțin 2 litere; nu sunt acceptate cifre).');
    }

    if (!isValidAge(data.child_age)) {
        e.child_age = t('admission.invalid_age', 'Vârsta trebuie să fie un număr întreg între 3 și 20 de ani.');
    } else if (data.child_age.trim() && data.desired_class) {
        const range = expectedAgeRange(data.desired_class);
        const ageNum = Number(data.child_age);

        if (range && (ageNum < range.min || ageNum > range.max)) {
            e.child_age = t('admission.age_class_mismatch', 'Vârsta nu corespunde clasei alese (așteptat aproximativ :min–:max ani).')
                .replace(':min', String(range.min))
                .replace(':max', String(range.max));
        }
    }

    return e;
}

/* GTM — `dataLayer` global; tolerant la SSR (window undefined). */
type DataLayerEvent = Record<string, unknown>;

declare global {
    interface Window {
        dataLayer?: DataLayerEvent[];
    }
}

export function pushDataLayer(event: DataLayerEvent): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.dataLayer = window.dataLayer ?? [];
    window.dataLayer.push(event);
}

/* ----------------------------------------------------------------- formatare dată */

export function formatLongDate(date: Date, locale: string): string {
    return new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(date);
}

function formatMonthYear(date: Date, locale: string): string {
    return new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(date);
}

/** ISO compact "2026-07-15T14:30" — formatul trimis spre backend (parsabil cu Carbon::parse). */
export function toIsoSlot(date: Date, time: string): string {
    const yyyy = date.getFullYear();
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const dd = String(date.getDate()).padStart(2, '0');

    return `${yyyy}-${mm}-${dd}T${time}`;
}

function isSameDay(a: Date, b: Date): boolean {
    return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

/* ----------------------------------------------------------------- câmpuri */

export function Field({
    label,
    name,
    value,
    onChange,
    type = 'text',
    required = false,
    error,
    autoComplete,
    inputMode,
    pattern,
    min,
    max,
    maxLength,
}: {
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    type?: string;
    required?: boolean;
    error?: string;
    autoComplete?: string;
    inputMode?: 'text' | 'tel' | 'email' | 'numeric';
    pattern?: string;
    min?: number;
    max?: number;
    maxLength?: number;
}) {
    return (
        <div className="space-y-1.5">
            <label htmlFor={name} className="block text-sm font-semibold text-brand-navy">
                {label}
                {required && <span className="text-brand-green"> *</span>}
            </label>
            <input
                id={name}
                name={name}
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                required={required}
                autoComplete={autoComplete}
                inputMode={inputMode}
                pattern={pattern}
                min={min}
                max={max}
                maxLength={maxLength}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? `${name}-error` : undefined}
                className={ADMISSION_INPUT}
            />
            {error && (
                <p id={`${name}-error`} className="text-sm text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}

export function SelectField({
    label,
    name,
    value,
    onChange,
    placeholder,
    options,
    error,
}: {
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    options: { value: string; label: string }[];
    error?: string;
}) {
    return (
        <div className="space-y-1.5">
            <label htmlFor={name} className="block text-sm font-semibold text-brand-navy">
                {label}
            </label>
            <select
                id={name}
                name={name}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? `${name}-error` : undefined}
                className={ADMISSION_INPUT}
            >
                <option value="">{placeholder}</option>
                {options.map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label}
                    </option>
                ))}
            </select>
            {error && (
                <p id={`${name}-error`} className="text-sm text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}

/** Indicator de paşi: cercuri conectate, pasul curent evidenţiat, paşii încheiaţi bifaţi. */
export function Stepper({ step, total = 3 }: { step: number; total?: number }) {
    const steps = Array.from({ length: total }, (_, i) => i + 1);

    return (
        <ol className="mt-3 flex items-center gap-2" aria-hidden="true">
            {steps.map((n, i) => (
                <Fragment key={n}>
                    <li
                        className={cn(
                            'grid size-9 shrink-0 place-items-center rounded-full border-2 text-sm font-semibold transition-colors',
                            step > n
                                ? 'border-brand-green bg-brand-green text-[color:var(--brand-dark)]'
                                : step === n
                                  ? 'border-brand-green bg-card text-brand-navy'
                                  : 'border-brand-navy/15 bg-card text-brand-gray',
                        )}
                    >
                        {step > n ? <Check className="size-4" /> : n}
                    </li>
                    {i < steps.length - 1 && (
                        <span className={cn('h-0.5 flex-1 rounded-full transition-colors', step > n ? 'bg-brand-green' : 'bg-brand-navy/15')} />
                    )}
                </Fragment>
            ))}
        </ol>
    );
}

export function RecapRow({ label, value, empty }: { label: string; value: string; empty: string }) {
    return (
        <div className="flex flex-col">
            <dt className="text-xs text-brand-gray">{label}</dt>
            <dd className="font-medium text-brand-dark">{value.trim() || empty}</dd>
        </div>
    );
}

/* ----------------------------------------------------------------- calendar de vizită */

/** Grila 6×7 a lunii, începând Luni; cell-urile din luna prev/next sunt marcate `outside`. */
function buildMonthGrid(month: Date): { date: Date; outside: boolean }[] {
    const year = month.getFullYear();
    const m = month.getMonth();
    const firstDay = new Date(year, m, 1);
    const startOffset = (firstDay.getDay() + 6) % 7;
    const lastDay = new Date(year, m + 1, 0).getDate();

    const cells: { date: Date; outside: boolean }[] = [];

    for (let i = startOffset - 1; i >= 0; i--) {
        cells.push({ date: new Date(year, m, -i), outside: true });
    }

    for (let i = 1; i <= lastDay; i++) {
        cells.push({ date: new Date(year, m, i), outside: false });
    }

    while (cells.length < 42) {
        const last = cells[cells.length - 1].date;
        const d = new Date(last);

        d.setDate(d.getDate() + 1);
        cells.push({ date: d, outside: true });
    }

    return cells;
}

/** Etichetele Mon–Sun pe locale (abreviat, fără punct final). */
function getWeekdayLabels(locale: string): string[] {
    const fmt = new Intl.DateTimeFormat(locale, { weekday: 'short' });

    return Array.from({ length: 7 }).map((_, i) => {
        const d = new Date(2024, 0, 1 + i); // 2024-01-01 = Luni cunoscută

        return fmt.format(d).replace(/\.$/, '');
    });
}

export function VisitScheduler({
    date,
    onDateChange,
    time,
    onTimeChange,
    locale,
    t,
}: {
    date: Date | null;
    onDateChange: (d: Date | null) => void;
    time: string;
    onTimeChange: (t: string) => void;
    locale: string;
    t: Tr;
}) {
    const today = useMemo(() => {
        const d = new Date();

        d.setHours(0, 0, 0, 0);

        return d;
    }, []);

    const maxDate = useMemo(() => {
        const d = new Date(today);

        d.setDate(d.getDate() + SCHEDULER_HORIZON_DAYS);

        return d;
    }, [today]);

    /* Antetul lunii — implicit = luna în care utilizatorul a accesat siteul; dacă există deja o dată aleasă, arătăm luna ei. */
    const [shownMonth, setShownMonth] = useState(() => {
        const seed = date ?? today;

        return new Date(seed.getFullYear(), seed.getMonth(), 1);
    });

    const weekdays = useMemo(() => getWeekdayLabels(locale), [locale]);
    const grid = useMemo(() => buildMonthGrid(shownMonth), [shownMonth]);
    const monthLabel = formatMonthYear(shownMonth, locale);

    const isPrevDisabled = shownMonth.getFullYear() === today.getFullYear() && shownMonth.getMonth() === today.getMonth();
    const isNextDisabled = shownMonth.getFullYear() === maxDate.getFullYear() && shownMonth.getMonth() === maxDate.getMonth();

    function isDayDisabled(d: Date): boolean {
        if (d < today || d > maxDate) {
            return true;
        }

        const dow = d.getDay();

        return dow === 0 || dow === 6; // sâmbătă/duminică = închis
    }

    function selectDay(d: Date, outside: boolean) {
        if (isDayDisabled(d)) {
            return;
        }

        if (outside) {
            setShownMonth(new Date(d.getFullYear(), d.getMonth(), 1));
        }

        onDateChange(d);
    }

    return (
        <div className="rounded-[14px] border keyline bg-card">
            {/* Calendar */}
            <div className="p-4 sm:p-5">
                <div className="mb-3 flex items-center gap-2">
                    <CalendarDays className="size-4 text-brand-green" />
                    <p className="display text-sm tracking-wide text-brand-navy uppercase">{t('admission.scheduler_date_title', 'Alege ziua')}</p>
                </div>

                <div className="flex items-center justify-between gap-2">
                    <button
                        type="button"
                        onClick={() => setShownMonth((m) => new Date(m.getFullYear(), m.getMonth() - 1, 1))}
                        disabled={isPrevDisabled}
                        aria-label={t('admission.prev_month', 'Luna precedentă')}
                        className="grid size-9 place-items-center rounded-full text-brand-navy transition-colors enabled:hover:bg-brand-navy/8 disabled:cursor-not-allowed disabled:opacity-30"
                    >
                        <ChevronLeft className="size-4" />
                    </button>
                    <span className="display text-base text-brand-navy first-letter:uppercase">{monthLabel}</span>
                    <button
                        type="button"
                        onClick={() => setShownMonth((m) => new Date(m.getFullYear(), m.getMonth() + 1, 1))}
                        disabled={isNextDisabled}
                        aria-label={t('admission.next_month', 'Luna următoare')}
                        className="grid size-9 place-items-center rounded-full text-brand-navy transition-colors enabled:hover:bg-brand-navy/8 disabled:cursor-not-allowed disabled:opacity-30"
                    >
                        <ChevronRight className="size-4" />
                    </button>
                </div>

                <div className="mt-3 grid grid-cols-7 gap-0.5 text-center text-xs text-brand-gray">
                    {weekdays.map((w) => (
                        <span key={w} className="py-1 first-letter:uppercase">
                            {w}
                        </span>
                    ))}
                </div>

                <div className="mt-1 grid grid-cols-7 gap-0.5">
                    {grid.map(({ date: d, outside }) => {
                        const disabled = isDayDisabled(d);
                        const isToday = isSameDay(d, today);
                        const isSelected = date ? isSameDay(d, date) : false;

                        return (
                            <button
                                key={d.toISOString()}
                                type="button"
                                onClick={() => selectDay(d, outside)}
                                disabled={disabled}
                                className={cn(
                                    'numeral relative aspect-square rounded-[8px] text-sm transition-colors',
                                    disabled && 'cursor-not-allowed text-brand-gray/30',
                                    !disabled && outside && 'text-brand-gray/60 hover:bg-brand-navy/5',
                                    !disabled && !outside && !isSelected && 'text-brand-navy hover:bg-brand-navy/8',
                                    isSelected && 'bg-brand-green font-semibold text-[color:var(--brand-dark)]',
                                    isToday && !isSelected && 'ring-1 ring-brand-green/50',
                                )}
                                aria-label={formatLongDate(d, locale)}
                                aria-pressed={isSelected}
                            >
                                {d.getDate()}
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Sloturi orare */}
            <div className="border-t keyline p-4 sm:p-5">
                <div className="mb-3 flex items-center gap-2">
                    <Clock className="size-4 text-brand-green" />
                    <p className="display text-sm tracking-wide text-brand-navy uppercase">{t('admission.scheduler_time_title', 'Alege intervalul orar')}</p>
                </div>

                {!date ? (
                    <p className="text-sm text-brand-gray">
                        {t('admission.scheduler_pick_date_first', 'Selectează mai întâi o zi pentru a vedea intervalele disponibile.')}
                    </p>
                ) : (
                    <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-5">
                        {TIME_SLOTS.map((slot) => {
                            const isSelected = time === slot;

                            return (
                                <button
                                    key={slot}
                                    type="button"
                                    onClick={() => onTimeChange(slot)}
                                    aria-pressed={isSelected}
                                    className={cn(
                                        'numeral min-h-10 rounded-full border text-sm transition-colors',
                                        isSelected
                                            ? 'border-brand-green bg-brand-green font-semibold text-[color:var(--brand-dark)]'
                                            : 'border-brand-navy/15 bg-card text-brand-navy hover:border-brand-green hover:bg-brand-green/10',
                                    )}
                                >
                                    {slot}
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Confirmare programare */}
            {date && time && (
                <div className="flex items-start gap-3 border-t keyline bg-brand-green/[0.08] px-4 py-3 sm:px-5">
                    <Check className="mt-0.5 size-4 shrink-0 text-brand-green" />
                    <p className="text-sm leading-snug text-brand-dark">
                        <span className="font-semibold text-brand-navy">{t('admission.scheduled_for', 'Programat pentru')}: </span>
                        <span className="first-letter:uppercase">{formatLongDate(date, locale)}</span>
                        <span> · </span>
                        <span className="numeral">{time}</span>
                    </p>
                </div>
            )}
        </div>
    );
}
