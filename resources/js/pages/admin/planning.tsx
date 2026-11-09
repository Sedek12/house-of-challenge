import { Card } from '@/components/hoc';
import { PlanningRow, PlanningView } from '@/components/planning-view';
import SiteLayout from '@/layouts/site-layout';
import { fcfa } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';

type Stats = { upcoming: number; pending: number; revenue_month: number; done: number };

function Stat({ label, value, href }: { label: string; value: string | number; href?: string }) {
    const body = (
        <Card className="h-full">
            <div className="text-2xl font-extrabold text-[#B85600]">{value}</div>
            <div className="text-sm text-neutral-600">{label}</div>
        </Card>
    );
    return href ? <Link href={href}>{body}</Link> : body;
}

export default function AdminPlanning({ stats, ...props }: { view: string; date: string; rows: PlanningRow[]; stats: Stats }) {
    return (
        <SiteLayout portal="admin" title="Planning">
            <Head title="Planning" />
            <div className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
                <Stat label="Passages à venir" value={stats.upcoming} />
                <Stat label="En attente de paiement" value={stats.pending} href={route('admin.bookings', { status: 'EN_ATTENTE_PAIEMENT' })} />
                <Stat label="Encaissé ce mois-ci" value={fcfa(stats.revenue_month)} href={route('admin.payments')} />
                <Stat label="Lives effectués" value={stats.done} href={route('admin.bookings', { status: 'EFFECTUEE' })} />
            </div>
            <PlanningView routeName="admin.planning" canMark {...props} />
        </SiteLayout>
    );
}