import { Card, StatusBadge } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fmtLome, fmtTime } from '@/lib/format';
import { Head, router } from '@inertiajs/react';

type Slot = { id: number; starts_at: string; status: string; booking_id: number | null; who: string | null };

export default function Session({ session, slots }: { session: { id: number; title: string; starts_at: string; status: string }; slots: Slot[] }) {
    return (
        <SiteLayout portal="admin" title={session.title}>
            <Head title={session.title} />
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <span>{fmtLome(session.starts_at)}</span><StatusBadge status={session.status} />
                {session.status === 'PROGRAMMEE' && (
                    <Button variant="destructive" size="sm" onClick={() => confirm('Annuler ce live ? Toutes les réservations seront annulées et les vendeurs prévenus par e-mail (remboursement hors du site).') && router.post(route('admin.sessions.cancel', session.id))}>Annuler le live</Button>
                )}
                <Button variant="outline" size="sm" onClick={() => confirm('Supprimer ce live ?') && router.delete(route('admin.sessions.delete', session.id))}>Supprimer</Button>
            </div>
            <div className="grid grid-cols-2 gap-2 md:grid-cols-4">
                {slots.map((s) => (
                    <Card key={s.id} className="text-sm">
                        <div className="font-semibold">{fmtTime(s.starts_at)}</div>
                        <StatusBadge status={s.status} />
                        {s.who && <div className="mt-1 text-xs">{s.who}</div>}
                        {s.booking_id && <button className="mt-1 text-xs text-red-600 underline" onClick={() => confirm('Annuler cette réservation ?') && router.post(route('admin.bookings.cancel', s.booking_id!))}>Annuler la réservation</button>}
                        {['DISPONIBLE', 'BLOQUE'].includes(s.status) && <button className="mt-1 block text-xs underline" onClick={() => router.post(route('admin.slots.toggle', s.id))}>{s.status === 'BLOQUE' ? 'Débloquer' : 'Bloquer'}</button>}
                    </Card>
                ))}
            </div>
        </SiteLayout>
    );
}
