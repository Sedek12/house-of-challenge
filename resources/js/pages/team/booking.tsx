import { Card, ModeBadge, StatusBadge } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { dayKey, fcfa, fmtTime } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/react';

type Booking = {
    id: number; reference: string; status: string; mode: string; starts_at: string; shop: string; name: string; city: string; package: string;
    duration_minutes: number; whatsapp: string; tiktok: string | null; host_notes: string | null; activity_domain: string | null; activity_description: string | null;
    products: { id: number; name: string; price: number; live_price: number; quantity: number; photo_url: string | null }[];
};

/** Fiche live : une page par vendeur, lisible sur téléphone, avec « précédent » et « suivant » (A6). */
export default function Fiche({ booking: b, nav, can_mark }: { booking: Booking; nav: { prev: number | null; next: number | null; position: number | null; total: number }; can_mark: boolean }) {
    const date = dayKey(b.starts_at);
    return (
        <SiteLayout portal={can_mark ? 'admin' : 'team'}>
            <Head title={`Fiche live — ${b.shop}`} />
            <div className="mx-auto grid max-w-2xl gap-4">
                <div className="flex items-center justify-between gap-2">
                    <Button asChild variant="outline" disabled={!nav.prev}>{nav.prev ? <Link href={route('team.booking', nav.prev)}>← Précédent</Link> : <span>← Précédent</span>}</Button>
                    <Link href={route(can_mark ? 'admin.planning' : 'team.planning', { view: 'day', date })} className="text-sm text-neutral-600 underline">{nav.position ? `${nav.position} / ${nav.total}` : 'Planning'}</Link>
                    <Button asChild variant="outline" disabled={!nav.next}>{nav.next ? <Link href={route('team.booking', nav.next)}>Suivant →</Link> : <span>Suivant →</span>}</Button>
                </div>

                <Card className="grid gap-1">
                    <div className="text-sm text-neutral-600">{fmtTime(b.starts_at)} · {b.package} ({b.duration_minutes} min)</div>
                    <h1 className="text-2xl font-extrabold">{b.shop}</h1>
                    <div>{b.name} · {b.city}</div>
                    {b.activity_domain && <div className="rounded bg-orange-50 px-2 py-1 text-sm"><b>{b.activity_domain}</b>{b.activity_description ? ` — ${b.activity_description}` : ''}</div>}
                    <div className="flex flex-wrap items-center gap-2"><ModeBadge mode={b.mode} /><StatusBadge status={b.status} />{b.tiktok && <span className="text-sm">TikTok @{b.tiktok}</span>}</div>
                    <a className="text-sm text-[#B85600] underline" href={`https://wa.me/${b.whatsapp}`} target="_blank" rel="noreferrer">WhatsApp {b.whatsapp}</a>
                </Card>

                {b.host_notes && <Card className="border-amber-300 bg-amber-50"><div className="text-sm font-semibold">Consignes du vendeur</div><p className="whitespace-pre-line">{b.host_notes}</p></Card>}

                <section className="grid gap-2">
                    <h2 className="font-bold">Produits ({b.products.length})</h2>
                    {b.products.map((p) => (
                        <Card key={p.id} className="flex items-center gap-3">
                            {p.photo_url ? <img src={p.photo_url} alt="" loading="lazy" className="h-20 w-20 rounded object-cover" /> : <div className="h-20 w-20 rounded bg-neutral-200" />}
                            <div className="min-w-0">
                                <div className="text-lg font-bold">{p.name}</div>
                                <div className="text-xl font-extrabold text-[#B85600]">{fcfa(p.live_price)} <span className="text-sm font-normal text-neutral-500 line-through">{fcfa(p.price)}</span></div>
                                <div className="text-sm">Quantité : {p.quantity}</div>
                            </div>
                        </Card>
                    ))}
                </section>

                {can_mark && b.status === 'CONFIRMEE' && (
                    <div className="flex gap-2">
                        <Button onClick={() => router.post(route('admin.bookings.mark', b.id), { status: 'EFFECTUEE' })}>Live effectué</Button>
                        <Button variant="outline" onClick={() => router.post(route('admin.bookings.mark', b.id), { status: 'ABSENT' })}>Absent</Button>
                    </div>
                )}
            </div>
        </SiteLayout>
    );
}
