import { Card, ModeBadge } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fmtLome, localHint } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';

export default function Confirmation({ booking, instructions }: { booking: { id: number; status: string; mode: string; project: string; starts_at: string }; instructions: string[] }) {
    const ok = booking.status === 'CONFIRMEE';
    return (
        <SiteLayout title={ok ? '✅ Réservation confirmée' : 'Réservation'}>
            <Head title="Confirmation" />
            <Card className="max-w-2xl">
                <p className="text-lg font-semibold">{fmtLome(booking.starts_at)}</p>
                {localHint(booking.starts_at) && <p className="text-sm text-neutral-500">{localHint(booking.starts_at)}</p>}
                <p className="mt-2">Projet : <b>{booking.project}</b> <ModeBadge mode={booking.mode} /></p>
                <ul className="mt-4 list-disc pl-5 text-sm">{instructions.map((l, i) => <li key={i}>{l}</li>)}</ul>
                <p className="mt-3 text-sm text-neutral-600">Un e-mail de confirmation vous a été envoyé.</p>
                <div className="mt-5 flex flex-wrap gap-2">
                    <Button asChild variant="outline"><a href={route('ics', booking.id)}>Ajouter à mon agenda (.ics)</a></Button>
                    <Button asChild><Link href={route('space.bookings')}>Mes réservations</Link></Button>
                </div>
            </Card>
        </SiteLayout>
    );
}
