import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/lib/i18n';

type Props = {
    passwordRules: string;
};

export default function MustChangePassword({ passwordRules }: Props) {
    const t = useTranslations();

    return (
        <>
            <Head title={t('auth.head_must')} />

            <Form
                action="/schimbare-parola"
                method="put"
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">{t('auth.new_password')}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                className="mt-1 block w-full"
                                autoFocus
                                placeholder={t('auth.new_password')}
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">{t('auth.confirm_password')}</Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                className="mt-1 block w-full"
                                placeholder={t('auth.confirm_password')}
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password_confirmation} />
                        </div>

                        <Button type="submit" className="mt-2 w-full" disabled={processing}>
                            {processing && <Spinner />}
                            {t('auth.must_submit')}
                        </Button>
                    </div>
                )}
            </Form>

            <div className="mt-4 text-center text-sm text-muted-foreground">
                <Link href="/logout" method="post" as="button" className="underline underline-offset-4">
                    {t('auth.logout')}
                </Link>
            </div>
        </>
    );
}

MustChangePassword.layout = {
    title: 'auth.must_title',
    description: 'auth.must_subtitle',
};
