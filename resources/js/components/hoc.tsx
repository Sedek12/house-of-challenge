import { Button } from '@/components/ui/button';
import { dayKey, fmtDay, fmtTime, localHint, MODE_LABEL, STATUS_LABEL, fcfa } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { ReactNode, useMemo, useState } from 'react';

export type Pkg = {
    id: number; code: string; name: string; description: string; price: number; duration_minutes: number;
    max_products: number; includes: string[] | null;
};

export function Card({ children, className = '' }: { children: ReactNode; className?: string }) {
    return <div className={`rounded-lg border bg-white p-4 shadow-sm ${className}`}>{children}</div>;
}

export function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="grid gap-1 text-sm">
            <span className="font-medium">{label}</span>
            {children}
            {error && <span className="text-red-600">{error}</span>}
        </label>
    );
}

export const inputCls = 'w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary';

export function StatusBadge({ status }: { status: string }) {
    const color = /CONFIRM|PAYE|PUBLIEE|EFFECTUEE|DISPONIBLE/.test(status) ? 'bg-green-100 text-green-800'
        : /ATTENTE|PROGRAMMEE/.test(status) ? 'bg-amber-100 text-amber-800'
        : 'bg-neutral-200 text-neutral-700';
    return <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${color}`}>{STATUS_LABEL[status] ?? status}</span>;
}

export function ModeBadge({ mode }: { mode: string }) {
    return <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${mode === 'SUR_PLACE' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'}`}>{MODE_LABEL[mode]}</span>;
}

export function PackageCard({ pkg, action, selected }: { pkg: Pkg; action?: ReactNode; selected?: boolean }) {
    return (
        <Card className={`flex flex-col gap-2 ${selected ? 'border-primary ring-2 ring-primary' : ''}`}>
            <div className="text-lg font-bold">{pkg.name}</div>
            <div className="text-2xl font-extrabold text-[#B85600]">{fcfa(pkg.price)}</div>
            <p className="text-sm text-neutral-600">{pkg.description}</p>
            <ul className="text-sm">
                {(pkg.includes?.length ? pkg.includes : [`1 passage de ${pkg.duration_minutes} min`, `Jusqu’à ${pkg.max_products} produits`]).map((i) => (
                    i.endsWith(':') ? <li key={i} className="mt-1 font-semibold text-[#B85600]">{i}</li> : <li key={i} className="flex gap-1.5"><span aria-hidden>✓</span><span>{i}</span></li>
                ))}
            </ul>
            <div className="mt-auto pt-2">{action}</div>
        </Card>
    );
}

export type SlotDto = { id: number; starts_at: string; ends_at: string; session_title: string; modes: string[]; available: boolean };

/**
 * Choix du jour puis de l'heure. Un créneau déjà pris par un autre vendeur reste visible mais grisé « Occupé ».
 * Toute la case est cliquable ; le créneau choisi passe en orange. Aucune donnée personnelle.
 */
export function SlotPicker({ slots, value, onSelect }: { slots: SlotDto[]; value?: number | null; onSelect: (s: SlotDto) => void }) {
    const days = useMemo(() => [...new Set(slots.map((s) => dayKey(s.starts_at)))], [slots]);
    const [day, setDay] = useState<string | null>(null);
    if (!slots.length) return <Card>Aucun créneau ouvert pour le moment. Revenez bientôt !</Card>;

    const freeCount = (d: string) => slots.filter((s) => dayKey(s.starts_at) === d && s.available).length;
    const current = day ?? days.find((d) => freeCount(d) > 0) ?? days[0];
    const list = slots.filter((s) => dayKey(s.starts_at) === current);
    const chosen = slots.find((s) => s.id === value);
    const hint = chosen ? localHint(chosen.starts_at) : null;

    return (
        <div className="grid gap-3">
            <div className="flex gap-2 overflow-x-auto pb-1" role="tablist">
                {days.map((d) => {
                    const first = slots.find((s) => dayKey(s.starts_at) === d)!;
                    const n = freeCount(d);
                    const active = d === current;
                    return (
                        <button key={d} type="button" role="tab" aria-selected={active} onClick={() => setDay(d)}
                            className={`shrink-0 rounded-lg border px-3 py-2 text-left text-sm ${active ? 'border-primary bg-primary text-white' : n === 0 ? 'bg-white text-neutral-400' : 'bg-white'}`}>
                            <div className="font-semibold first-letter:uppercase">{fmtDay(first.starts_at)}</div>
                            <div className={`text-xs ${active ? 'text-white/90' : 'text-neutral-500'}`}>{n > 0 ? `${n} libre${n > 1 ? 's' : ''}` : 'complet'}</div>
                        </button>
                    );
                })}
            </div>
            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                {list.map((s) => {
                    const selected = value === s.id;
                    return (
                        <button key={s.id} type="button" disabled={!s.available} aria-pressed={selected} aria-label={`${fmtTime(s.starts_at)}${s.available ? '' : ', occupé'}`} onClick={() => onSelect(s)}
                            className={`flex h-14 flex-col items-center justify-center rounded-lg border text-sm font-semibold transition-colors ${selected ? 'border-primary bg-primary text-white' : s.available ? 'bg-white hover:border-primary' : 'cursor-not-allowed border-dashed bg-neutral-100 text-neutral-400'}`}>
                            <span className={s.available ? '' : 'line-through'}>{fmtTime(s.starts_at)}</span>
                            {!s.available && <span className="text-[11px] font-medium">Occupé</span>}
                        </button>
                    );
                })}
            </div>
            <p className="text-xs text-neutral-500">
                Heures de Lomé.{hint ? ` ${hint}.` : ''} Un créneau « Occupé » est déjà pris par un autre vendeur.
            </p>
        </div>
    );
}

export function ReserveButton({ children = 'Réserver', pack }: { children?: ReactNode; pack?: string }) {
    return <Button asChild><Link href={route('reserve', pack ? { pack } : {})}>{children}</Link></Button>;
}

export function Pagination({ links }: { links: { url: string | null; label: string; active: boolean }[] }) {
    if (links.length <= 3) return null;
    return (
        <div className="mt-4 flex flex-wrap gap-1">
            {links.map((l, i) => l.url ? (
                <Link key={i} href={l.url} preserveScroll className={`rounded border px-3 py-1 text-sm ${l.active ? 'bg-primary text-white' : 'bg-white'}`} dangerouslySetInnerHTML={{ __html: l.label }} />
            ) : (
                <span key={i} className="rounded border px-3 py-1 text-sm text-neutral-400" dangerouslySetInnerHTML={{ __html: l.label }} />
            ))}
        </div>
    );
}
