import { PackageCard, Pkg } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Packages({ packages }: { packages: Pkg[] }) {
    const { auth } = usePage<{ auth: { user: unknown } }>().props;
    return (
        <SiteLayout title="Nos formules">
            <Head title="Formules" />
            <div className="grid gap-4 md:grid-cols-3">
                {packages.map((p) => (
                    <PackageCard key={p.id} pkg={p} action={
                        <div className="grid gap-2">
                            <Button asChild><Link href={route('reserve')}>Réserver un créneau</Link></Button>
                            {!!auth.user && <Button variant="outline" onClick={() => router.post(route('space.purchases.buy'), { package_id: p.id })}>Acheter seulement</Button>}
                        </div>
                    } />
                ))}
            </div>
            <p className="mt-4 text-sm text-neutral-600">Paiement par Mobile Money ou carte bancaire. Les crédits non utilisés expirent à la fin de la validité.</p>
        </SiteLayout>
    );
}
