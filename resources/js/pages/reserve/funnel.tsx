import { CountrySelect, PhoneInput } from '@/components/country-fields';
import { Card, Field, ModeBadge, PackageCard, Pkg, SlotDto, SlotPicker, inputCls } from '@/components/hoc';
import { emptyProduct, ProductRow, ProductsEditor } from '@/components/products-editor';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { buildPhone, Country, flag, splitPhone } from '@/lib/countries';
import { fcfa, fmtLome, MODE_LABEL } from '@/lib/format';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Profile = { name: string; email: string; phone: string | null; country: string | null };
type ShopProduct = { id: number; name: string; price: number; live_price: number; quantity: number; photo_url: string | null };
type Shop = { id: number; name: string; city: string; activity_domain: string; activity_description: string; tiktok: string | null; products: ShopProduct[] };

type Form = {
    package_id: number | null; slot_id: number | null; mode: string; country: string; shop_id: number | null; full_name: string; phone: string; email: string;
    shop_name: string; city: string; activity_domain: string; activity_description: string; tiktok: string; host_notes: string;
    create_account: boolean; password: string; consent: boolean; products: ProductRow[];
};

const STEPS = ['Package', 'Créneau', 'Informations', 'Paiement'];
const INFO_FIELDS = ['full_name', 'phone', 'email', 'shop_id', 'shop_name', 'city', 'activity_domain', 'activity_description', 'tiktok', 'host_notes', 'create_account', 'password', 'consent', 'products'];

/** Produits mémorisés de la boutique, prêts à être repris dans la réservation (photos comprises). */
const rowsFromShop = (shop: Shop, max: number): ProductRow[] =>
    shop.products.length
        ? shop.products.slice(0, max).map((p) => ({ source_id: p.id, name: p.name, price: String(p.price), live_price: String(p.live_price), quantity: String(p.quantity), photo: null, photo_url: p.photo_url }))
        : [emptyProduct()];

export default function Funnel({ packages, slots, pack, hold_minutes, domains, countries, home_country, profile, shops }: {
    packages: Pkg[]; slots: SlotDto[]; pack: string | null; hold_minutes: number; domains: string[]; countries: Country[]; home_country: string; profile: Profile | null; shops: Shop[];
}) {
    const { auth } = usePage().props as unknown as { auth: { user: unknown } };
    const preselected = packages.find((p) => p.code === pack);
    // Un package déjà choisi (bouton « Choisir ce package » de la page des packages) fait passer directement au créneau.
    const [step, setStep] = useState(preselected ? 2 : 1);
    const [chosen, setChosen] = useState(!!preselected);
    const startCountry = profile?.country ?? home_country;
    const initPhone = splitPhone(profile?.phone, countries, startCountry);
    const [phoneCode, setPhoneCode] = useState(initPhone.code);
    const [national, setNational] = useState(initPhone.national);
    const dial = (code: string) => countries.find((c) => c.code === code)?.dial ?? '';

    const firstShop = profile ? shops[0] : undefined;
    const [shopId, setShopId] = useState<number | 'new'>(firstShop ? firstShop.id : 'new');

    const { data, setData, post, processing, errors, transform } = useForm<Form>({
        package_id: (preselected ?? packages[0])?.id ?? null, slot_id: null, mode: '', country: startCountry, shop_id: firstShop?.id ?? null,
        full_name: profile?.name ?? '', phone: profile?.phone ?? '', email: profile?.email ?? '',
        shop_name: firstShop?.name ?? '', city: firstShop?.city ?? '', activity_domain: firstShop?.activity_domain ?? '',
        activity_description: firstShop?.activity_description ?? '', tiktok: firstShop?.tiktok ?? '',
        host_notes: '', create_account: false, password: '', consent: false,
        products: firstShop ? rowsFromShop(firstShop, (preselected ?? packages[0])?.max_products ?? 10) : [emptyProduct()],
    });
    const err = errors as Record<string, string>;
    const pkg = packages.find((p) => p.id === data.package_id);
    const slot = slots.find((s) => s.id === data.slot_id);
    const country = countries.find((c) => c.code === data.country);
    const savedShop = shops.find((s) => s.id === shopId);

    transform((d) => ({ ...d, products: d.products.map((p) => ({ ...p, photo: p.photo ?? undefined })) }));

    /** Le passage sur place n'est possible que depuis le Togo ; ailleurs, uniquement à distance. */
    const modesFor = (s: SlotDto | undefined, code: string) => (s ? s.modes.filter((m) => m === 'A_DISTANCE' || code === home_country) : []);
    const allowed = modesFor(slot, data.country);

    const missing = (): string | null => {
        if (step === 2) {
            if (!slot) return 'Choisissez un créneau.';
            if (allowed.length === 0) return 'Ce créneau n’accepte que les passages sur place, réservés au Togo : choisissez un autre créneau.';
            return !data.mode ? 'Choisissez où vous passez.' : null;
        }
        const need: [string, string][] = [['shop_name', 'le nom de votre boutique'], ['city', 'la ville de votre boutique'], ['activity_domain', 'votre domaine d’activité'], ['activity_description', 'ce que vous vendez']];
        if (!profile) need.unshift(['full_name', 'votre nom'], ['phone', 'votre numéro WhatsApp'], ['email', 'votre e-mail']);
        const m = need.find(([k]) => !String(data[k as keyof Form]).trim());
        if (m) return `Renseignez ${m[1]}.`;
        if (data.products.some((p) => !p.name.trim() || p.price === '' || p.live_price === '' || !p.quantity)) return 'Complétez chaque produit (nom, prix, prix live, quantité).';
        if (data.create_account && data.password.length < 8) return 'Choisissez un mot de passe d’au moins 8 caractères pour créer votre compte.';
        return !data.consent ? 'Acceptez l’utilisation de vos données pour continuer.' : null;
    };
    const [hint, setHint] = useState<string | null>(null);
    const go = (n: number) => { setHint(null); setStep(n); window.scrollTo({ top: 0 }); };
    const next = () => { const m = missing(); setHint(m); if (!m) go(step + 1); };

    // Le mode se règle tout seul quand il n'y a qu'un choix possible (ex. : vendeur hors du Togo → à distance).
    useEffect(() => {
        if (!slot) return;
        if (allowed.length === 1 && data.mode !== allowed[0]) setData('mode', allowed[0]);
        else if (allowed.length !== 1 && data.mode && !allowed.includes(data.mode)) setData('mode', '');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [slot?.id, data.country]);

    // Les créneaux pris par d'autres vendeurs apparaissent « Occupé » sans recharger la page.
    useEffect(() => {
        const t = setInterval(() => router.reload({ only: ['slots'] }), 30000);
        return () => clearInterval(t);
    }, []);
    // Si le créneau choisi vient d'être pris par quelqu'un d'autre, on le dit tout de suite.
    useEffect(() => {
        if (data.slot_id && !slots.find((s) => s.id === data.slot_id)?.available) {
            setData((d) => ({ ...d, slot_id: null, mode: '' }));
            go(2);
            setHint('Ce créneau vient d’être pris par un autre vendeur : choisissez-en un autre.');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [slots]);

    const choosePack = (p: Pkg) => {
        setChosen(true);
        setData((d) => ({ ...d, package_id: p.id, products: d.products.slice(0, p.max_products) }));
        go(2);
    };
    const changeCountry = (code: string) => {
        setData('country', code);
        if (!national) setPhoneCode(code); // le téléphone suit le pays tant que le numéro n'est pas saisi
    };
    const changePhone = (code: string, n: string) => {
        setPhoneCode(code);
        setNational(n);
        setData('phone', buildPhone(dial(code), n));
    };
    /** Choix d'une boutique enregistrée (ses informations et ses produits sont repris) ou d'une nouvelle boutique. */
    const chooseShop = (value: string) => {
        const id = value === 'new' ? 'new' : Number(value);
        setShopId(id);
        const s = shops.find((x) => x.id === id);
        setData((d) => s
            ? { ...d, shop_id: s.id, shop_name: s.name, city: s.city, activity_domain: s.activity_domain, activity_description: s.activity_description, tiktok: s.tiktok ?? '', products: rowsFromShop(s, pkg?.max_products ?? 10) }
            : { ...d, shop_id: null, shop_name: '', city: '', activity_domain: '', activity_description: '', tiktok: '', products: [emptyProduct()] });
    };

    const submit = () => {
        post(route('reserve.start'), {
            forceFormData: true,
            onError: (e) => go(Object.keys(e).some((k) => INFO_FIELDS.includes(k.split('.')[0])) ? 3 : 2),
        });
    };

    const shopFields = (
        <section className="grid gap-3 md:grid-cols-2">
            <Field label="Nom de la boutique" error={err.shop_name}><input className={inputCls} value={data.shop_name} onChange={(e) => setData('shop_name', e.target.value)} /></Field>
            <Field label="Ville de la boutique" error={err.city}><input className={inputCls} autoComplete="address-level2" value={data.city} onChange={(e) => setData('city', e.target.value)} /></Field>
            <Field label="Domaine d’activité" error={err.activity_domain}>
                <select className={inputCls} value={data.activity_domain} onChange={(e) => setData('activity_domain', e.target.value)}>
                    <option value="">Choisir…</option>
                    {domains.map((d) => <option key={d}>{d}</option>)}
                </select>
            </Field>
            <Field label="Compte TikTok (facultatif)" error={err.tiktok}><input className={inputCls} placeholder="@votre_compte" value={data.tiktok} onChange={(e) => setData('tiktok', e.target.value)} /></Field>
            <div className="md:col-span-2">
                <Field label={`Que vendez-vous ? (${data.activity_description.length}/300)`} error={err.activity_description}>
                    <textarea className={inputCls} rows={3} maxLength={300} placeholder="Ex. : robes et tissus wax faits main, livraison à Lomé" value={data.activity_description} onChange={(e) => setData('activity_description', e.target.value)} />
                </Field>
            </div>
        </section>
    );

    return (
        <SiteLayout title="Réserver mon passage">
            <Head title="Réserver" />
            {!auth.user && step <= 2 && (
                <p className="-mt-2 mb-4 text-sm text-neutral-600">
                    Déjà un compte ? <Link className="font-semibold text-[#B85600] underline" href={route('login')}>Connectez-vous</Link> pour retrouver votre boutique et vos produits.
                </p>
            )}
            <ol className="mb-6 flex gap-1.5 text-[11px] sm:gap-2 sm:text-sm">
                {STEPS.map((s, i) => (
                    <li key={s} className={`flex-1 rounded px-1 py-1 text-center sm:px-2 ${i + 1 === step ? 'bg-primary text-white' : i + 1 < step ? 'bg-green-100' : 'bg-neutral-200'}`}>{i + 1}. {s}</li>
                ))}
            </ol>

            {/* Rappel du package choisi : les autres packages disparaissent, « Changer » y ramène. */}
            {step > 1 && pkg && (
                <Card className="mb-5 flex items-center justify-between gap-3 text-sm">
                    <span><span className="text-neutral-500">Package choisi :</span> <b>{pkg.name}</b> · {fcfa(pkg.price)} · {pkg.duration_minutes} min</span>
                    {step < 4 && <button type="button" className="shrink-0 font-semibold text-[#B85600] underline" onClick={() => go(1)}>Changer</button>}
                </Card>
            )}

            {step === 1 && (
                <section>
                    <h2 className="mb-1 text-lg font-bold">Choisissez votre package</h2>
                    <p className="mb-3 text-sm text-neutral-600">Touchez un package pour passer au choix du créneau.</p>
                    <div className="grid gap-3 md:grid-cols-3">
                        {packages.map((p) => (
                            <button key={p.id} type="button" className="text-left" aria-label={`Choisir ${p.name}`} onClick={() => choosePack(p)}>
                                <PackageCard pkg={p} selected={chosen && p.id === data.package_id} />
                            </button>
                        ))}
                    </div>
                </section>
            )}

            {step === 2 && (
                <div className="grid gap-6 pb-24">
                    <section className="max-w-md">
                        <h2 className="mb-2 text-lg font-bold">Où êtes-vous ?</h2>
                        <CountrySelect id="country" value={data.country} countries={countries} onChange={changeCountry} />
                        {err.country && <p className="mt-1 text-sm text-red-600">{err.country}</p>}
                        <p className="mt-1 text-xs text-neutral-500">Depuis le Togo, vous passez au studio ou à distance. Depuis un autre pays, vous passez à distance, sur TikTok.</p>
                    </section>
                    <section>
                        <h2 className="mb-2 text-lg font-bold">Votre créneau</h2>
                        <SlotPicker slots={slots} value={data.slot_id} onSelect={(s) => { setHint(null); setData('slot_id', s.id); if (modesFor(s, data.country).length > 1) setTimeout(() => document.getElementById('mode')?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 80); }} />
                    </section>
                    {slot && (
                        <section id="mode">
                            <h2 className="mb-2 text-lg font-bold">Comment passez-vous ?</h2>
                            {allowed.length === 0 ? (
                                <Card className="border-amber-300 bg-amber-50 text-sm">Ce créneau n’accepte que les passages sur place, réservés aux vendeurs au Togo. Choisissez un autre créneau.</Card>
                            ) : (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {allowed.map((m) => (
                                        <button key={m} type="button" onClick={() => setData('mode', m)} className={`rounded-lg border p-4 text-left ${data.mode === m ? 'border-primary ring-2 ring-primary' : 'bg-white'}`}>
                                            <div className="font-bold">{MODE_LABEL[m]}</div>
                                            <div className="text-sm text-neutral-600">{m === 'SUR_PLACE' ? 'Au studio OCD Live, au Togo.' : 'Depuis chez vous, invité sur le live TikTok.'}</div>
                                        </button>
                                    ))}
                                </div>
                            )}
                            {err.mode && <p className="mt-2 text-sm text-red-600">{err.mode}</p>}
                        </section>
                    )}
                </div>
            )}

            {step === 3 && pkg && (
                <div className="grid gap-5">
                    <Card className="text-sm">
                        {slot && fmtLome(slot.starts_at)} <ModeBadge mode={data.mode} />
                        {' '}<button type="button" className="text-[#B85600] underline" onClick={() => go(2)}>Modifier</button>
                    </Card>

                    {profile ? (
                        <Card className="text-sm">
                            Réservation au nom de <b>{profile.name}</b> · {profile.phone} · {profile.email}.{' '}
                            <Link className="text-[#B85600] underline" href={route('account.home')}>Modifier mes coordonnées</Link>
                        </Card>
                    ) : (
                        <section className="grid gap-3 md:grid-cols-2">
                            <Field label="Nom complet" error={err.full_name}><input className={inputCls} autoComplete="name" value={data.full_name} onChange={(e) => setData('full_name', e.target.value)} /></Field>
                            <PhoneInput label="Téléphone WhatsApp" countries={countries} code={phoneCode} national={national} onChange={changePhone} error={err.phone} />
                            <Field label="E-mail" error={err.email}><input className={inputCls} type="email" autoComplete="email" value={data.email} onChange={(e) => setData('email', e.target.value)} /></Field>
                        </section>
                    )}

                    <section className="grid gap-3">
                        <h2 className="text-lg font-bold">Votre boutique</h2>
                        {profile && shops.length > 0 && (
                            <Field label="Boutique de cette réservation">
                                <select className={inputCls} value={String(shopId)} onChange={(e) => chooseShop(e.target.value)}>
                                    {shops.map((s) => <option key={s.id} value={s.id}>{s.name} — {s.city}</option>)}
                                    <option value="new">+ Nouvelle boutique</option>
                                </select>
                            </Field>
                        )}
                        {savedShop ? (
                            <Card className="text-sm">
                                <div><b>{savedShop.name}</b> · {savedShop.city}</div>
                                <div className="text-neutral-600">{savedShop.activity_domain} — {savedShop.activity_description}</div>
                                <Link className="mt-1 inline-block text-[#B85600] underline" href={route('account.home')}>Modifier cette boutique</Link>
                            </Card>
                        ) : shopFields}
                    </section>

                    <section>
                        <h2 className="mb-1 text-lg font-bold">Vos produits <span className="text-sm font-normal text-neutral-500">(1 à {pkg.max_products})</span></h2>
                        {savedShop && savedShop.products.length > 0 && <p className="mb-2 text-sm text-neutral-600">Repris de votre dernière réservation : modifiez, retirez ou ajoutez des produits.</p>}
                        <ProductsEditor rows={data.products} onChange={(products) => setData('products', products)} max={pkg.max_products} errors={err} />
                    </section>
                    <Field label={`Consignes pour l’animateur (facultatif, ${data.host_notes.length}/500)`} error={err.host_notes}>
                        <textarea className={inputCls} rows={3} maxLength={500} value={data.host_notes} onChange={(e) => setData('host_notes', e.target.value)} />
                    </Field>
                    {!auth.user && (
                        <Card className="grid gap-2">
                            <label className="flex items-start gap-2 text-sm">
                                <input type="checkbox" className="mt-1" checked={data.create_account} onChange={(e) => setData('create_account', e.target.checked)} />
                                <span><b>Créer mon compte</b> pour retrouver ma boutique, mes produits et mes réservations, sans tout ressaisir la prochaine fois.</span>
                            </label>
                            {data.create_account && (
                                <Field label="Mot de passe (8 caractères minimum)" error={err.password}>
                                    <input type="password" className={inputCls} autoComplete="new-password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                                </Field>
                            )}
                        </Card>
                    )}
                    <label className="flex items-start gap-2 text-sm">
                        <input type="checkbox" className="mt-1" checked={data.consent} onChange={(e) => setData('consent', e.target.checked)} />
                        <span>J’accepte que mes données soient utilisées pour gérer ma réservation (<Link className="text-[#B85600] underline" href={route('legal')} target="_blank">confidentialité</Link>).</span>
                    </label>
                    {err.consent && <p className="text-sm text-red-600">{err.consent}</p>}
                </div>
            )}

            {step === 4 && pkg && slot && (
                <Card className="grid max-w-xl gap-2">
                    <h2 className="text-lg font-bold">Récapitulatif</h2>
                    <div>{pkg.name} — {pkg.duration_minutes} min, {data.products.length} produit(s)</div>
                    <div>{fmtLome(slot.starts_at)} <ModeBadge mode={data.mode} /></div>
                    <div>{data.shop_name} · {profile?.name ?? data.full_name}</div>
                    <div className="flag-font">{country && <>{flag(country.code)} {country.name} · </>}{profile?.phone ?? data.phone}</div>
                    {data.create_account && !auth.user && <div className="text-sm text-neutral-600">Un compte sera créé avec l’e-mail {data.email}.</div>}
                    <div className="text-2xl font-extrabold text-[#B85600]">{fcfa(pkg.price)}</div>
                    <p className="text-sm text-neutral-600">Paiement par Flooz ou T-Money. Votre créneau est bloqué {hold_minutes} minutes pendant le paiement ; il est confirmé dès que le paiement est reçu.</p>
                    <Button disabled={processing} onClick={submit} size="lg">{processing ? 'Redirection vers le paiement…' : `Payer ${fcfa(pkg.price)}`}</Button>
                </Card>
            )}

            {step === 2 ? (
                <div className="fixed inset-x-0 bottom-0 z-30 border-t bg-white p-3 shadow-[0_-2px_8px_rgba(0,0,0,0.08)] print:hidden">
                    <div className="mx-auto max-w-6xl">
                        {hint && <p role="alert" className="mb-2 text-sm text-red-600">{hint}</p>}
                        <div className="flex items-center justify-between gap-3">
                            <div className="min-w-0 text-sm">
                                {slot ? <>{fmtLome(slot.starts_at)}{data.mode && <> · {MODE_LABEL[data.mode]}</>}</> : 'Choisissez un créneau.'}
                            </div>
                            <Button onClick={next}>Continuer</Button>
                        </div>
                    </div>
                </div>
            ) : step > 2 && (
                <>
                    {hint && <p role="alert" className="mt-4 text-sm text-red-600">{hint}</p>}
                    <div className="mt-6 flex gap-3">
                        <Button variant="outline" onClick={() => go(step - 1)}>Retour</Button>
                        {step < 4 && <Button onClick={next}>Continuer</Button>}
                    </div>
                </>
            )}
        </SiteLayout>
    );
}
