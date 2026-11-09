import { PackageCard, Pkg, ReserveButton } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { Head } from '@inertiajs/react';

/** Détail de tous les packages, avec un bouton « Choisir ce package » qui mène directement au choix du créneau. */
export default function Packages({ packages }: { packages: Pkg[] }) {
    return (
        <SiteLayout title="Nos packages">
            <Head title="Nos packages" />
            <p className="mb-6 max-w-2xl text-neutral-600">
                Chaque package reprend tout ce que contient le précédent, avec des avantages en plus : plus de temps à l’antenne, plus de produits, et plus de visibilité grâce à la republication et au relais de votre passage.
                Choisissez celui qui vous convient : vous passerez ensuite au choix du créneau.
            </p>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {packages.map((p) => (
                    <div key={p.id} id={p.code} className="scroll-mt-24">
                        <PackageCard pkg={p} action={<ReserveButton pack={p.code}>Choisir ce package</ReserveButton>} />
                    </div>
                ))}
            </div>
        </SiteLayout>
    );
}