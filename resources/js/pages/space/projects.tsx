import { Card } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link, router } from '@inertiajs/react';

type P = { id: number; title: string; sector: string; short_pitch: string };

export default function Projects({ projects }: { projects: P[] }) {
    return (
        <SiteLayout portal="space" title="Mes projets">
            <Head title="Mes projets" />
            <Button asChild className="mb-4"><Link href={route('space.projects.create')}>+ Nouveau projet</Link></Button>
            <div className="grid gap-3">
                {projects.length === 0 && <Card className="text-sm">Vous n’avez pas encore de projet.</Card>}
                {projects.map((p) => (
                    <Card key={p.id} className="flex flex-wrap items-center justify-between gap-2">
                        <div><b>{p.title}</b><div className="text-xs text-neutral-500">{p.sector}</div><div className="text-sm">{p.short_pitch}</div></div>
                        <div className="flex gap-2">
                            <Button asChild variant="outline" size="sm"><Link href={route('space.projects.edit', p.id)}>Modifier</Link></Button>
                            <Button variant="outline" size="sm" onClick={() => confirm('Supprimer ce projet ?') && router.delete(route('space.projects.delete', p.id))}>Supprimer</Button>
                        </div>
                    </Card>
                ))}
            </div>
        </SiteLayout>
    );
}
