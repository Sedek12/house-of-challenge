import { Card, Pkg, ReserveButton } from '@/components/hoc';
import { Markdown } from '@/components/markdown';
import { OcdBand } from '@/components/ocd-band';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fcfa, tiktokHandle } from '@/lib/format';
import { Head, Link, usePage } from '@inertiajs/react';

const STEPS = [
    ['Choisissez votre package', 'Selon la durée de passage et le nombre de produits.'],
    ['Choisissez un créneau libre', 'Parmi les dates et heures ouvertes par l’équipe.'],
    ['Décrivez vos produits', 'Noms, prix live et photos : l’animateur les présentera.'],
    ['Payez par Flooz ou T-Money', 'Votre place est confirmée aussitôt, sans créer de compte.'],
];

export default function Home({ packages, faq, concept, hero_image }: { packages: Pkg[]; faq: string; concept: string; hero_image: string }) {
    const { site } = usePage().props as unknown as { site: { tiktok: string; logo: string } };
    const prices = packages.map((p) => p.price);
    const [minPrice, maxPrice] = [Math.min(...prices), Math.max(...prices)];
    return (
        <SiteLayout>
            <Head title="Réservez votre passage en live" />

            {/* 1. Accueil */}
            <section className="grid items-center gap-6 rounded-xl border bg-white p-6 md:grid-cols-2 md:p-10">
                <div>
                    <p className="mb-2 text-sm font-semibold uppercase tracking-wide text-[#B85600]">Avec Bovann · House of Challenge</p>
                    <h1 className="text-3xl font-extrabold leading-tight md:text-4xl">Présentez vos produits en <span className="text-[#B85600]">live</span> avec Bovann et vendez à toute l’audience</h1>
                    <p className="mt-3 text-neutral-600">Bovann lance le live sur le TikTok de House of Challenge : vendeurs et entrepreneurs viennent y présenter leurs produits, pour vendre et gagner de l’argent.</p>
                    <div className="mt-6 flex flex-wrap gap-3">
                        <ReserveButton>Réserver mon passage</ReserveButton>
                        <Button asChild variant="soft"><a href="#concept">Découvrir le concept</a></Button>
                    </div>
                </div>
                {hero_image ? (
                    <img src={hero_image} alt="Le studio OCD Live : mur lumineux en forme de carte de l’Afrique" width={436} height={418} fetchPriority="high" className="aspect-square w-full max-w-md justify-self-center rounded-lg object-cover shadow-md md:justify-self-end" />
                ) : (
                    <div className="flex aspect-[4/3] items-center justify-center rounded-lg bg-gradient-to-br from-orange-50 to-orange-200 p-8">
                        <img src={site.logo} alt="" className="w-3/4" />
                    </div>
                )}
            </section>

            {/* Sens de OCD */}
            <div className="mt-10"><OcdBand /></div>
            {/* 2. Le concept */}
            <section className="mt-10 grid gap-6 md:grid-cols-3" id="concept">
                <div className="md:col-span-2"><Card className="h-full"><Markdown text={concept ?? ''} /></Card></div>
                <div className="flex flex-col justify-center gap-3 rounded-lg bg-[#1F1F1F] p-5 text-white shadow-sm">
                    <div className="text-xl font-bold">Suivez le live</div>
                    <p className="text-sm text-neutral-300">Les lives ont lieu sur le compte TikTok de House of Challenge, avec Bovann. Ce site sert uniquement à réserver votre passage.</p>
                    <Button asChild><a href={site.tiktok} target="_blank" rel="noreferrer">{tiktokHandle(site.tiktok) ? `Voir ${tiktokHandle(site.tiktok)} sur TikTok` : 'Voir House of Challenge sur TikTok'}</a></Button>
                </div>
            </section>

            {/* 3. Comment réserver */}
            <section className="mt-10">
                <h2 className="mb-4 text-xl font-bold">Réservez en moins de 3 minutes</h2>
                <ol className="grid gap-3 sm:grid-cols-2 md:grid-cols-4">
                    {STEPS.map(([t, d], i) => (
                        <li key={t}><Card className="h-full"><div className="text-xl font-extrabold text-[#B85600]">{i + 1}</div><div className="font-semibold">{t}</div><p className="mt-1 text-sm text-neutral-600">{d}</p></Card></li>
                    ))}
                </ol>
            </section>

            {/* 4. Les packages : aperçu et accès à la page dédiée */}
            <section className="mt-10" id="packages">
                <Card className="grid gap-4 md:grid-cols-2 md:items-center md:p-8">
                    <div>
                        <h2 className="text-xl font-bold">{packages.length} packages pour tous les budgets</h2>
                        <p className="mt-2 text-neutral-600">
                            De {fcfa(minPrice)} à {fcfa(maxPrice)} : plus le package est grand, plus vous avez d’avantages, comme plus de temps à l’antenne, plus de produits, et la republication ou le relais de votre vidéo pour toucher plus de monde.
                        </p>
                        <div className="mt-4"><Button asChild size="lg"><Link href={route('packages')}>Découvrir les packages</Link></Button></div>
                    </div>
                    <ul className="grid gap-2 text-sm">
                        {packages.map((p) => (
                            <li key={p.id}>
                                <Link href={`${route('packages')}#${p.code}`} className="flex items-center justify-between gap-3 rounded-md border bg-neutral-50 px-3 py-2 hover:border-primary">
                                    <span className="font-semibold">{p.name}</span>
                                    <span className="text-[#B85600]">{fcfa(p.price)} · {p.duration_minutes} min</span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Card>
            </section>

            {/* 5. FAQ */}
            <section className="mt-10" id="faq">
                <h2 className="mb-4 text-xl font-bold">Questions fréquentes</h2>
                <Card><Markdown text={faq ?? ''} /></Card>
            </section>
        </SiteLayout>
    );
}
