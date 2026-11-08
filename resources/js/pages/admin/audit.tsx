import { Pagination } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { Head } from '@inertiajs/react';

type L = { id: number; actor_id: number | null; action: string; entity: string; entity_id: string; data: unknown; created_at: string };

export default function Audit({ logs }: { logs: { data: L[]; links: never[] } }) {
    return (
        <SiteLayout portal="admin" title="Journal d’audit">
            <Head title="Audit" />
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="bg-neutral-100"><tr><th className="p-2">Date</th><th className="p-2">Acteur</th><th className="p-2">Action</th><th className="p-2">Objet</th><th className="p-2">Détails</th></tr></thead>
                    <tbody>{logs.data.map((l) => (
                        <tr key={l.id} className="border-t"><td className="p-2">{new Date(l.created_at).toLocaleString('fr-FR')}</td><td className="p-2">#{l.actor_id ?? '—'}</td><td className="p-2">{l.action}</td><td className="p-2">{l.entity} #{l.entity_id}</td><td className="p-2 text-xs">{l.data ? JSON.stringify(l.data) : ''}</td></tr>
                    ))}</tbody>
                </table>
            </div>
            <Pagination links={logs.links} />
        </SiteLayout>
    );
}
