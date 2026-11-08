import { Card, Field, Pagination, StatusBadge, inputCls } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { fcfa } from '@/lib/format';
import { Head, useForm } from '@inertiajs/react';

type P = { id: number; provider: string; method: string; amount: number; status: string; created_at: string; purchase: { package_name: string; user: { name: string; email: string } } };

export default function Payments({ payments, packages, users }: { payments: { data: P[]; links: never[] }; packages: { id: number; name: string; price: number }[]; users: { id: number; name: string; email: string }[] }) {
    const { data, setData, post, processing, errors } = useForm({ user_id: '', package_id: '', method: 'ESPECES', mode: '' });
    const err = errors as Record<string, string>;
    return (
        <SiteLayout portal="admin" title="Paiements">
            <Head title="Paiements" />
            <Card className="mb-6">
                <b>Saisir un paiement manuel</b> <span className="text-xs text-neutral-500">(même effet qu’un paiement en ligne : crédits ajoutés)</span>
                <form onSubmit={(e) => { e.preventDefault(); post(route('admin.payments.manual')); }} className="mt-3 grid gap-3 md:grid-cols-5">
                    <Field label="Participant" error={err.user_id}><select className={inputCls} value={data.user_id} onChange={(e) => setData('user_id', e.target.value)} required><option value="" />{users.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.email})</option>)}</select></Field>
                    <Field label="Formule" error={err.package_id}><select className={inputCls} value={data.package_id} onChange={(e) => setData('package_id', e.target.value)} required><option value="" />{packages.map((p) => <option key={p.id} value={p.id}>{p.name} — {fcfa(p.price)}</option>)}</select></Field>
                    <Field label="Moyen"><select className={inputCls} value={data.method} onChange={(e) => setData('method', e.target.value)}><option value="ESPECES">Espèces</option><option value="TRANSFERT">Transfert</option></select></Field>
                    <Field label="Mode (prix)"><select className={inputCls} value={data.mode} onChange={(e) => setData('mode', e.target.value)}><option value="">Prix standard</option><option value="SUR_PLACE">Sur place</option><option value="A_DISTANCE">À distance</option></select></Field>
                    <button disabled={processing} className="self-end rounded-md bg-[#B05A26] px-3 py-2 text-white">Enregistrer</button>
                </form>
            </Card>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="bg-neutral-100"><tr><th className="p-2">#</th><th className="p-2">Participant</th><th className="p-2">Formule</th><th className="p-2">Montant</th><th className="p-2">Moyen</th><th className="p-2">Prestataire</th><th className="p-2">Statut</th><th className="p-2">Date</th></tr></thead>
                    <tbody>{payments.data.map((p) => (
                        <tr key={p.id} className="border-t"><td className="p-2">{p.id}</td><td className="p-2">{p.purchase.user.name}</td><td className="p-2">{p.purchase.package_name}</td><td className="p-2">{fcfa(p.amount)}</td>
                            <td className="p-2">{p.method}</td><td className="p-2">{p.provider}</td><td className="p-2"><StatusBadge status={p.status} /></td><td className="p-2">{new Date(p.created_at).toLocaleString('fr-FR')}</td></tr>
                    ))}</tbody>
                </table>
            </div>
            <Pagination links={payments.links} />
        </SiteLayout>
    );
}
