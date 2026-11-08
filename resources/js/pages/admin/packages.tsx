import { Card, Field, StatusBadge, inputCls } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { fcfa } from '@/lib/format';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Pkg = {
    id?: number; code: string; name: string; description: string; price: number; price_on_site: number | null; price_remote: number | null;
    credits_included: number; validity_days: number; includes_publication: boolean; publication_days: number | null;
    allowed_modes: string[]; is_active: boolean; display_order: number;
};

const EMPTY: Pkg = { code: '', name: '', description: '', price: 0, price_on_site: null, price_remote: null, credits_included: 1, validity_days: 90, includes_publication: false, publication_days: null, allowed_modes: ['SUR_PLACE', 'A_DISTANCE'], is_active: true, display_order: 0 };

function PackageForm({ initial, onDone }: { initial: Pkg; onDone: () => void }) {
    const { data, setData, post, processing, errors } = useForm<Pkg>(initial);
    const err = errors as Record<string, string>;
    const num = (k: keyof Pkg, nullable = false) => (e: React.ChangeEvent<HTMLInputElement>) => setData(k, (e.target.value === '' && nullable ? null : Number(e.target.value)) as never);
    return (
        <form onSubmit={(e) => { e.preventDefault(); post(initial.id ? route('admin.packages.update', initial.id) : route('admin.packages.store'), { onSuccess: onDone }); }}
            className="mt-3 grid gap-3 border-t pt-3 md:grid-cols-3">
            <Field label="Code" error={err.code}><input className={inputCls} value={data.code} onChange={(e) => setData('code', e.target.value)} required /></Field>
            <Field label="Nom" error={err.name}><input className={inputCls} value={data.name} onChange={(e) => setData('name', e.target.value)} required /></Field>
            <Field label="Ordre d’affichage"><input type="number" className={inputCls} value={data.display_order} onChange={num('display_order')} /></Field>
            <div className="md:col-span-3"><Field label="Description" error={err.description}><textarea className={inputCls} value={data.description} onChange={(e) => setData('description', e.target.value)} required /></Field></div>
            <Field label="Prix (FCFA)" error={err.price}><input type="number" min={0} className={inputCls} value={data.price} onChange={num('price')} /></Field>
            <Field label="Prix sur place (optionnel)"><input type="number" min={0} className={inputCls} value={data.price_on_site ?? ''} onChange={num('price_on_site', true)} /></Field>
            <Field label="Prix à distance (optionnel)"><input type="number" min={0} className={inputCls} value={data.price_remote ?? ''} onChange={num('price_remote', true)} /></Field>
            <Field label="Crédits inclus"><input type="number" min={0} className={inputCls} value={data.credits_included} onChange={num('credits_included')} /></Field>
            <Field label="Validité (jours)"><input type="number" min={1} className={inputCls} value={data.validity_days} onChange={num('validity_days')} /></Field>
            <Field label="Durée de publication (jours)"><input type="number" min={1} className={inputCls} value={data.publication_days ?? ''} onChange={num('publication_days', true)} /></Field>
            <div className="flex flex-wrap items-center gap-4 text-sm md:col-span-3">
                <label><input type="checkbox" checked={data.includes_publication} onChange={(e) => setData('includes_publication', e.target.checked)} /> Inclut la publication</label>
                <label><input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} /> Active</label>
                {['SUR_PLACE', 'A_DISTANCE'].map((m) => (
                    <label key={m}><input type="checkbox" checked={data.allowed_modes.includes(m)} onChange={(e) => setData('allowed_modes', e.target.checked ? [...data.allowed_modes, m] : data.allowed_modes.filter((x) => x !== m))} /> {m === 'SUR_PLACE' ? 'Sur place' : 'À distance'}</label>
                ))}
                {err.allowed_modes && <span className="text-red-600">{err.allowed_modes}</span>}
            </div>
            <button disabled={processing} className="rounded-md bg-[#C8662B] px-4 py-2 text-white md:col-span-3">Enregistrer</button>
        </form>
    );
}

export default function Packages({ packages }: { packages: (Pkg & { id: number })[] }) {
    const [editing, setEditing] = useState<number | 'new' | null>(null);
    return (
        <SiteLayout portal="admin" title="Formules">
            <Head title="Formules" />
            <button className="mb-4 rounded-md bg-[#C8662B] px-4 py-2 text-sm text-white" onClick={() => setEditing('new')}>+ Nouvelle formule</button>
            {editing === 'new' && <Card className="mb-4"><b>Nouvelle formule</b><PackageForm initial={EMPTY} onDone={() => setEditing(null)} /></Card>}
            <div className="grid gap-3">
                {packages.map((p) => (
                    <Card key={p.id}>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div><b>{p.name}</b> <span className="text-xs text-neutral-500">({p.code})</span><div className="text-sm">{fcfa(p.price)} · {p.credits_included} passage(s) · {p.validity_days} j{p.includes_publication && ' · publication'}</div></div>
                            <div className="flex items-center gap-2"><StatusBadge status={p.is_active ? 'CONFIRMEE' : 'ANNULEE'} /><button className="text-sm underline" onClick={() => setEditing(editing === p.id ? null : p.id)}>Modifier</button></div>
                        </div>
                        {editing === p.id && <PackageForm initial={p} onDone={() => setEditing(null)} />}
                    </Card>
                ))}
            </div>
        </SiteLayout>
    );
}
