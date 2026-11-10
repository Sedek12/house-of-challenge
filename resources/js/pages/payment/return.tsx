import { Card } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link, router } from '@inertiajs/react';
import { CircleCheck, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';

/** Page de retour : elle n'a aucun pouvoir de validation, elle attend la notification serveur de l'agrégateur. */
export default function PaymentReturn({ token, status: initial }: { token: string; status: string }) {
    const [status, setStatus] = useState(initial);
    const [timedOut, setTimedOut] = useState(false);

    useEffect(() => {
        if (status !== 'EN_ATTENTE') return;
        const started = Date.now();
        const t = setInterval(async () => {
            const r = await fetch(route('payment.status', token), { headers: { Accept: 'application/json' } });
            const j = await r.json();
            setStatus(j.status);
            if (j.status === 'PAYE') router.visit(route('track', token));
            if (j.status !== 'EN_ATTENTE') clearInterval(t);
            if (Date.now() - started > 120000) { clearInterval(t); setTimedOut(true); }
        }, 3000);
        return () => clearInterval(t);
    }, [status, token]);

    return (
        <SiteLayout title="Paiement">
            <Head title="Paiement"><meta name="robots" content="noindex" /></Head>
            <Card className="max-w-xl">
                {status === 'EN_ATTENTE' && !timedOut && <p className="flex items-center gap-2"><Loader2 className="size-4 animate-spin text-[#B85600]" aria-hidden />Vérification de votre paiement en cours…</p>}
                {status === 'EN_ATTENTE' && timedOut && (
                    <>
                        <p>Nous attendons encore la confirmation de votre opérateur. Vous recevrez un e-mail dès que le paiement sera confirmé.</p>
                        <Button asChild className="mt-3"><Link href={route('track', token)}>Voir ma réservation</Link></Button>
                    </>
                )}
                {status === 'PAYE' && <p className="flex items-center gap-2 font-semibold text-green-700"><CircleCheck className="size-5" aria-hidden />Paiement reçu. Merci !</p>}
                {(status === 'ECHOUE' || status === 'ANNULE') && (
                    <>
                        <p className="font-semibold text-red-700">Le paiement n’a pas abouti. Votre créneau a été libéré.</p>
                        <Button asChild className="mt-3"><Link href={route('reserve')}>Recommencer ma réservation</Link></Button>
                    </>
                )}
            </Card>
        </SiteLayout>
    );
}
