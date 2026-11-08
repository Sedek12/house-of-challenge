import { Card, ModeBadge, StatusBadge, inputCls } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fmtLome } from '@/lib/format';
import { Head, Link, router, useForm } from '@inertiajs/react';

type Props = {
    booking: { id: number; status: string; mode: string; starts_at: string; name: string; email: string; country: string; city: string | null; tiktok: string | null; whatsapp: string; session_id: number };
    project: { title: string; sector: string; stage: string; short_pitch: string; description: string; needs: string[] | null; country: string; links: Record<string, string> | null };
    notes: { id: number; content: string; author: string; created_at: string }[];
    history: { id: number; status: string; project: string; starts_at: string }[];
};

export default function TeamBooking({ booking: b, project: p, notes, history }: Props) {
    const { data, setData, post, processing, reset } = useForm({ content: '' });
    return (
        <SiteLayout portal="team" title={`Fiche de préparation — ${b.name}`}>
            <Head title="Fiche de préparation" />
            <Link className="mb-3 inline-block text-sm text-[#C8662B] underline" href={route('team.session', b.session_id)}>← Retour à la session</Link>
            <div className="grid gap-4 md:grid-cols-2">
                <Card>
                    <div className="mb-2 flex gap-2"><ModeBadge mode={b.mode} /><StatusBadge status={b.status} /></div>
                    <p className="font-semibold">{fmtLome(b.starts_at)}</p>
                    <p className="text-sm">{b.name} — {b.city ? `${b.city}, ` : ''}{b.country}</p>
                    <p className="text-sm">{b.email}</p>
                    <p className="mt-1 text-sm">
                        {b.tiktok && <a className="mr-3 underline" target="_blank" rel="noreferrer" href={`https://www.tiktok.com/@${b.tiktok}`}>TikTok @{b.tiktok}</a>}
                        {b.whatsapp && <a className="underline" target="_blank" rel="noreferrer" href={`https://wa.me/${b.whatsapp}`}>Écrire au participant</a>}
                    </p>
                    <div className="mt-4 flex gap-2 print:hidden">
                        <Button onClick={() => router.post(route('team.mark', b.id), { status: 'EFFECTUEE' })}>Marquer effectué</Button>
                        <Button variant="outline" onClick={() => router.post(route('team.mark', b.id), { status: 'ABSENT' })}>Marquer absent</Button>
                    </div>
                </Card>
                <Card>
                    <h2 className="text-lg font-bold">{p.title}</h2>
                    <p className="text-xs text-neutral-500">{p.sector} · {p.stage} · {p.country}</p>
                    <p className="mt-2 font-medium">{p.short_pitch}</p>
                    <p className="mt-2 whitespace-pre-line text-sm">{p.description}</p>
                    {p.needs?.length ? <p className="mt-2 text-sm"><b>Besoins :</b> {p.needs.join(', ')}</p> : null}
                    {p.links && <ul className="mt-2 text-sm">{Object.entries(p.links).map(([k, v]) => <li key={k}><a className="underline" target="_blank" rel="noopener noreferrer" href={v}>{k}</a></li>)}</ul>}
                </Card>
            </div>

            {history.length > 0 && (
                <Card className="mt-4"><b>Passages précédents</b>
                    {history.map((h) => <div key={h.id} className="mt-1 text-sm">{fmtLome(h.starts_at)} — {h.project} <StatusBadge status={h.status} /></div>)}
                </Card>
            )}

            <Card className="mt-4">
                <b>Notes d’interview</b> <span className="text-xs text-neutral-500">(visibles uniquement par l’équipe)</span>
                <form className="mt-2 flex gap-2 print:hidden" onSubmit={(e) => { e.preventDefault(); post(route('team.note', b.id), { onSuccess: () => reset() }); }}>
                    <textarea aria-label="Nouvelle note" rows={2} className={inputCls} value={data.content} onChange={(e) => setData('content', e.target.value)} required />
                    <Button disabled={processing}>Ajouter</Button>
                </form>
                {notes.map((n) => <div key={n.id} className="mt-3 border-t pt-2 text-sm"><div className="text-xs text-neutral-500">{n.author} · {fmtLome(n.created_at)}</div>{n.content}</div>)}
            </Card>
        </SiteLayout>
    );
}
