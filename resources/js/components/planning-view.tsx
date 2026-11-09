import { Card, ModeBadge, StatusBadge } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import { dayKey, fmtDay, fmtTime } from '@/lib/format';
import { Link, router } from '@inertiajs/react';

export type PlanningRow = {
    id: number; reference: string; status: string; mode: string; starts_at: string; shop: string; name: string; package: string; products: number; whatsapp: string; tiktok: string | null;
};

const shift = (date: string, days: number) => {
    const d = new Date(`${date}T12:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
};

/** Planning du jour ou de la semaine, dans l'ordre de passage (A4). `canMark` : actions « effectué / absent » (admin). */
export function PlanningView({ routeName, view, date, rows, canMark }: { routeName: string; view: string; date: string; rows: PlanningRow[]; canMark?: boolean }) {
    const go = (q: { view?: string; date?: string }) => router.get(route(routeName), { view, date, ...q }, { preserveState: true });
    const step = view === 'week' ? 7 : 1;
    const days = [...new Set(rows.map((r) => dayKey(r.starts_at)))];

    return (
        <div className="grid gap-4">
            <div className="flex flex-wrap items-center gap-2">
                {view !== 'upcoming' && (
                    <>
                        <Button variant="outline" size="sm" onClick={() => go({ date: shift(date, -step) })}>←</Button>
                        <input type="date" aria-label="Date" value={date} onChange={(e) => e.target.value && go({ date: e.target.value })} className="rounded-md border px-2 py-1 text-sm" />
                        <Button variant="outline" size="sm" onClick={() => go({ date: shift(date, step) })}>→</Button>
                        <Button variant="outline" size="sm" onClick={() => go({ date: new Date().toISOString().slice(0, 10) })}>Aujourd’hui</Button>
                    </>
                )}
                <div className="ml-auto flex gap-1" role="tablist">
                    {(['upcoming', 'day', 'week'] as const).map((v) => (
                        <button key={v} role="tab" aria-selected={view === v} onClick={() => go({ view: v })} className={`rounded-md border px-3 py-1 text-sm ${view === v ? 'bg-primary text-white' : 'bg-white'}`}>{v === 'upcoming' ? 'À venir' : v === 'day' ? 'Jour' : 'Semaine'}</button>
                    ))}
                </div>
            </div>

            {rows.length === 0 && <Card>{view === 'upcoming' ? 'Aucun passage à venir pour le moment. Les nouvelles réservations payées apparaîtront ici.' : 'Aucun passage confirmé sur cette période.'}</Card>}
            {days.map((d) => (
                <section key={d}>
                    <h2 className="mb-2 font-bold first-letter:uppercase">{fmtDay(rows.find((r) => dayKey(r.starts_at) === d)!.starts_at)}</h2>
                    <div className="grid gap-2">
                        {rows.filter((r) => dayKey(r.starts_at) === d).map((r) => (
                            <Card key={r.id} className="flex flex-wrap items-center gap-3">
                                <div className="w-20 text-lg font-extrabold">{fmtTime(r.starts_at)}</div>
                                <div className="min-w-0 flex-1">
                                    <Link className="font-semibold text-[#B85600] underline" href={route('team.booking', r.id)}>{r.shop}</Link>
                                    <div className="text-sm text-neutral-600">{r.name} · {r.package} · {r.products} produit(s) · {r.reference}</div>
                                </div>
                                <ModeBadge mode={r.mode} /><StatusBadge status={r.status} />
                                {canMark && r.status === 'CONFIRMEE' && (
                                    <div className="flex gap-1">
                                        <Button size="sm" onClick={() => router.post(route('admin.bookings.mark', r.id), { status: 'EFFECTUEE' }, { preserveScroll: true })}>Effectué</Button>
                                        <Button size="sm" variant="outline" onClick={() => router.post(route('admin.bookings.mark', r.id), { status: 'ABSENT' }, { preserveScroll: true })}>Absent</Button>
                                    </div>
                                )}
                            </Card>
                        ))}
                    </div>
                </section>
            ))}
        </div>
    );
}
