import { Card, StatusBadge } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { fmtLome } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';

type S = { id: number; title: string; starts_at: string; status: string; booked_count: number };

function List({ items }: { items: S[] }) {
    return (
        <div className="grid gap-2">
            {items.length === 0 && <Card className="text-sm">Aucune session.</Card>}
            {items.map((s) => (
                <Link key={s.id} href={route('team.session', s.id)}>
                    <Card className="flex flex-wrap items-center justify-between gap-2 hover:border-[#B05A26]">
                        <div><b>{s.title}</b><div className="text-sm">{fmtLome(s.starts_at)}</div></div>
                        <div className="flex items-center gap-2 text-sm">{s.booked_count} passage(s) <StatusBadge status={s.status} /></div>
                    </Card>
                </Link>
            ))}
        </div>
    );
}

export default function Planning({ upcoming, past }: { upcoming: S[]; past: S[] }) {
    return (
        <SiteLayout portal="team" title="Planning des sessions">
            <Head title="Planning" />
            <h2 className="mb-2 font-bold">À venir</h2><List items={upcoming} />
            <h2 className="mb-2 mt-8 font-bold">Passées</h2><List items={past} />
        </SiteLayout>
    );
}
