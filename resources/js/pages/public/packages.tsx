import { PackageCard, Pkg, ReserveButton } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { Head } from '@inertiajs/react';

/** Détail de tous les packages, avec un bouton « Choisir ce package » qui mène directement au choix du créneau. */
export default function Packages({ packages }: { packages: Pkg[] }) {
    return (
        <SiteLayout title="Nos packages">
            <Head title="Nos packages" />
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {packages.map((p, i) => (
                    <div key={p.id} id={p.code} className="scroll-mt-24">
                        <PackageCard pkg={p} rank={i} total={packages.length} action={<ReserveButton pack={p.code}>Choisir ce package</ReserveButton>} />
                    </div>
                ))}
            </div>
        </SiteLayout>
    );
}