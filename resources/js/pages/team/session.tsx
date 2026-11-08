import { ModeBadge, StatusBadge } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fmtLome, fmtTime } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';

type Row = { id: number; status: string; mode: string; starts_at: string; name: string; project: string; tiktok: string | null; whatsapp: string };

/** « Conducteur » de la session : imprimable via window.print(). */
export default function Session({ session, rows }: { session: { id: number; title: string; starts_at: string; tiktok_live_url: string | null; notes: string | null }; rows: Row[] }) {
    return (
        <SiteLayout portal="team" title={session.title}>
            <Head title={session.title} />
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <p>{fmtLome(session.starts_at)}</p>
                <Button variant="outline" className="print:hidden" onClick={() => window.print()}>Imprimer le conducteur</Button>
            </div>
            {session.notes && <p className="mb-3 text-sm">{session.notes}</p>}
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="bg-neutral-100"><tr><th className="p-2">Heure</th><th className="p-2">Participant</th><th className="p-2">Projet</th><th className="p-2">Mode</th><th className="p-2">TikTok</th><th className="p-2">WhatsApp</th><th className="p-2">Statut</th></tr></thead>
                    <tbody>
                        {rows.length === 0 && <tr><td colSpan={7} className="p-3 text-neutral-500">Aucun passage confirmé.</td></tr>}
                        {rows.map((r) => (
                            <tr key={r.id} className="border-t">
                                <td className="p-2 font-semibold">{fmtTime(r.starts_at)}</td>
                                <td className="p-2"><Link className="text-[#B05A26] underline" href={route('team.booking', r.id)}>{r.name}</Link></td>
                                <td className="p-2">{r.project}</td>
                                <td className="p-2"><ModeBadge mode={r.mode} /></td>
                                <td className="p-2">{r.tiktok ? <a className="underline" target="_blank" rel="noreferrer" href={`https://www.tiktok.com/@${r.tiktok}`}>@{r.tiktok}</a> : '—'}</td>
                                <td className="p-2">{r.whatsapp ? <a className="underline" target="_blank" rel="noreferrer" href={`https://wa.me/${r.whatsapp}`}>Écrire</a> : '—'}</td>
                                <td className="p-2"><StatusBadge status={r.status} /></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </SiteLayout>
    );
}
