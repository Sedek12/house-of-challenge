import { CategoryTiles, CountryBubbles, LiveCategory, LiveDot, LiveDto, useAutoRefresh, WindowBadge, WindowState } from '@/components/live';
import { LiveTile, LiveViewer } from '@/components/live-viewer';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { Country } from '@/lib/countries';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Radio, X } from 'lucide-react';
import { useCallback, useState } from 'react';

type Props = {
    lives: LiveDto[];
    counts: { countries: Record<string, number>; categories: Record<string, number>; total: number };
    filters: { pays: string | null; categorie: string | null };
    countries: Country[];
    categories: LiveCategory[];
    window: WindowState;
};

const STEPS: { title: string; steps: string[] }[] = [
    {
        title: 'Pour acheter',
        steps: ['Choisissez un pays, puis ce que vous cherchez.', 'Touchez un live : il s’ouvre en grand.', 'Touchez « Rejoindre le live sur TikTok », posez vos questions et achetez auprès du vendeur.'],
    },
    {
        title: 'Pour vendre',
        steps: ['Créez votre compte vendeur et activez l’abonnement.', 'Lancez votre live sur TikTok, puis envoyez-nous son lien depuis « Mon live ».', 'L’équipe vérifie que vous êtes en live, puis votre live apparaît ici.'],
    },
];

/** Annuaire public : les vendeurs en live sur TikTok en ce moment, par pays et par catégorie. */
export default function Lives({ lives, counts, filters, countries, categories, window: w }: Props) {
    const [openId, setOpenId] = useState<number | null>(null);
    const openIndex = lives.findIndex((l) => l.id === openId);

    const go = (next: Partial<Props['filters']>) => {
        const f = { ...filters, ...next };
        router.get(route('lives'), { ...(f.pays ? { pays: f.pays } : {}), ...(f.categorie ? { categorie: f.categorie } : {}) }, { preserveState: true, preserveScroll: true, replace: true });
    };
    const reload = useCallback(() => router.reload({ only: ['lives', 'counts', 'window'] }), []);
    useAutoRefresh(reload, 30_000);
    const filtered = Boolean(filters.pays || filters.categorie);

    return (
        <SiteLayout>
            <Head title="Lives en direct" />

            {/* En-tête compact : titre, nombre de lives, action principale */}
            <header className="mb-3 flex items-center justify-between gap-3">
                <div className="min-w-0">
                    <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">Lives en direct</h1>
                    <p className="mt-0.5 inline-flex items-center gap-2 text-sm text-neutral-600">
                        {counts.total > 0 && <LiveDot />}
                        {counts.total === 0 ? 'Aucun vendeur en direct pour le moment.' : `${counts.total} vendeur${counts.total > 1 ? 's' : ''} en direct`}
                    </p>
                </div>
                <Button asChild size="sm" className="shrink-0">
                    <Link href={route('live.mine')}><span className="sm:hidden">Mon live</span><span className="hidden sm:inline">Mettre mon live en avant</span><ArrowRight className="size-4" aria-hidden /></Link>
                </Button>
            </header>

            {/* Filtres : une barre collée en haut, deux lignes qui défilent sur le côté (pas de grand bloc à parcourir) */}
            <section aria-label="Filtres" className="sticky top-16 z-30 -mx-4 mb-4 grid gap-1.5 border-b bg-neutral-50/95 px-4 py-2 backdrop-blur sm:top-[72px]">
                <CountryBubbles oneLine countries={countries} value={filters.pays} onChange={(pays) => go({ pays })} counts={counts.countries} allLabel="Tous" extra={Object.keys(counts.countries)} />
                <CategoryTiles oneLine categories={categories} value={filters.categorie} onChange={(categorie) => go({ categorie })} counts={counts.categories} allLabel="Toutes" />
            </section>

            {filtered && (
                <div className="mb-3 flex items-center justify-between text-sm text-neutral-600">
                    <span>{lives.length} résultat{lives.length > 1 ? 's' : ''}</span>
                    <button type="button" onClick={() => go({ pays: null, categorie: null })} className="inline-flex items-center gap-1 font-medium text-neutral-700 hover:text-black">
                        <X className="size-4" aria-hidden /> Effacer
                    </button>
                </div>
            )}

            {/* Lives : tuiles verticales, 2 par ligne sur téléphone */}
            {lives.length > 0 ? (
                <section aria-label="Lives en cours" className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    {lives.map((l) => <LiveTile key={l.id} live={l} countries={countries} categories={categories} onOpen={() => setOpenId(l.id)} />)}
                </section>
            ) : (
                <section className="flex flex-col items-center rounded-xl border border-dashed bg-white px-6 py-14 text-center">
                    <span className="flex size-12 items-center justify-center rounded-full bg-neutral-100"><Radio className="size-6 text-neutral-500" aria-hidden /></span>
                    <p className="mt-4 text-lg font-semibold">{filtered ? 'Aucun live dans cette sélection.' : 'Aucun live pour le moment.'}</p>
                    <p className="mt-1 max-w-md text-neutral-600">Les vendeurs sont surtout en direct de {w.label}. Cette page se met à jour toute seule.</p>
                    {filtered && <Button variant="soft" className="mt-5" onClick={() => go({ pays: null, categorie: null })}>Voir tous les lives</Button>}
                </section>
            )}

            {/* Comment ça marche : un seul bloc, deux colonnes */}
            <section aria-labelledby="how" className="mt-10 overflow-hidden rounded-xl border bg-white">
                <h2 id="how" className="border-b px-5 py-4 text-lg font-bold">Comment ça marche</h2>
                <div className="grid divide-y md:grid-cols-2 md:divide-x md:divide-y-0">
                    {STEPS.map(({ title, steps }) => (
                        <div key={title} className="p-5">
                            <h3 className="mb-4 font-semibold">{title}</h3>
                            <ol className="grid gap-3.5">
                                {steps.map((s, i) => (
                                    <li key={s} className="flex gap-3 text-sm text-neutral-700">
                                        <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">{i + 1}</span>
                                        <span className="pt-0.5">{s}</span>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    ))}
                </div>
                <div className="border-t bg-neutral-50 px-5 py-3"><WindowBadge window={w} className="border-0 bg-transparent px-0 py-0" /></div>
            </section>

            {/* Live ouvert en plein écran */}
            {openIndex >= 0 && (
                <LiveViewer lives={lives} index={openIndex} onIndex={(i) => setOpenId(lives[i].id)} onClose={() => setOpenId(null)} countries={countries} categories={categories} />
            )}
        </SiteLayout>
    );
}
