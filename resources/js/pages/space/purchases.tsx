import { Card, PackageCard, Pkg, StatusBadge } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fcfa } from '@/lib/format';
import { Head, router } from '@inertiajs/react';

type Pu = { id: number; package_name: string; amount: number; credits_total: number; credits_remaining: number; status: string; expires_at: string | null; created_at: string };

export default function Purchases({ purchases, packages }: { purchases: Pu[]; packages: Pkg[] }) {
    return (
        <SiteLayout portal="space" title="Mes achats">
            <Head title="Mes achats" />
            <div className="grid gap-2">
                {purchases.length === 0 && <Card className="text-sm">Aucun achat pour le moment.</Card>}
                {purchases.map((p) => (
                    <Card key={p.id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <div>
                            <b>#{p.id} {p.package_name}</b> — {fcfa(p.amount)}
                            <div className="text-xs text-neutral-500">{new Date(p.created_at).toLocaleDateString('fr-FR')} · {p.credits_remaining}/{p.credits_total} crédit(s){p.expires_at && ` · expire le ${new Date(p.expires_at).toLocaleDateString('fr-FR')}`}</div>
                        </div>
                        <StatusBadge status={p.status} />
                    </Card>
                ))}
            </div>
            <h2 className="mb-3 mt-8 font-bold">Acheter des crédits</h2>
            <div className="grid gap-4 md:grid-cols-3">
                {packages.map((p) => <PackageCard key={p.id} pkg={p} action={<Button className="w-full" onClick={() => router.post(route('space.purchases.buy'), { package_id: p.id })}>Acheter</Button>} />)}
            </div>
        </SiteLayout>
    );
}
