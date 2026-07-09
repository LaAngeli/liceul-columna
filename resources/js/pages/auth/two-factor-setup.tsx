import { Head, setLayoutProps } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import ManageTwoFactor from '@/components/manage-two-factor';
import TwoFactorEmail from '@/components/two-factor-email';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/lib/i18n';

interface Props {
    twoFactor: {
        enabled: boolean;
        requiresConfirmation: boolean;
        email: { enabled: boolean; address: string | null };
    };
    /** Cel puțin o metodă e configurată → gate-ul s-a ridicat, se poate continua. */
    configured: boolean;
    continueTo: string;
    status?: string | null;
}

/**
 * Configurarea OBLIGATORIE a 2FA — gate-ul EnsureTwoFactorEnrolled ține utilizatorul aici
 * până activează una dintre metode (tiparul „schimbării obligatorii de parolă").
 */
export default function TwoFactorSetup({ twoFactor, configured, continueTo, status }: Props) {
    const t = useTranslations();

    setLayoutProps({
        title: 'auth.twofa_setup_title',
        description: 'auth.twofa_setup_subtitle',
        // Card lat pe desktop cât timp se configurează (conține ghidul cu QR-uri pe 2 coloane);
        // ecranul de succes „configurat" rămâne îngust. Mobilul nu e afectat.
        wide: !configured,
    });

    return (
        <>
            <Head title={t('auth.twofa_setup_title', 'Activează autentificarea în doi pași')} />

            <div className="space-y-8">
                {configured ? (
                    <div className="flex flex-col items-center gap-4 text-center">
                        <ShieldCheck className="size-10 text-primary" />
                        <p className="text-sm text-muted-foreground">{t('auth.twofa_setup_done', 'Autentificarea în doi pași e activă. De acum, la fiecare logare ți se va cere al doilea pas.')}</p>
                        {/* Navigare COMPLETĂ, nu <Link> Inertia: continueTo poate fi /admin (Filament,
                            non-Inertia) — o vizită client Inertia acolo se rupe (chrome mort, URL blocat pe
                            /configurare-2fa). Un <a> simplu face page-load real; sigur și pentru cabinet (/dashboard). */}
                        <Button asChild className="w-full">
                            <a href={continueTo}>{t('auth.twofa_setup_continue', 'Continuă către cont')}</a>
                        </Button>
                    </div>
                ) : (
                    <>
                        <ManageTwoFactor
                            canManageTwoFactor
                            requiresConfirmation={twoFactor.requiresConfirmation}
                            twoFactorEnabled={twoFactor.enabled}
                        />
                        <div className="border-t pt-6">
                            <TwoFactorEmail
                                enabled={twoFactor.email.enabled}
                                accountEmail={twoFactor.email.address}
                                status={status}
                            />
                        </div>
                    </>
                )}
            </div>
        </>
    );
}
