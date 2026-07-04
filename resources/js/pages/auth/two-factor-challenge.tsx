import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { useTranslations } from '@/lib/i18n';
import { store } from '@/routes/two-factor/login';
import { send as sendEmailCode, verify as verifyEmailCode } from '@/routes/two-factor-email/challenge';

interface Props {
    /** Metoda utilizatorului provocat: TOTP (aplicație) sau cod pe email. */
    method?: 'totp' | 'email';
    maskedEmail?: string | null;
    status?: string | null;
}

export default function TwoFactorChallenge({ method = 'totp', maskedEmail, status }: Props) {
    const t = useTranslations();
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');
    const [resendCooldown, setResendCooldown] = useState<number>(0);

    const isEmail = method === 'email';
    const codeSent = status === 'two-factor-email-code-sent';

    useEffect(() => {
        if (resendCooldown <= 0) {
            return;
        }

        const timer = setTimeout(() => setResendCooldown((s) => s - 1), 1000);

        return () => clearTimeout(timer);
    }, [resendCooldown]);

    const toggleText = showRecoveryInput
        ? t('auth.twofa_use_code')
        : t('auth.twofa_use_recovery');

    setLayoutProps({
        title: isEmail
            ? 'auth.twofa_email_title'
            : showRecoveryInput ? 'auth.twofa_recovery_title' : 'auth.twofa_code_title',
        description: isEmail
            ? 'auth.twofa_email_subtitle'
            : showRecoveryInput ? 'auth.twofa_recovery_subtitle' : 'auth.twofa_code_subtitle',
    });

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    if (isEmail) {
        return (
            <>
                <Head title={t('auth.head_twofa')} />

                <div className="space-y-6">
                    {/* Pasul 1: trimiterea codului către emailul (mascat) al contului. */}
                    <Form
                        {...sendEmailCode.form()}
                        className="space-y-3"
                        onSuccess={() => setResendCooldown(60)}
                    >
                        {({ processing }) => (
                            <div className="flex flex-col items-center space-y-3 text-center">
                                {codeSent && maskedEmail && (
                                    <p className="text-sm text-muted-foreground">
                                        {t('auth.twofa_email_sent_to').replace(':email', maskedEmail)}
                                    </p>
                                )}
                                <Button
                                    type="submit"
                                    variant={codeSent ? 'outline' : 'default'}
                                    className="w-full"
                                    disabled={processing || resendCooldown > 0}
                                >
                                    {resendCooldown > 0
                                        ? t('auth.twofa_email_resend_in').replace(':seconds', String(resendCooldown))
                                        : codeSent
                                          ? t('auth.twofa_email_resend')
                                          : t('auth.twofa_email_send')}
                                </Button>
                            </div>
                        )}
                    </Form>

                    {/* Pasul 2: codul primit finalizează autentificarea. */}
                    <Form {...verifyEmailCode.form()} className="space-y-4" resetOnError>
                        {({ errors, processing }) => (
                            <>
                                <div className="flex flex-col items-center justify-center space-y-3 text-center">
                                    <InputOTP
                                        name="code"
                                        maxLength={OTP_MAX_LENGTH}
                                        value={code}
                                        onChange={(value) => setCode(value)}
                                        disabled={processing}
                                        pattern={REGEXP_ONLY_DIGITS}
                                        autoFocus
                                    >
                                        <InputOTPGroup>
                                            {Array.from({ length: OTP_MAX_LENGTH }, (_, index) => (
                                                <InputOTPSlot key={index} index={index} />
                                            ))}
                                        </InputOTPGroup>
                                    </InputOTP>
                                    <InputError message={errors.code} />
                                </div>

                                <Button type="submit" className="w-full" disabled={processing || code.length < OTP_MAX_LENGTH}>
                                    {t('auth.twofa_continue')}
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={t('auth.head_twofa')} />

            <div className="space-y-6">
                <Form
                    {...store.form()}
                    className="space-y-4"
                    resetOnError
                    resetOnSuccess={!showRecoveryInput}
                >
                    {({ errors, processing, clearErrors }) => (
                        <>
                            {showRecoveryInput ? (
                                <>
                                    <Input
                                        name="recovery_code"
                                        type="text"
                                        placeholder={t('auth.twofa_recovery_ph')}
                                        autoFocus={showRecoveryInput}
                                        required
                                    />
                                    <InputError
                                        message={errors.recovery_code}
                                    />
                                </>
                            ) : (
                                <div className="flex flex-col items-center justify-center space-y-3 text-center">
                                    <div className="flex w-full items-center justify-center">
                                        <InputOTP
                                            name="code"
                                            maxLength={OTP_MAX_LENGTH}
                                            value={code}
                                            onChange={(value) => setCode(value)}
                                            disabled={processing}
                                            pattern={REGEXP_ONLY_DIGITS}
                                            autoFocus
                                        >
                                            <InputOTPGroup>
                                                {Array.from(
                                                    { length: OTP_MAX_LENGTH },
                                                    (_, index) => (
                                                        <InputOTPSlot
                                                            key={index}
                                                            index={index}
                                                        />
                                                    ),
                                                )}
                                            </InputOTPGroup>
                                        </InputOTP>
                                    </div>
                                    <InputError message={errors.code} />
                                </div>
                            )}

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {t('auth.twofa_continue')}
                            </Button>

                            <div className="text-center text-sm text-muted-foreground">
                                <span>{t('auth.twofa_or_you_can')} </span>
                                <button
                                    type="button"
                                    className="cursor-pointer text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                    onClick={() =>
                                        toggleRecoveryMode(clearErrors)
                                    }
                                >
                                    {toggleText}
                                </button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
