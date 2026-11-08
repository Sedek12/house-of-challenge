import { Card } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function PaymentReturn({ payment_id, status: initial, booking_id }: { payment_id: number; status: string; booking_id: number | null }) {
    const [status, setStatus] = useState(initial);
    const [timedOut, setTimedOut] = useState(false);

    useEffect(() => {
        if (status !== 'EN_ATTENTE') return;
        const started = Date.now();
        const t = setInterval(async () => {
            const r = await fetch(route('payment.status', payment_id), { headers: { Accept: 'application/json' } });
            const j = await r.json();
            setStatus(j.status);
            if (j.status === 'PAYE' && j.booking_status === 'CONFIRMEE' && booking_id) router.visit(route('reserve.confirmation', booking_id));
            if (j.status !== 'EN_ATTENTE') clearInterval(t);
            if (Date.now() - started > 120000) { clearInterval(t); setTimedOut(true); }
        }, 3000);
        return () => clearInterval(t);
    }, [status, payment_id, booking_id]);

    return (
        <SiteLayout title="Paiement">
            <Head title="Paiement" />
            <Card className="max-w-xl">
                {status === 'EN_ATTENTE' && !timedOut && <p>⏳ Vérification de votre paiement en cours…</p>}
                {status === 'EN_ATTENTE' && timedOut && <p>Nous vérifions votre paiement, vous recevrez un e-mail dès qu’il sera confirmé.</p>}
                {status === 'PAYE' && (
                    <>
                        <p className="font-semibold text-green-700">✅ Paiement reçu. Merci !</p>
                        {booking_id ? <Button asChild className="mt-3"><Link href={route('reserve.confirmation', booking_id)}>Voir ma réservation</Link></Button>
                            : <Button asChild className="mt-3"><Link href={route('reserve')}>Réserver un créneau</Link></Button>}
                    </>
                )}
                {(status === 'ECHOUE' || status === 'ANNULE') && (
                    <>
                        <p className="font-semibold text-red-700">Le paiement n’a pas abouti. Aucun montant n’a été débité.</p>
                        <Button asChild className="mt-3"><Link href={route('reserve')}>Réessayer</Link></Button>
                    </>
                )}
            </Card>
        </SiteLayout>
    );
}
