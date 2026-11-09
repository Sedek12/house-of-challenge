import { Card, Field, Pkg, inputCls } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { Head, useForm } from '@inertiajs/react';

type P = Pkg & { is_active: boolean; display_order: number };

function PackForm({ pkg }: { pkg?: P }) {
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        code: pkg?.code ?? '', name: pkg?.name ?? '', description: pkg?.description ?? '', price: String(pkg?.price ?? ''), duration_minutes: String(pkg?.duration_minutes ?? 10),
        max_products: String(pkg?.max_products ?? 3), includes: (pkg?.includes ?? []).join('\n'), is_active: pkg?.is_active ?? true, display_order: String(pkg?.display_order ?? 0),
    });
    transform((d) => ({ ...d, includes: d.includes.split('\n').map((l) => l.trim()).filter(Boolean) }));
    const err = errors as Record<string, string>;
    return (
        <Card>
            <form onSubmit={(e) => { e.preventDefault(); post(pkg ? route('admin.packages.update', pkg.id) : route('admin.packages.store'), { preserveScroll: true, onSuccess: () => !pkg && reset() }); }} className="grid gap-3 md:grid-cols-3">
                <Field label="Nom" error={err.name}><input className={inputCls} value={data.name} onChange={(e) => setData('name', e.target.value)} /></Field>
                <Field label="Code" error={err.code}><input className={inputCls} value={data.code} onChange={(e) => setData('code', e.target.value)} /></Field>
                <Field label="Prix (FCFA)" error={err.price}><input className={inputCls} inputMode="numeric" value={data.price} onChange={(e) => setData('price', e.target.value.replace(/\D/g, ''))} /></Field>
                <Field label="Durée du passage (min)" error={err.duration_minutes}><input className={inputCls} inputMode="numeric" value={data.duration_minutes} onChange={(e) => setData('duration_minutes', e.target.value.replace(/\D/g, ''))} /></Field>
                <Field label="Produits maximum (1 à 10)" error={err.max_products}><input className={inputCls} inputMode="numeric" value={data.max_products} onChange={(e) => setData('max_products', e.target.value.replace(/\D/g, ''))} /></Field>
                <Field label="Ordre d’affichage" error={err.display_order}><input className={inputCls} inputMode="numeric" value={data.display_order} onChange={(e) => setData('display_order', e.target.value.replace(/\D/g, ''))} /></Field>
                <Field label="Description" error={err.description}><textarea className={inputCls} rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} /></Field>
                <Field label="Ce qui est inclus (une ligne par élément)"><textarea className={inputCls} rows={3} value={data.includes} onChange={(e) => setData('includes', e.target.value)} /></Field>
                <div className="flex flex-col justify-between">
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} /> Package actif (visible et réservable)</label>
                    <Button disabled={processing}>{pkg ? 'Enregistrer' : 'Créer le package'}</Button>
                </div>
            </form>
        </Card>
    );
}

export default function Packages({ packages }: { packages: P[] }) {
    return (
        <SiteLayout portal="admin" title="Packages">
            <Head title="Packages" />
            <div className="grid gap-4">
                {packages.map((p) => <PackForm key={p.id} pkg={p} />)}
                <h2 className="mt-4 font-bold">Nouveau package</h2>
                <PackForm />
            </div>
        </SiteLayout>
    );
}
