import { CountrySelect, PhoneInput } from '@/components/country-fields';
import { Card, Field, ModeBadge, StatusBadge, inputCls } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { buildPhone, Country, splitPhone } from '@/lib/countries';
import { fcfa, fmtLome } from '@/lib/format';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Profile = { name: string; email: string; phone: string | null; country: string | null };
type Shop = { id: number; name: string; city: string; activity_domain: string; activity_description: string; tiktok: string | null; products_count: number };
type B = { reference: string; status: string; mode: string; amount: number; shop: string; package: string; starts_at: string; tracking_token: string };

function ShopForm({ shop, domains, onDone }: { shop?: Shop; domains: string[]; onDone?: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: shop?.name ?? '', city: shop?.city ?? '', activity_domain: shop?.activity_domain ?? '', activity_description: shop?.activity_description ?? '', tiktok: shop?.tiktok ?? '',
    });
    const err = errors as Record<string, string>;
    return (
        <form onSubmit={(e) => { e.preventDefault(); post(shop ? route('account.shops.update', shop.id) : route('account.shops.store'), { preserveScroll: true, onSuccess: () => { if (!shop) reset(); onDone?.(); } }); }} className="grid gap-3">
            <Field label="Nom de la boutique" error={err.name}><input className={inputCls} value={data.name} onChange={(e) => setData('name', e.target.value)} /></Field>
            <Field label="Ville" error={err.city}><input className={inputCls} value={data.city} onChange={(e) => setData('city', e.target.value)} /></Field>
            <Field label="Domaine d’activité" error={err.activity_domain}>
                <select className={inputCls} value={data.activity_domain} onChange={(e) => setData('activity_domain', e.target.value)}>
                    <option value="">Choisir…</option>
                    {domains.map((d) => <option key={d}>{d}</option>)}
                </select>
            </Field>
            <Field label={`Que vendez-vous ? (${data.activity_description.length}/300)`} error={err.activity_description}>
                <textarea className={inputCls} rows={3} maxLength={300} value={data.activity_description} onChange={(e) => setData('activity_description', e.target.value)} />
            </Field>
            <Field label="Compte TikTok (facultatif)" error={err.tiktok}><input className={inputCls} placeholder="@votre_compte" value={data.tiktok} onChange={(e) => setData('tiktok', e.target.value)} /></Field>
            <Button disabled={processing} className="justify-self-start">{shop ? 'Enregistrer' : 'Ajouter la boutique'}</Button>
        </form>
    );
}

export default function Account({ profile, countries, domains, shops, bookings }: { profile: Profile; countries: Country[]; domains: string[]; shops: Shop[]; bookings: B[] }) {
    const country = profile.country ?? 'TG';
    const initial = splitPhone(profile.phone, countries, country);
    const [phoneCode, setPhoneCode] = useState(initial.code);
    const [national, setNational] = useState(initial.national);
    const [editing, setEditing] = useState<number | null>(null);
    const [adding, setAdding] = useState(shops.length === 0);
    const { data, setData, post, processing, errors } = useForm({ name: profile.name, country, phone: profile.phone ?? '' });
    const err = errors as Record<string, string>;
    const dial = (code: string) => countries.find((c) => c.code === code)?.dial ?? '';

    return (
        <SiteLayout title="Mon compte">
            <Head title="Mon compte" />
            <div className="grid gap-8 lg:grid-cols-2">
                <section className="grid content-start gap-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-bold">Mes réservations</h2>
                        <Button asChild size="sm"><Link href={route('reserve')}>Nouvelle réservation</Link></Button>
                    </div>
                    {bookings.length === 0 && <Card className="text-sm">Vous n’avez pas encore de réservation.</Card>}
                    {bookings.map((b) => (
                        <Card key={b.reference} className="grid gap-1 text-sm">
                            <div className="flex flex-wrap items-center gap-2"><b>{b.reference}</b><StatusBadge status={b.status} /><ModeBadge mode={b.mode} /></div>
                            <div>{b.shop} · {b.package} · {fcfa(b.amount)}</div>
                            <div>{fmtLome(b.starts_at)}</div>
                            <Link className="text-[#B85600] underline" href={route('track', b.tracking_token)}>Voir et modifier mes produits</Link>
                        </Card>
                    ))}
                </section>

                <div className="grid content-start gap-8">
                    <section>
                        <h2 className="mb-1 text-lg font-bold">Mes boutiques</h2>
                        <p className="mb-3 text-sm text-neutral-600">Enregistrées une seule fois : à chaque réservation, vous choisissez votre boutique et vos produits sont repris automatiquement.</p>
                        <div className="grid gap-3">
                            {shops.map((s) => (
                                <Card key={s.id} className="grid gap-2 text-sm">
                                    {editing === s.id ? (
                                        <ShopForm shop={s} domains={domains} onDone={() => setEditing(null)} />
                                    ) : (
                                        <>
                                            <div className="font-bold">{s.name} <span className="font-normal text-neutral-500">· {s.city}</span></div>
                                            <div className="text-neutral-600">{s.activity_domain} — {s.activity_description}</div>
                                            <div className="text-neutral-500">{s.products_count} produit(s) mémorisé(s)</div>
                                            <div className="flex gap-3">
                                                <button type="button" className="text-[#B85600] underline" onClick={() => setEditing(s.id)}>Modifier</button>
                                                <button type="button" className="text-red-600 underline" onClick={() => confirm(`Supprimer la boutique « ${s.name} » ? Vos réservations passées sont conservées.`) && router.delete(route('account.shops.delete', s.id), { preserveScroll: true })}>Supprimer</button>
                                            </div>
                                        </>
                                    )}
                                </Card>
                            ))}
                            {adding ? (
                                <Card><h3 className="mb-2 font-semibold">Nouvelle boutique</h3><ShopForm domains={domains} onDone={() => setAdding(false)} /></Card>
                            ) : (
                                <Button variant="outline" onClick={() => setAdding(true)}>+ Ajouter une boutique</Button>
                            )}
                        </div>
                    </section>

                    <section>
                        <h2 className="mb-3 text-lg font-bold">Mes coordonnées</h2>
                        <Card>
                            <form onSubmit={(e) => { e.preventDefault(); post(route('account.update'), { preserveScroll: true }); }} className="grid gap-3">
                                <Field label="E-mail"><input className={`${inputCls} bg-neutral-100`} value={profile.email} readOnly /></Field>
                                <Field label="Nom complet" error={err.name}><input className={inputCls} value={data.name} onChange={(e) => setData('name', e.target.value)} /></Field>
                                <Field label="Votre pays" error={err.country}><CountrySelect value={data.country} countries={countries} onChange={(c) => setData('country', c)} /></Field>
                                <PhoneInput label="Téléphone WhatsApp" countries={countries} code={phoneCode} national={national} error={err.phone}
                                    onChange={(code, n) => { setPhoneCode(code); setNational(n); setData('phone', buildPhone(dial(code), n)); }} />
                                <Button disabled={processing} className="justify-self-start">Enregistrer</Button>
                            </form>
                        </Card>
                    </section>
                </div>
            </div>
        </SiteLayout>
    );
}
