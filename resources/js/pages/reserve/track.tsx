import { PosterButton, Receipt } from '@/components/booking-actions';
import { Card, Field, ModeBadge, StatusBadge, inputCls } from '@/components/hoc';
import { emptyProduct, ProductRow, ProductsEditor } from '@/components/products-editor';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fcfa, fmtLome } from '@/lib/format';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarPlus, MessageCircle, Printer } from 'lucide-react';
import { useEffect, useState } from 'react';

type Booking = {
    reference: string; status: string; mode: string; amount: number; full_name: string; shop_name: string; city: string; tiktok: string | null; activity_domain: string | null; activity_description: string | null;
    host_notes: string | null; starts_at: string; hold_expires_at: string | null; package: { name: string; duration_minutes: number; max_products: number };
    products: { id: number; name: string; price: number; live_price: number; quantity: number; photo_url: string | null }[];
    can_edit: boolean; edit_deadline_hours: number;
};

/** Transforme chaque adresse web d'une ligne de texte en lien cliquable (plan d'accès, compte TikTok…). */
function linkify(line: string) {
    return line.split(/(https?:\/\/\S+)/g).map((part, i) =>
        /^https?:\/\//.test(part) ? <a key={i} className="text-[#B85600] underline" href={part} target="_blank" rel="noreferrer">{part.replace(/^https?:\/\/(www\.)?/, '')}</a> : part,
    );
}
function Countdown({ until }: { until: string }) {
    const [left, setLeft] = useState(0);
    useEffect(() => {
        const tick = () => setLeft(Math.max(0, Math.floor((new Date(until).getTime() - Date.now()) / 1000)));
        tick();
        const t = setInterval(tick, 1000);
        return () => clearInterval(t);
    }, [until]);
    return <b>{String(Math.floor(left / 60)).padStart(2, '0')}:{String(left % 60).padStart(2, '0')}</b>;
}

/** Page unique : confirmation après le paiement ET suivi via le lien personnel (F8, F9). */
export default function Track({ booking: b, token, instructions, whatsapp }: { booking: Booking; token: string; instructions: string[]; whatsapp: string | null }) {
    const { data, setData, post, processing, errors, transform } = useForm<{ host_notes: string; products: ProductRow[] }>({
        host_notes: b.host_notes ?? '',
        products: b.products.length ? b.products.map((p) => ({ id: p.id, name: p.name, price: String(p.price), live_price: String(p.live_price), quantity: String(p.quantity), photo: null, photo_url: p.photo_url })) : [emptyProduct()],
    });
    transform((d) => ({ ...d, products: d.products.map((p) => ({ id: p.id, name: p.name, price: p.price, live_price: p.live_price, quantity: p.quantity, ...(p.photo ? { photo: p.photo } : {}) })) }));
    const err = errors as Record<string, string>;
    const confirmed = ['CONFIRMEE', 'EFFECTUEE'].includes(b.status);
    const firstName = b.full_name.trim().split(/\s+/)[0];

    return (
        <SiteLayout title={confirmed ? undefined : 'Votre réservation'}>
            <Head title={`Réservation ${b.reference}`}><meta name="robots" content="noindex" /></Head>
            <Receipt reference={b.reference} status={b.status} name={b.full_name} shop={b.shop_name} city={b.city} packageName={b.package.name} minutes={b.package.duration_minutes} startsAt={b.starts_at} mode={b.mode} amount={b.amount} />
            <div className="grid max-w-3xl gap-5 print:hidden">
                {confirmed && (
                    <header>
                        <h1 className="text-3xl font-extrabold tracking-tight sm:text-4xl">C’est réservé, {firstName}.</h1>
                        <p className="mt-2 text-neutral-600">Votre créneau est confirmé et le paiement est bien reçu. Voici tout ce qu’il faut pour la suite.</p>
                    </header>
                )}

                {/* Billet : la référence en grand, puis les informations du passage */}
                <section aria-label="Votre réservation" className="rounded-xl bg-primary p-6 text-primary-foreground [forced-color-adjust:none]">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div className="text-xs font-semibold uppercase tracking-widest opacity-80">Référence</div>
                            <div className="text-3xl font-extrabold tracking-wider sm:text-4xl">{b.reference}</div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2"><StatusBadge status={b.status} /><ModeBadge mode={b.mode} /></div>
                    </div>
                    <dl className="mt-5 grid gap-4 border-t border-dashed border-current/40 pt-5 sm:grid-cols-3">
                        <div><dt className="text-xs uppercase tracking-wide opacity-80">Boutique</dt><dd className="font-bold">{b.shop_name}</dd><dd className="text-sm opacity-90">{b.full_name} · {b.city}</dd></div>
                        <div><dt className="text-xs uppercase tracking-wide opacity-80">Package</dt><dd className="font-bold">{b.package.name}</dd><dd className="text-sm opacity-90">{b.package.duration_minutes} min · {fcfa(b.amount)}</dd></div>
                        <div><dt className="text-xs uppercase tracking-wide opacity-80">Passage</dt><dd className="font-bold">{fmtLome(b.starts_at)}</dd></div>
                    </dl>
                </section>

                {b.status === 'EN_ATTENTE_PAIEMENT' && b.hold_expires_at && (
                    <div className="rounded border border-amber-300 bg-amber-50 p-3 text-sm">
                        Paiement non reçu. Votre créneau reste bloqué encore <Countdown until={b.hold_expires_at} />.
                        <Button className="ml-3" size="sm" onClick={() => router.post(route('track.pay', token))}>Reprendre le paiement</Button>
                    </div>
                )}
                {b.status === 'ANNULEE' && <p className="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">Cette réservation est annulée. Contactez-nous sur WhatsApp pour toute question ou pour être remboursé.</p>}

                {confirmed && (
                    <>
                        <div className="flex flex-wrap gap-2">
                            <PosterButton shop={b.shop_name} startsAt={b.starts_at} mode={b.mode} />
                            <Button asChild variant="outline"><a href={route('ics', token)}><CalendarPlus className="size-4" aria-hidden />Ajouter à mon agenda</a></Button>
                            <Button variant="outline" onClick={() => window.print()}><Printer className="size-4" aria-hidden />Mon reçu (PDF)</Button>
                            {whatsapp && <Button asChild variant="outline"><a href={whatsapp} target="_blank" rel="noreferrer"><MessageCircle className="size-4" aria-hidden />Écrire à l’équipe</a></Button>}
                        </div>
                        <p className="-mt-3 text-xs text-neutral-500">Affiche : à publier sur WhatsApp ou vos réseaux pour annoncer votre passage. Reçu : choisissez « Enregistrer au format PDF » dans la fenêtre d’impression.</p>

                        <Card>
                            <h2 className="mb-4 font-bold">Et maintenant ?</h2>
                            <ol className="relative grid gap-5 border-l-2 border-neutral-200 pl-6">
                                {([
                                    ['Maintenant', 'Votre réservation est confirmée. Vous recevez un e-mail avec le détail de votre passage.'],
                                    [`Jusqu’à ${b.edit_deadline_hours} h avant le live`, 'Modifiez vos produits et vos consignes depuis cette page (section plus bas).'],
                                    ['Le jour J', 'Suivez les informations pratiques ci-dessous : où et quand vous présenter.'],
                                ] as [string, string][]).map(([t, d], i) => (
                                    <li key={t} className="relative">
                                        <span className={`absolute -left-[33px] top-0.5 size-4 rounded-full border-2 border-white ${i === 0 ? 'bg-primary' : 'bg-neutral-300'}`} aria-hidden />
                                        <div className="font-semibold">{t}</div>
                                        <div className="text-sm text-neutral-600">{d}</div>
                                    </li>
                                ))}
                            </ol>
                        </Card>

                        <Card>
                            <h2 className="mb-2 font-bold">Informations pratiques</h2>
                            <ul className="grid gap-1 text-sm">{instructions.map((l) => <li key={l}>{linkify(l)}</li>)}</ul>
                        </Card>
                    </>
                )}

                <Card>
                    <h2 className="mb-1 font-bold">Vos produits et consignes</h2>
                    {b.can_edit ? (
                        <form onSubmit={(e) => { e.preventDefault(); post(route('track.update', token), { forceFormData: true, preserveScroll: true }); }} className="grid gap-4">
                            <p className="text-sm text-neutral-600">Vous pouvez les modifier jusqu’à {b.edit_deadline_hours} h avant le live.</p>
                            <ProductsEditor rows={data.products} onChange={(products) => setData('products', products)} max={b.package.max_products} errors={err} />
                            <Field label={`Consignes pour l’animateur (${data.host_notes.length}/500)`} error={err.host_notes}>
                                <textarea className={inputCls} rows={3} maxLength={500} value={data.host_notes} onChange={(e) => setData('host_notes', e.target.value)} />
                            </Field>
                            <Button disabled={processing} className="justify-self-start">Enregistrer mes modifications</Button>
                        </form>
                    ) : (
                        <div className="grid gap-2">
                            <p className="text-sm text-neutral-600">Les modifications sont closes {b.edit_deadline_hours} h avant le live. Écrivez-nous sur WhatsApp en cas de besoin.</p>
                            {b.products.map((p) => (
                                <div key={p.id} className="flex items-center gap-3 text-sm">
                                    {p.photo_url && <img src={p.photo_url} alt="" loading="lazy" className="h-12 w-12 rounded object-cover" />}
                                    <span><b>{p.name}</b> — {fcfa(p.live_price)} (au lieu de {fcfa(p.price)}) × {p.quantity}</span>
                                </div>
                            ))}
                            {b.host_notes && <p className="text-sm"><b>Consignes :</b> {b.host_notes}</p>}
                        </div>
                    )}
                </Card>
                <p className="text-xs text-neutral-500">Conservez cette page : son adresse est votre lien de suivi personnel, envoyé aussi par e-mail.</p>
            </div>
        </SiteLayout>
    );
}
