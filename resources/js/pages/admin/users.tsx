import { Pagination, inputCls } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type U = { id: number; name: string; email: string; role: string; is_active: boolean; phone_e164: string | null; country: string | null; credits: number };

export default function Users({ users, filters }: { users: { data: U[]; links: never[] }; filters: Record<string, string> }) {
    const [q, setQ] = useState(filters.q ?? '');
    const adjust = (u: U) => {
        const delta = Number(prompt(`Ajuster les crédits de ${u.name} (ex. 2 ou -1) :`));
        if (!delta) return;
        const note = prompt('Motif (obligatoire) :');
        if (note) router.post(route('admin.users.credits', u.id), { delta, note });
    };
    return (
        <SiteLayout portal="admin" title="Utilisateurs">
            <Head title="Utilisateurs" />
            <form onSubmit={(e) => { e.preventDefault(); router.get(route('admin.users'), q ? { q } : {}); }} className="mb-4 flex gap-2">
                <input aria-label="Recherche" className={inputCls} placeholder="Nom ou e-mail" value={q} onChange={(e) => setQ(e.target.value)} />
                <button className="rounded-md bg-[#C8662B] px-4 text-white">Rechercher</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="bg-neutral-100"><tr><th className="p-2">Nom</th><th className="p-2">E-mail</th><th className="p-2">Rôle</th><th className="p-2">Crédits</th><th className="p-2">Actif</th><th className="p-2" /></tr></thead>
                    <tbody>{users.data.map((u) => (
                        <tr key={u.id} className="border-t">
                            <td className="p-2">{u.name}</td><td className="p-2">{u.email}</td>
                            <td className="p-2"><select aria-label="Rôle" className="rounded border px-1" value={u.role} onChange={(e) => router.post(route('admin.users.update', u.id), { role: e.target.value })}>{['PARTICIPANT', 'INTERVIEWER', 'ADMIN'].map((r) => <option key={r}>{r}</option>)}</select></td>
                            <td className="p-2">{u.credits}</td>
                            <td className="p-2"><input type="checkbox" aria-label="Actif" checked={u.is_active} onChange={(e) => router.post(route('admin.users.update', u.id), { is_active: e.target.checked })} /></td>
                            <td className="p-2"><button className="text-xs underline" onClick={() => adjust(u)}>Ajuster crédits</button></td>
                        </tr>
                    ))}</tbody>
                </table>
            </div>
            <Pagination links={users.links} />
        </SiteLayout>
    );
}
