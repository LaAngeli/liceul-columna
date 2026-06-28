import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';

interface NoticeSection {
    h: string;
    b: string;
}

interface Notice {
    title: string;
    intro: string;
    sections: NoticeSection[];
    agree: string;
    button: string;
    recorded: string;
}

interface Props {
    version: string;
    notice: Notice;
}

/**
 * Luarea la cunoștință a notei de informare (Legea 133/2011 §7). Pagină BLOCANTĂ pentru elev/părinte
 * la prima logare (sau la schimbarea versiunii); confirmarea se înregistrează ca dovadă.
 */
export default function PrivacyConsent({ version, notice }: Props) {
    const [agreed, setAgreed] = useState(false);
    const [processing, setProcessing] = useState(false);

    function submit() {
        setProcessing(true);
        router.post('/consimtamant', {}, { onFinish: () => setProcessing(false) });
    }

    return (
        <>
            <Head title={notice.title} />

            <div className="grid gap-5">
                <p className="text-sm text-muted-foreground">{notice.intro}</p>

                <div className="max-h-[45vh] space-y-4 overflow-y-auto rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 text-sm dark:border-sidebar-border">
                    {notice.sections.map((s) => (
                        <div key={s.h}>
                            <h3 className="font-semibold">{s.h}</h3>
                            <p className="text-muted-foreground">{s.b}</p>
                        </div>
                    ))}
                </div>

                <label className="flex items-start gap-3 text-sm">
                    <Checkbox
                        checked={agreed}
                        onCheckedChange={(value) => setAgreed(value === true)}
                        className="mt-0.5"
                    />
                    <span>{notice.agree}</span>
                </label>

                <Button onClick={submit} disabled={!agreed || processing} className="w-full">
                    {processing && <Spinner />}
                    {notice.button}
                </Button>

                <p className="text-center text-xs text-muted-foreground">
                    {notice.recorded} (v{version})
                </p>
            </div>
        </>
    );
}

PrivacyConsent.layout = {
    title: 'auth.privacy_title',
    description: 'auth.privacy_subtitle',
};
