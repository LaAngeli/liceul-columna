import { Form } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/lib/i18n';
import { confirm, destroy, send } from '@/routes/two-factor-email';

export type Props = {
    /** 2FA pe email e activ pentru cont. */
    enabled: boolean;
    /** Emailul curent al contului (594/603 conturi migrate nu au — fluxul îl adaugă verificat). */
    accountEmail: string | null;
    /** Flash-ul „two-factor-email-code-sent" după trimitere (vine din pagina-mamă). */
    status?: string | null;
};

/**
 * A doua metodă 2FA — cod pe email: trimite codul (opțional către o adresă nouă, verificată prin
 * el), confirmă și activează; dezactivare cu un pas. Endpoint-urile stau sub password.confirm —
 * prima acțiune redirecționează prin pagina de confirmare a parolei, apoi fluxul curge direct.
 */
export default function TwoFactorEmail({ enabled, accountEmail, status }: Props) {
    const t = useTranslations();
    const codeSent = status === 'two-factor-email-code-sent';
    const [resendCooldown, setResendCooldown] = useState<number>(0);

    useEffect(() => {
        if (resendCooldown <= 0) {
            return;
        }

        const timer = setTimeout(() => setResendCooldown((s) => s - 1), 1000);

        return () => clearTimeout(timer);
    }, [resendCooldown]);

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title={t('settings.twofa_email_title', 'Cod pe e-mail')}
                description={t('settings.twofa_email_desc', 'Alternativă la aplicația de autentificare: primești un cod de conectare pe e-mail la fiecare logare.')}
            />

            {enabled ? (
                <div className="flex flex-col items-start space-y-4">
                    <p className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                        <MailCheck className="size-4 text-primary" />
                        {t('settings.twofa_email_status_on').replace(':email', accountEmail ?? '—')}
                    </p>

                    <Form {...destroy.form()}>
                        {({ processing }) => (
                            <Button variant="destructive" type="submit" className="min-h-11 md:min-h-0" disabled={processing}>
                                {t('settings.twofa_email_disable', 'Dezactivează codul pe e-mail')}
                            </Button>
                        )}
                    </Form>
                </div>
            ) : (
                <div className="flex flex-col items-start space-y-4">
                    {/* Pasul 1: adresa (obligatorie când contul nu are email) + trimiterea codului. */}
                    <Form
                        {...send.form()}
                        className="w-full max-w-sm space-y-3"
                        onSuccess={() => setResendCooldown(60)}
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="twofa-email">{t('settings.twofa_email_input_label', 'Adresa de e-mail')}</Label>
                                    <Input
                                        id="twofa-email"
                                        name="email"
                                        type="email"
                                        defaultValue={accountEmail ?? ''}
                                        placeholder="nume@exemplu.md"
                                    />
                                    {accountEmail === null && (
                                        <p className="text-xs text-muted-foreground">
                                            {t('settings.twofa_email_input_hint', 'Contul tău nu are e-mail — adaugă o adresă; o verificăm prin codul trimis.')}
                                        </p>
                                    )}
                                    <InputError message={errors.email} />
                                </div>

                                <Button type="submit" variant={codeSent ? 'outline' : 'default'} className="min-h-11 md:min-h-0" disabled={processing || resendCooldown > 0}>
                                    {resendCooldown > 0
                                        ? t('auth.twofa_email_resend_in').replace(':seconds', String(resendCooldown))
                                        : t('settings.twofa_email_send_code', 'Trimite codul de verificare')}
                                </Button>
                            </>
                        )}
                    </Form>

                    {/* Pasul 2: confirmarea codului activează metoda (și comite adresa nouă). */}
                    {codeSent && (
                        <Form {...confirm.form()} className="w-full max-w-sm space-y-3">
                            {({ errors, processing }) => (
                                <>
                                    <p className="text-sm text-muted-foreground">
                                        {t('settings.twofa_email_sent', 'Ți-am trimis un cod de verificare. Introdu-l mai jos.')}
                                    </p>
                                    <div className="grid gap-2">
                                        <Label htmlFor="twofa-email-code">{t('settings.twofa_email_code_label', 'Codul primit')}</Label>
                                        <Input
                                            id="twofa-email-code"
                                            name="code"
                                            type="text"
                                            inputMode="numeric"
                                            maxLength={6}
                                            autoComplete="one-time-code"
                                            required
                                        />
                                        <InputError message={errors.code} />
                                    </div>

                                    <Button type="submit" className="min-h-11 md:min-h-0" disabled={processing}>
                                        {t('settings.twofa_email_confirm', 'Confirmă și activează')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </div>
            )}
        </div>
    );
}
