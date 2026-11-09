import { Card, ModeBadge, Pagination, StatusBadge, inputCls } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fcfa, fmtLome } from '@/lib/format';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type B = { id: number; reference: string; status: string; mode: string; full_name: string; shop_name: string; email: string; phone: string; amount: number; package: string; starts_at: string; tracking_token: string };

function Row({ b, slots }: { b: B; slots: { id: number; starts_at: string }[] }) {
    const [target, setTarget] = useState('');
    return (
        <Card className="grid gap-2 text-sm">
            <div className="flex flex-wrap items-center gap-2">
                <b>{b.reference}</b><StatusBadge status={b.status} /><ModeBadge mode={b.mode} />
                <span className="ml-auto">{fcfa(b.amount)} · {b.package}</span>
            </div>
            <div>{b.shop_name} — {b.full_name} · {b.email} · {b.phone}</div>
            <div>{fmtLome(b.starts_at)}</div>
            <div className="flex flex-wrap items-center gap-2">
                <Button asChild size="sm" variant="outline"><a href={route('track', b.tracking_token)} target="_blank" rel="noreferrer">Lien de suivi</a></Button>
                {b.status === 'CONFIRMEE' && (
                    <>
                        <select aria-label="Nouveau créneau" className={`${inputCls} !w-auto`} value={target} onChange={(e) => setTarget(e.target.value)}>
                            <option value="">Déplacer vers…</option>
                            {slots.map((s) => <option key={s.id} value={s.id}>{fmtLome(s.starts_at)}</option>)}
                        </select>
                        <Button size="sm" variant="outline" disabled={!target} onClick={() => router.post(route('admin.bookings.reschedule', b.id), { slot_id: target }, { preserveScroll: true })}>Déplacer</Button>
                    </>
                )}
                {['CONFIRMEE', 'EN_ATTENTE_PAIEMENT'].includes(b.status) && (
                    <Button size="sm" variant="destructive" onClick={() => confirm('Annuler cette réservation ? Le créneau sera libéré. Le remboursement se fait hors du site.') && router.post(route('admin.bookings.cancel', b.id), {}, { preserveScroll: true })}>Annuler</Button>
                )}
                {b.status === 'CONFIRMEE' && <>
                    <Button size="sm" onClick={() => router.post(route('admin.bookings.mark', b.id), { status: 'EFFECTUEE' }, { preserveScroll: true })}>Effectué</Button>
                    <Button size="sm" variant="outline" onClick={() => router.post(route('admin.bookings.mark', b.id), { status: 'ABSENT' }, { preserveScroll: true })}>Absent</Button>
                </>}
            </div>
        </Card>
    );
}

export default function Bookings({ bookings, filters, open_slots }: { bookings: { data: B[]; links: never[] }; filters: Record<string, string>; open_slots: { id: number; starts_at: string }[] }) {
    const [f, setF] = useState({ q: filters.q ?? '', status: filters.status ?? '', from: filters.from ?? '' });
    return (
        <SiteLayout portal="admin" title="Réservations">
            <Head title="Réservations" />
            <form onSubmit={(e) => { e.preventDefault(); router.get(route('admin.bookings'), Object.fromEntries(Object.entries(f).filter(([, v]) => v))); }} className="mb-4 grid gap-2 md:grid-cols-4">
                <input aria-label="Recherche" placeholder="Nom, boutique, e-mail, référence" className={inputCls} value={f.q} onChange={(e) => setF({ ...f, q: e.target.value })} />
                <select aria-label="Statut" className={inputCls} value={f.status} onChange={(e) => setF({ ...f, status: e.target.value })}>
                    <option value="">Tous statuts</option>
                    {['EN_ATTENTE_PAIEMENT', 'CONFIRMEE', 'EFFECTUEE', 'ABSENT', 'ANNULEE'].map((s) => <option key={s}>{s}</option>)}
                </select>
                <input aria-label="À partir du" type="date" className={inputCls} value={f.from} onChange={(e) => setF({ ...f, from: e.target.value })} />
                <Button>Filtrer</Button>
            </form>
            <div className="mb-3"><Button asChild variant="outline" size="sm"><a href={route('admin.export', 'reservations')}>Exporter en CSV</a></Button></div>
            <div className="grid gap-3">
                {bookings.data.length === 0 && <Card>Aucune réservation.</Card>}
                {bookings.data.map((b) => <Row key={b.id} b={b} slots={open_slots} />)}
            </div>
            <Pagination links={bookings.links} />
        </SiteLayout>
    );
}
