import { PhoneInput } from '@/components/country-fields';
import { Card, inputCls } from '@/components/hoc';
import { Avatar, CategoryTiles, CountryBubbles, LiveCategory, LiveDto, OnAirBadge, useAutoRefresh, WindowBadge, WindowState } from '@/components/live';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { buildPhone, Country, splitPhone } from '@/lib/countries';
import { fcfa, fmtLome, fmtTime, tiktokHandle } from '@/lib/format';
import { LivePhone, PhoneLive } from '@/components/live-phone';
import { Shared } from '@/lib/layout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Camera, Check, ClipboardPaste, Clock, Hourglass, MessageCircle } from 'lucide-react';
import { FormEvent, ReactNode, useCallback, useState } from 'react';

type Offer = { price: number; days: number; quota: number; per_day: number; display_minutes: number; extend_minutes: number; pending_minutes: number };
type Current = LiveDto & { status: 'EN_ATTENTE' | 'EN_LIGNE'; extended: boolean; submitted_at: string };
type Props = {
    guest: boolean; offer: Offer; window: WindowState;
    subscription?: { reference: string; ends_at: string; quota: number; used: number; remaining: number } | null;
    exhausted?: { ends_at: string } | null;
    payment_pending?: boolean;
    blocker?: { code: string; message: string } | null;
    current?: Current | null;
    history?: { id: number; status: string; name: string; date: string; reason: string | null }[];
    prefill?: { seller_name: string; phone: string | null; country: string; category: string; tiktok_url: string; photo: string | null };
    countries?: Country[]; categories?: LiveCategory[];
};

/** Statut d'un live passé : libellé et couleur de la pastille. */
const STATUS: Record<string, [string, string]> = {
    EN_ATTENTE: ['En vérification', 'bg-amber-500'], EN_LIGNE: ['Affiché', 'bg-red-600'], TERMINE: ['Terminé', 'bg-green-600'],
    REFUSE: ['Refusé', 'bg-neutral-900'], EXPIRE: ['Non traité, non décompté', 'bg-neutral-300'],
};

/** Ce que comprend l'abonnement. */
function OfferCard({ offer, action }: { offer: Offer; action: ReactNode }) {
    const items = [
        `${offer.quota} lives mis en avant`,
        `${offer.per_day} live par jour au plus`,
        `Affiché ${offer.display_minutes / 60} h, prolongeable de ${offer.extend_minutes} min`,
        'Un live refusé n’est pas décompté',
    ];
    return (
        <Card className="grid gap-5 p-6">
            <div>
                <div className="text-xs font-semibold uppercase tracking-widest text-neutral-500">Abonnement Lives</div>
                <div className="mt-1 text-3xl font-extrabold">{fcfa(offer.price)} <span className="text-base font-medium text-neutral-500">pour {offer.days} jours</span></div>
            </div>
            <ul className="grid gap-2.5">
                {items.map((i) => <li key={i} className="flex items-start gap-2.5"><Check className="mt-0.5 size-4 shrink-0 text-[#B85600]" aria-hidden />{i}</li>)}
            </ul>
            {action}
        </Card>
    );
}

/** Étapes numérotées (sans pictogramme). */
function Steps({ items }: { items: [string, string][] }) {
    return (
        <ol className="grid gap-3 sm:grid-cols-3">
            {items.map(([t, s], i) => (
                <li key={t} className="flex gap-3 rounded-xl border bg-white p-4">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#1F1F1F] text-sm font-bold text-white">{i + 1}</span>
                    <span><b className="block">{t}</b><span className="text-sm text-neutral-600">{s}</span></span>
                </li>
            ))}
        </ol>
    );
}

export default function MyLive(props: Props) {
    const { offer, window: w, guest } = props;
    const { site } = usePage<Shared>().props;
    const wa = site.whatsapp ? `https://wa.me/${site.whatsapp.replace(/\D/g, '')}?text=${encodeURIComponent('Bonjour, j’ai besoin d’aide pour mettre mon live en avant sur OCD Live.')}` : null;
    const reload = useCallback(() => router.reload({ only: ['current', 'blocker', 'subscription', 'payment_pending', 'history', 'window', 'exhausted'] }), []);
    useAutoRefresh(reload, props.current?.status === 'EN_ATTENTE' || props.payment_pending ? 10_000 : 60_000);

    if (guest) {
        return (
            <SiteLayout>
                <Head title="Mettre mon live en avant" />
                <h1 className="text-3xl font-extrabold">Mettez votre live TikTok en avant</h1>
                <p className="mt-2 max-w-2xl text-neutral-600">Pendant votre live, des acheteurs de toute l’Afrique vous trouvent sur OCD Live, classé par pays et par catégorie.</p>
                <div className="my-6"><Steps items={[['Abonnez-vous', `${fcfa(offer.price)} pour ${offer.days} jours`], ['Lancez votre live', 'Sur TikTok, comme d’habitude'], ['Envoyez le lien', 'L’équipe vérifie, puis vous affiche']]} /></div>
                <div className="grid gap-6 md:grid-cols-2">
                    <OfferCard offer={offer} action={null} />
                    <Card className="grid content-start gap-3 p-6">
                        <h2 className="text-xl font-bold">Pour commencer</h2>
                        <p className="text-neutral-600">Créez votre compte vendeur en une minute, ou connectez-vous.</p>
                        <Button asChild size="lg"><Link href={route('account.register')}>Créer mon compte vendeur</Link></Button>
                        <Button asChild size="lg" variant="soft"><Link href={route('login')}>J’ai déjà un compte</Link></Button>
                        <WindowBadge window={w} />
                    </Card>
                </div>
            </SiteLayout>
        );
    }

    const { subscription: sub, exhausted, payment_pending, blocker, current, history = [], prefill, countries = [], categories = [] } = props;
    const phoneLive: PhoneLive | null = current
        ? { id: current.id, name: current.name, handle: current.handle, photo: current.photo, country: current.country, category: current.category, since: current.since }
        : prefill ? { name: prefill.seller_name || 'Votre boutique', handle: tiktokHandle(prefill.tiktok_url).replace('@', '') || null, photo: prefill.photo, country: prefill.country, category: prefill.category } : null;
    const subscribe = () => router.post(route('live.subscribe'));

    return (
        <SiteLayout>
            <Head title="Mon live" />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-3 border-b pb-4">
                <h1 className="text-3xl font-extrabold">Mon live</h1>
                <Link href={route('lives')} className="text-sm font-medium text-[#B85600] hover:underline">Voir tous les lives en direct</Link>
            </div>

            <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                <div className="grid content-start gap-6">
                    {current && <CurrentLive live={current} offer={offer} />}

                    {sub && !current && !blocker && <SubmitForm {...props} />}

                    {blocker && !current && blocker.code !== 'subscription' && blocker.code !== 'quota' && (
                        <Card className="flex items-start gap-3 p-5">
                            <Clock className="mt-0.5 size-5 shrink-0 text-neutral-500" aria-hidden />
                            <div><p className="font-semibold">{blocker.message}</p>{blocker.code === 'closed' && <WindowBadge window={w} className="mt-3" />}</div>
                        </Card>
                    )}

                    {!sub && (
                        payment_pending ? (
                            <Card className="flex items-start gap-3 border-amber-300 bg-amber-50 p-5">
                                <Hourglass className="mt-0.5 size-5 shrink-0 text-amber-700" aria-hidden />
                                <div>
                                    <p className="font-semibold">Paiement en cours de vérification</p>
                                    <p className="text-sm text-neutral-700">Cette page se met à jour toute seule. Paiement abandonné ? <button type="button" className="font-medium text-[#B85600] underline" onClick={subscribe}>Payer à nouveau</button></p>
                                </div>
                            </Card>
                        ) : (
                            <OfferCard offer={offer} action={
                                <div className="grid gap-2">
                                    {exhausted && <p className="rounded-md bg-amber-50 p-3 text-sm text-amber-900">Vous avez utilisé tous les lives de votre abonnement. Renouvelez-le pour continuer.</p>}
                                    <Button size="lg" onClick={subscribe}>{exhausted ? 'Renouveler' : 'Payer'} {fcfa(offer.price)}</Button>
                                    <p className="text-center text-xs text-neutral-500">Flooz, T-Money, Mobile Money ou carte bancaire</p>
                                </div>
                            } />
                        )
                    )}

                    {history.length > 0 && (
                        <Card className="p-5">
                            <h2 className="mb-2 font-bold">Mes derniers lives</h2>
                            <ul className="divide-y text-sm">
                                {history.map((h) => (
                                    <li key={h.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 py-2.5">
                                        <span className={`size-2 rounded-full ${STATUS[h.status]?.[1] ?? 'bg-neutral-300'}`} aria-hidden />
                                        <span className="font-medium">{STATUS[h.status]?.[0] ?? h.status}</span>
                                        <span className="text-neutral-500">{fmtLome(h.date)}</span>
                                        {h.reason && <span className="w-full pl-5 text-neutral-700">Motif : {h.reason}</span>}
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}
                </div>

                <aside className="grid content-start gap-4">
                    {phoneLive && (
                        <Card className="p-5">
                            <h2 className="mb-3 text-center font-bold">Votre live, vu par le public</h2>
                            <LivePhone live={phoneLive} countries={countries} categories={categories} className="w-56" />
                        </Card>
                    )}
                    {sub && (
                        <Card className="grid gap-2 p-5">
                            <div className="text-xs font-semibold uppercase tracking-widest text-green-700">Abonnement actif</div>
                            <div className="text-4xl font-extrabold">{sub.remaining}<span className="text-lg font-medium text-neutral-400"> / {sub.quota}</span></div>
                            <div className="text-sm text-neutral-600">lives restants</div>
                            <div className="h-2 overflow-hidden rounded-full bg-neutral-200" role="progressbar" aria-label="Lives restants" aria-valuemin={0} aria-valuemax={sub.quota} aria-valuenow={sub.remaining}>
                                <div className="h-full bg-primary" style={{ width: `${(sub.remaining / sub.quota) * 100}%` }} />
                            </div>
                            <div className="text-xs text-neutral-500">Jusqu’au {fmtLome(sub.ends_at)}</div>
                        </Card>
                    )}
                    <WindowBadge window={w} />
                    {wa && <Button asChild variant="soft"><a href={wa} target="_blank" rel="noreferrer"><MessageCircle className="size-4" aria-hidden />Besoin d’aide ? WhatsApp</a></Button>}
                </aside>
            </div>
        </SiteLayout>
    );
}

function CurrentLive({ live, offer }: { live: Current; offer: Offer }) {
    const post = (name: string) => router.post(route(name, live.id), {}, { preserveScroll: true });
    if (live.status === 'EN_ATTENTE') {
        return (
            <Card className="grid gap-3 border-amber-300 bg-amber-50 p-5">
                <div className="flex items-start gap-3">
                    <Hourglass className="mt-1 size-5 shrink-0 text-amber-700" aria-hidden />
                    <div>
                        <h2 className="text-xl font-bold">L’équipe vérifie votre live</h2>
                        <p className="text-neutral-700">Restez en live sur TikTok : il apparaîtra sur OCD Live dans quelques minutes. Sans réponse sous {offer.pending_minutes} min, la demande s’annule et n’est pas décomptée.</p>
                    </div>
                </div>
                <button type="button" className="justify-self-start pl-8 text-sm text-neutral-600 underline" onClick={() => post('live.finish')}>Annuler ma demande</button>
            </Card>
        );
    }
    return (
        <Card className="grid gap-4 border-red-200 p-5">
            <div className="flex items-center gap-4">
                <Avatar name={live.name} photo={live.photo} />
                <div>
                    <OnAirBadge />
                    <h2 className="mt-1.5 text-xl font-bold">Votre live est affiché sur OCD Live</h2>
                    <p className="text-neutral-700">Jusqu’à <b>{fmtTime(live.until!)}</b>.</p>
                </div>
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
                {!live.extended && <Button size="lg" onClick={() => post('live.extend')}>Je suis encore en live (+{offer.extend_minutes} min)</Button>}
                <Button size="lg" variant="outline" onClick={() => post('live.finish')}>J’ai terminé mon live</Button>
            </div>
        </Card>
    );
}

function SubmitForm({ prefill, countries = [], categories = [] }: Props) {
    const p = prefill!;
    const initial = splitPhone(p.phone, countries, p.country);
    const [phoneCode, setPhoneCode] = useState(initial.code);
    const [national, setNational] = useState(initial.national);
    const { data, setData, post, processing, errors, transform } = useForm({
        seller_name: p.seller_name, phone: p.phone ?? '', country: p.country, category: p.category, tiktok_url: p.tiktok_url, photo: null as File | null,
    });
    const err = errors as Record<string, string>;
    const [preview, setPreview] = useState<string | null>(p.photo);
    const dial = countries.find((c) => c.code === phoneCode)?.dial ?? '';
    transform((d) => ({ ...d, phone: buildPhone(dial, national) }));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('live.submit'), { forceFormData: true, preserveScroll: true });
    };
    const paste = async () => {
        try {
            const t = await navigator.clipboard.readText();
            if (t) setData('tiktok_url', t.trim());
        } catch { /* le navigateur refuse : le vendeur colle à la main */ }
    };
    const label = 'text-sm font-semibold text-neutral-900';

    return (
        <Card className="p-6">
            <h2 className="text-2xl font-extrabold">Envoyer mon live</h2>
            <p className="mb-6 mt-1 text-neutral-600">Lancez d’abord votre live sur TikTok, puis remplissez ce formulaire.</p>
            <form onSubmit={submit} className="grid gap-6">
                <div className="grid gap-2">
                    <label htmlFor="tiktok_url" className={label}>Lien de votre live TikTok</label>
                    <div className="flex gap-2">
                        <input id="tiktok_url" className={`${inputCls} !py-3 !text-base`} placeholder="Collez le lien, ou écrivez @votre_compte" value={data.tiktok_url} onChange={(e) => setData('tiktok_url', e.target.value)} autoComplete="off" />
                        <Button type="button" variant="outline" className="h-auto" onClick={paste}><ClipboardPaste className="size-4" aria-hidden />Coller</Button>
                    </div>
                    {err.tiktok_url && <span className="text-sm text-red-600">{err.tiktok_url}</span>}
                    <details className="rounded-lg border bg-neutral-50 px-3 py-2 text-sm">
                        <summary className="cursor-pointer font-medium text-neutral-700">Où trouver le lien ?</summary>
                        <ol className="mt-2 list-decimal space-y-1 pl-5 text-neutral-700">
                            <li>Dans TikTok, lancez votre live.</li>
                            <li>Touchez « Partager ».</li>
                            <li>Touchez « Copier le lien », puis revenez ici et collez-le.</li>
                        </ol>
                        <p className="mt-2 text-neutral-600">Vous pouvez aussi écrire seulement votre compte, par exemple <b>@chez_maman_marie</b>.</p>
                    </details>
                </div>

                <div className="grid gap-2">
                    <span className={label}>Ce que vous vendez</span>
                    <CategoryTiles categories={categories} value={data.category || null} onChange={(c) => setData('category', c ?? '')} name="Ce que vous vendez" />
                    {err.category && <span className="text-sm text-red-600">{err.category}</span>}
                </div>

                <div className="grid gap-2">
                    <span className={label}>Votre pays</span>
                    <CountryBubbles countries={countries} value={data.country} onChange={(c) => setData('country', c ?? p.country)} />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="grid gap-1 text-sm">
                        <span className="font-medium">Votre nom ou celui de votre boutique</span>
                        <input className={inputCls} value={data.seller_name} onChange={(e) => setData('seller_name', e.target.value)} />
                        {err.seller_name && <span className="text-red-600">{err.seller_name}</span>}
                    </label>
                    <PhoneInput id="live-phone" label="Votre WhatsApp" countries={countries} code={phoneCode} national={national}
                        onChange={(c, n) => { setPhoneCode(c); setNational(n); }} error={err.phone} />
                </div>

                <div className="flex items-center gap-3">
                    {preview ? <img src={preview} alt="" className="size-14 rounded-full object-cover" /> : <span className="flex size-14 items-center justify-center rounded-full bg-neutral-100"><Camera className="size-5 text-neutral-500" aria-hidden /></span>}
                    <label className="text-sm">
                        <span className="block font-medium">Votre photo ou logo (facultatif)</span>
                        <input type="file" accept="image/*" className="mt-1 text-sm" onChange={(e) => { const f = e.target.files?.[0] ?? null; setData('photo', f); if (f) setPreview(URL.createObjectURL(f)); }} />
                    </label>
                    {err.photo && <span className="text-sm text-red-600">{err.photo}</span>}
                </div>

                <Button size="lg" disabled={processing} className="h-12 text-base">Envoyer mon live</Button>
            </form>
        </Card>
    );
}
