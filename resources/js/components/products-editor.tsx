import { Field, inputCls } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import { shrinkImage } from '@/lib/image';
import { useMemo } from 'react';

/** `source_id` : produit repris du catalogue de la boutique (sa photo est recopiée par le serveur). */
export type ProductRow = { id?: number; source_id?: number; name: string; price: string; live_price: string; quantity: string; photo: File | null; photo_url?: string | null };

export const emptyProduct = (): ProductRow => ({ name: '', price: '', live_price: '', quantity: '1', photo: null });

function Preview({ file, url }: { file: File | null; url?: string | null }) {
    const src = useMemo(() => (file ? URL.createObjectURL(file) : url ?? null), [file, url]);
    return src ? <img src={src} alt="" className="h-14 w-14 rounded object-cover" /> : null;
}

/** Liste de 1 à `max` produits (F5) : nom, prix normal, prix spécial live, quantité, photo. */
export function ProductsEditor({ rows, onChange, max, errors }: { rows: ProductRow[]; onChange: (rows: ProductRow[]) => void; max: number; errors: Record<string, string> }) {
    const set = (i: number, patch: Partial<ProductRow>) => onChange(rows.map((r, j) => (j === i ? { ...r, ...patch } : r)));
    const err = (i: number, k: string) => errors[`products.${i}.${k}`];
    return (
        <div className="grid gap-3">
            {rows.map((r, i) => (
                <div key={r.id ?? i} className="grid gap-2 rounded-md border bg-white p-3">
                    <div className="flex items-center justify-between text-sm font-semibold">
                        <span>Produit {i + 1}</span>
                        {rows.length > 1 && <button type="button" className="text-xs font-normal text-red-600 underline" onClick={() => onChange(rows.filter((_, j) => j !== i))}>Retirer</button>}
                    </div>
                    <Field label="Nom du produit" error={err(i, 'name')}><input className={inputCls} value={r.name} maxLength={120} onChange={(e) => set(i, { name: e.target.value })} /></Field>
                    <div className="grid grid-cols-3 gap-2">
                        <Field label="Prix normal (FCFA)" error={err(i, 'price')}><input className={inputCls} inputMode="numeric" value={r.price} onChange={(e) => set(i, { price: e.target.value.replace(/\D/g, '') })} /></Field>
                        <Field label="Prix live (FCFA)" error={err(i, 'live_price')}><input className={inputCls} inputMode="numeric" value={r.live_price} onChange={(e) => set(i, { live_price: e.target.value.replace(/\D/g, '') })} /></Field>
                        <Field label="Quantité" error={err(i, 'quantity')}><input className={inputCls} inputMode="numeric" value={r.quantity} onChange={(e) => set(i, { quantity: e.target.value.replace(/\D/g, '') })} /></Field>
                    </div>
                    <div className="flex items-center gap-3">
                        <Preview file={r.photo} url={r.photo_url} />
                        <label className="grid gap-1 text-sm">
                            <span className="font-medium">Photo</span>
                            <input type="file" accept="image/*" className="text-xs" onChange={async (e) => { const f = e.target.files?.[0]; if (f) set(i, { photo: await shrinkImage(f) }); }} />
                            {err(i, 'photo') && <span className="text-red-600">{err(i, 'photo')}</span>}
                        </label>
                    </div>
                </div>
            ))}
            {rows.length < max
                ? <Button type="button" variant="outline" onClick={() => onChange([...rows, emptyProduct()])}>+ Ajouter un produit ({rows.length}/{max})</Button>
                : <p className="text-xs text-neutral-500">Maximum {max} produit(s) pour ce package.</p>}
            {errors.products && <p className="text-sm text-red-600">{errors.products}</p>}
        </div>
    );
}
