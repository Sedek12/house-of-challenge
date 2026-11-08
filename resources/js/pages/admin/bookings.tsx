import { Card, Field, ModeBadge, Pagination, StatusBadge, inputCls } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { fmtLome } from '@/lib/format';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

type B = { id: number; status: string; mode: string; user: { name: string; email: string }; project: { title: string }; slot: { starts_at: string } };

export default function Bookings({ bookings, filters, users, projects, open_slots }: {
    bookings: { data: B[]; links: never[] }; filters: Record<string, string>;
    users: { id: number; name: string; email: string }[]; projects: { id: number; title: string; user_id: number }[]; open_slots: { id: number; starts_at: string }[];
}) {
    const [f, setF] = useState({ q: filters.q ?? '', status: filters.status ?? '', mode: filters.mode ?? '', from: filters.from ?? '' });
    const { data, setData, post, processing, errors } = useForm({ user_id: '', project_id: '', slot_id: '', mode: 'A_DISTANCE' });
    const err = errors as Record<string, string>;
    return (
        <SiteLayout portal="admin" title="Réservations">
            <Head title="Réservations" />
            <form onSubmit={(e) => { e.preventDefault(); router.get(route('admin.bookings'), Object.fromEntries(Object.entries(f).filter(([, v]) => v))); }} className="mb-4 grid gap-2 md:grid-cols-5">
                <input aria-label="Recherche" placeholder="Nom ou e-mail" className={inputCls} value={f.q} onChange={(e) => setF({ ...f, q: e.target.value })} />
                <select aria-label="Statut" className={inputCls} value={f.status} onChange={(e) => setF({ ...f, status: e.target.value })}>
                    <option value="">Tous statuts</option>
                    {['EN_ATTENTE_PAIEMENT', 'CONFIRMEE', 'EFFECTUEE', 'ABSENT', 'ANNULEE', 'ANNULEE_PAR_ORGANISATEUR'].map((s) => <option key={s}>{s}</option>)}
                </select>
                <select aria-label="Mode" className={inputCls} value={f.mode} onChange={(e) => setF({ ...f, mode: e.target.value })}><option value="">Tous modes</option><option value="SUR_PLACE">Sur place</option><option value="A_DISTANCE">À distance</option></select>
                <input aria-label="À partir du" type="date" className={inputCls} value={f.from} onChange={(e) => setF({ ...f, from: e.target.value })} />
                <button className="rounded-md bg-[#C8662B] px-3 text-white">Filtrer</button>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="bg-neutral-100"><tr><th className="p-2">#</th><th className="p-2">Participant</th><th className="p-2">Projet</th><th className="p-2">Créneau</th><th className="p-2">Mode</th><th className="p-2">Statut</th><th className="p-2" /></tr></thead>
                    <tbody>
                        {bookings.data.map((b) => (
                            <tr key={b.id} className="border-t">
                                <td className="p-2">{b.id}</td><td className="p-2">{b.user.name}<div className="text-xs text-neutral-500">{b.user.email}</div></td>
                                <td className="p-2">{b.project.title}</td><td className="p-2">{fmtLome(b.slot.starts_at)}</td>
                                <td className="p-2"><ModeBadge mode={b.mode} /></td><td className="p-2"><StatusBadge status={b.status} /></td>
                                <td className="p-2">{['CONFIRMEE', 'EN_ATTENTE_PAIEMENT'].includes(b.status) && <button className="text-xs text-red-600 underline" onClick={() => confirm('Annuler et recréditer ?') && router.post(route('admin.bookings.cancel', b.id))}>Annuler</button>}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination links={bookings.links} />

            <Card className="mt-8">
                <b>Créer une réservation manuellement</b> <span className="text-xs text-neutral-500">(ex. paiement en espèces : enregistrez d’abord le paiement)</span>
                <form onSubmit={(e) => { e.preventDefault(); post(route('admin.bookings.store')); }} className="mt-3 grid gap-3 md:grid-cols-5">
                    <Field label="Participant" error={err.user_id}><select className={inputCls} value={data.user_id} onChange={(e) => setData({ ...data, user_id: e.target.value, project_id: '' })} required><option value="" />{users.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}</select></Field>
                    <Field label="Projet" error={err.project_id}><select className={inputCls} value={data.project_id} onChange={(e) => setData('project_id', e.target.value)} required><option value="" />{projects.filter((p) => String(p.user_id) === data.user_id).map((p) => <option key={p.id} value={p.id}>{p.title}</option>)}</select></Field>
                    <Field label="Créneau" error={err.slot_id}><select className={inputCls} value={data.slot_id} onChange={(e) => setData('slot_id', e.target.value)} required><option value="" />{open_slots.map((s) => <option key={s.id} value={s.id}>{fmtLome(s.starts_at)}</option>)}</select></Field>
                    <Field label="Mode"><select className={inputCls} value={data.mode} onChange={(e) => setData('mode', e.target.value)}><option value="SUR_PLACE">Sur place</option><option value="A_DISTANCE">À distance</option></select></Field>
                    <button disabled={processing} className="self-end rounded-md bg-[#C8662B] px-3 py-2 text-white">Créer</button>
                </form>
            </Card>
        </SiteLayout>
    );
}
