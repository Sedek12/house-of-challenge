import { inputCls, Pagination } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ProjectCard } from './home';

export default function Projects({ projects, filters, sectors }: { projects: { data: never[]; links: never[] }; filters: Record<string, string>; sectors: string[] }) {
    const [f, setF] = useState({ q: filters.q ?? '', sector: filters.sector ?? '', country: filters.country ?? '' });
    const go = (e: React.FormEvent) => { e.preventDefault(); router.get(route('projects'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true }); };
    return (
        <SiteLayout title="Projets présentés">
            <Head title="Projets" />
            <form onSubmit={go} className="mb-5 grid gap-2 md:grid-cols-4">
                <input aria-label="Recherche" className={inputCls} placeholder="Mot-clé" value={f.q} onChange={(e) => setF({ ...f, q: e.target.value })} />
                <select aria-label="Secteur" className={inputCls} value={f.sector} onChange={(e) => setF({ ...f, sector: e.target.value })}>
                    <option value="">Tous les secteurs</option>
                    {sectors.map((s) => <option key={s}>{s}</option>)}
                </select>
                <input aria-label="Pays" className={inputCls} placeholder="Pays" value={f.country} onChange={(e) => setF({ ...f, country: e.target.value })} />
                <button className="rounded-md bg-[#B05A26] px-4 py-2 text-sm text-white">Filtrer</button>
            </form>
            {projects.data.length === 0 && <p className="text-neutral-600">Aucun projet publié pour le moment.</p>}
            <div className="grid gap-4 md:grid-cols-3">{projects.data.map((p: { id: number }) => <ProjectCard key={p.id} p={p as never} />)}</div>
            <Pagination links={projects.links} />
        </SiteLayout>
    );
}
