import { Card } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { fcfa, fmtLome } from '@/lib/format';
import { Head } from '@inertiajs/react';

type Stats = { revenue_month: number; participants: number; upcoming_bookings: number; done: number; absence_rate: number; pending_publications: number };

export default function Dashboard({ stats, sessions }: { stats: Stats; sessions: { id: number; title: string; starts_at: string }[] }) {
    const tiles: [string, string | number][] = [
        ['Revenus du mois', fcfa(stats.revenue_month)], ['Inscrits', stats.participants], ['Réservations à venir', stats.upcoming_bookings],
        ['Passages effectués', stats.done], ['Taux d’absence', `${stats.absence_rate} %`], ['Publications en attente', stats.pending_publications],
    ];
    return (
        <SiteLayout portal="admin" title="Tableau de bord">
            <Head title="Admin" />
            <div className="grid gap-3 md:grid-cols-3">
                {tiles.map(([l, v]) => <Card key={l}><div className="text-sm text-neutral-500">{l}</div><div className="text-2xl font-extrabold">{v}</div></Card>)}
            </div>
            <h2 className="mb-2 mt-8 font-bold">Prochaines sessions</h2>
            <div className="grid gap-2">{sessions.map((s) => <Card key={s.id} className="text-sm"><b>{s.title}</b> — {fmtLome(s.starts_at)}</Card>)}</div>
            <h2 className="mb-2 mt-8 font-bold">Exports CSV</h2>
            <div className="flex gap-3 text-sm">
                {['reservations', 'paiements', 'participants'].map((t) => <a key={t} className="text-[#B05A26] underline" href={route('admin.export', t)}>{t}</a>)}
            </div>
        </SiteLayout>
    );
}
