import { Card, StatusBadge, inputCls } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type Pub = { id: number; status: string; project_id: number | null; project: { title: string } | null; purchase: { package_name: string }; reject_reason: string | null; expires_at: string | null };

export default function Publications({ publications, projects, email_verified }: { publications: Pub[]; projects: { id: number; title: string }[]; email_verified: boolean }) {
    const [choice, setChoice] = useState<Record<number, string>>({});
    return (
        <SiteLayout portal="space" title="Mes publications">
            <Head title="Publications" />
            {!email_verified && <Card className="mb-4 border-amber-300 bg-amber-50 text-sm">Votre e-mail doit être vérifié avant la validation de la publication.</Card>}
            {publications.length === 0 && <Card className="text-sm">Aucune publication. Elles sont incluses dans les formules « visibilité ».</Card>}
            <div className="grid gap-3">
                {publications.map((p) => (
                    <Card key={p.id}>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div><b>{p.purchase.package_name}</b><div className="text-sm">{p.project ? p.project.title : 'Aucun projet choisi'}</div>
                                {p.expires_at && <div className="text-xs text-neutral-500">Visible jusqu’au {new Date(p.expires_at).toLocaleDateString('fr-FR')}</div>}
                                {p.reject_reason && <div className="text-sm text-red-700">Motif du refus : {p.reject_reason}</div>}</div>
                            <StatusBadge status={p.status} />
                        </div>
                        {['EN_ATTENTE', 'REFUSEE'].includes(p.status) && (
                            <div className="mt-3 flex gap-2">
                                <select aria-label="Projet à publier" className={inputCls} value={choice[p.id] ?? p.project_id ?? ''} onChange={(e) => setChoice({ ...choice, [p.id]: e.target.value })}>
                                    <option value="">Choisir un projet…</option>
                                    {projects.map((pr) => <option key={pr.id} value={pr.id}>{pr.title}</option>)}
                                </select>
                                <button className="rounded-md bg-[#C8662B] px-3 text-sm text-white" onClick={() => choice[p.id] && router.post(route('space.publications.choose', p.id), { project_id: choice[p.id] })}>Soumettre</button>
                            </div>
                        )}
                    </Card>
                ))}
            </div>
        </SiteLayout>
    );
}
