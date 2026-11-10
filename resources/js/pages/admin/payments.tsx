import { Card, Field, Pagination, StatusBadge, inputCls } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fcfa } from '@/lib/format';
import { Head, useForm } from '@inertiajs/react';

type P = { id: number; amount: number; method: string; operator: string | null; status: string; provider_ref: string | null; created_at: string; booking: { reference: string; shop_name: string; full_name: string } | null; live_subscription: { reference: string; user: { name: string } | null } | null };

export default function Payments({ payments, pending }: { payments: { data: P[]; links: never[] }; pending: { id: number; reference: string; shop_name: string; amount: number }[] }) {
    const { data, setData, post, processing, errors } = useForm({ booking_id: '', method: 'ESPECES' });
    return (
        <SiteLayout portal="admin" title="Paiements">
            <Head title="Paiements" />
            <div className="mb-3"><Button asChild variant="outline" size="sm"><a href={route('admin.export', 'paiements')}>Exporter en CSV</a></Button></div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="bg-neutral-100"><tr>{['Date', 'Réservation', 'Montant', 'Moyen', 'Opérateur', 'Statut', 'Réf. agrégateur'].map((h) => <th key={h} className="px-3 py-2">{h}</th>)}</tr></thead>
                    <tbody>
                        {payments.data.map((p) => (
                            <tr key={p.id} className="border-t">
                                <td className="px-3 py-2">{new Date(p.created_at).toLocaleString('fr-FR', { timeZone: 'Africa/Lome' })}</td>
                                <td className="px-3 py-2">{p.booking ? `${p.booking.reference} — ${p.booking.shop_name}` : p.live_subscription ? `Abonnement Lives ${p.live_subscription.reference} — ${p.live_subscription.user?.name ?? ''}` : '—'}</td>
                                <td className="px-3 py-2">{fcfa(p.amount)}</td>
                                <td className="px-3 py-2">{p.method}</td>
                                <td className="px-3 py-2">{p.operator ?? '—'}</td>
                                <td className="px-3 py-2"><StatusBadge status={p.status} /></td>
                                <td className="px-3 py-2 font-mono text-xs">{p.provider_ref ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination links={payments.links} />

            <Card className="mt-6 max-w-xl">
                <h2 className="mb-2 font-bold">Saisir un paiement hors ligne</h2>
                <p className="mb-3 text-sm text-neutral-600">Espèces ou transfert reçu pour une réservation en attente de paiement : elle est confirmée aussitôt.</p>
                <form onSubmit={(e) => { e.preventDefault(); post(route('admin.payments.manual'), { preserveScroll: true }); }} className="grid gap-3">
                    <Field label="Réservation en attente" error={(errors as Record<string, string>).booking_id}>
                        <select className={inputCls} value={data.booking_id} onChange={(e) => setData('booking_id', e.target.value)} required>
                            <option value="">Choisir…</option>
                            {pending.map((b) => <option key={b.id} value={b.id}>{b.reference} — {b.shop_name} ({fcfa(b.amount)})</option>)}
                        </select>
                    </Field>
                    <Field label="Moyen"><select className={inputCls} value={data.method} onChange={(e) => setData('method', e.target.value)}><option value="ESPECES">Espèces</option><option value="TRANSFERT">Transfert</option></select></Field>
                    <Button disabled={processing || !data.booking_id}>Enregistrer le paiement</Button>
                </form>
            </Card>
        </SiteLayout>
    );
}
