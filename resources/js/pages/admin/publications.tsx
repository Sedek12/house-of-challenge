import { Card, Pagination, StatusBadge } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { Head, router } from '@inertiajs/react';

type Pub = { id: number; status: string; project: { title: string; short_pitch: string; slug: string } | null; purchase: { user: { name: string; email: string; email_verified_at: string | null } } };

export default function Publications({ publications }: { publications: { data: Pub[]; links: never[] } }) {
    const act = (id: number, action: string, reason?: string) => router.post(route('admin.publications.review', id), { action, reason });
    return (
        <SiteLayout portal="admin" title="Publications">
            <Head title="Publications" />
            <div className="grid gap-3">
                {publications.data.map((p) => (
                    <Card key={p.id}>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div><b>{p.project?.title ?? 'Projet non choisi'}</b><div className="text-sm">{p.project?.short_pitch}</div>
                                <div className="text-xs text-neutral-500">{p.purchase.user.name} · {p.purchase.user.email} {p.purchase.user.email_verified_at ? '✓' : '(e-mail non vérifié)'}</div></div>
                            <StatusBadge status={p.status} />
                        </div>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {p.status !== 'PUBLIEE' && <Button size="sm" disabled={!p.project} onClick={() => act(p.id, 'publish')}>Publier</Button>}
                            {p.status !== 'REFUSEE' && p.status !== 'PUBLIEE' && <Button size="sm" variant="outline" onClick={() => { const r = prompt('Motif du refus :'); if (r) act(p.id, 'reject', r); }}>Refuser</Button>}
                            {p.status === 'PUBLIEE' && <Button size="sm" variant="outline" onClick={() => act(p.id, 'unpublish')}>Dépublier</Button>}
                        </div>
                    </Card>
                ))}
            </div>
            <Pagination links={publications.links} />
        </SiteLayout>
    );
}
