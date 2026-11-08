import { Button } from '@/components/ui/button';
import { dayKey, fmtDay, fmtTime, localHint, MODE_LABEL, STATUS_LABEL, fcfa } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { ReactNode, useMemo, useState } from 'react';

export type Pkg = {
    id: number; code: string; name: string; description: string; price: number; credits_included: number;
    validity_days: number; includes_publication: boolean; allowed_modes: string[]; price_for_mode?: number;
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

export const inputCls = 'w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-[#C8662B] focus:outline-none focus:ring-1 focus:ring-[#C8662B]';

export function StatusBadge({ status }: { status: string }) {
    const color = /CONFIRM|PAYE|PUBLIEE|EFFECTUEE|DISPONIBLE/.test(status) ? 'bg-green-100 text-green-800'
        : /ATTENTE|PROGRAMMEE/.test(status) ? 'bg-amber-100 text-amber-800'
        : 'bg-neutral-200 text-neutral-700';
    return <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${color}`}>{STATUS_LABEL[status] ?? status}</span>;
}

export function ModeBadge({ mode }: { mode: string }) {
    return <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${mode === 'SUR_PLACE' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'}`}>{MODE_LABEL[mode]}</span>;
}

export function PackageCard({ pkg, action }: { pkg: Pkg; action?: ReactNode }) {
    return (
        <Card className="flex flex-col gap-2">
            <div className="text-lg font-bold">{pkg.name}</div>
            <div className="text-2xl font-extrabold text-[#C8662B]">{fcfa(pkg.price_for_mode ?? pkg.price)}</div>
            <p className="text-sm text-neutral-600">{pkg.description}</p>
            <ul className="text-sm">
                <li>✓ {pkg.credits_included} passage(s)</li>
                <li>✓ Valable {pkg.validity_days} jours</li>
                {pkg.includes_publication && <li>✓ Publication de votre projet sur le site</li>}
            </ul>
            <div className="mt-auto pt-2">{action}</div>
        </Card>
    );
}

export type SlotDto = { id: number; starts_at: string; ends_at: string; session_title: string; modes: string[] };

/** Sélection d'un jour puis d'un créneau libre. Aucune donnée personnelle. */
export function SlotPicker({ slots, value, onSelect, cta }: { slots: SlotDto[]; value?: number | null; onSelect?: (s: SlotDto) => void; cta?: (s: SlotDto) => ReactNode }) {
    const days = useMemo(() => [...new Set(slots.map((s) => dayKey(s.starts_at)))], [slots]);
    const [day, setDay] = useState<string | null>(null);
    const current = day ?? days[0];
    if (!slots.length) return <Card>Aucun créneau disponible pour le moment. Revenez bientôt !</Card>;
    const list = slots.filter((s) => dayKey(s.starts_at) === current);
    return (
        <div className="grid gap-3">
            <div className="flex gap-2 overflow-x-auto pb-1" role="tablist">
                {days.map((d) => {
                    const first = slots.find((s) => dayKey(s.starts_at) === d)!;
                    return (
                        <button key={d} role="tab" aria-selected={d === current} onClick={() => setDay(d)}
                            className={`whitespace-nowrap rounded-md border px-3 py-2 text-sm ${d === current ? 'border-[#C8662B] bg-[#C8662B] text-white' : 'bg-white'}`}>
                            {fmtDay(first.starts_at)}
                        </button>
                    );
                })}
            </div>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                {list.map((s) => {
                    const hint = localHint(s.starts_at);
                    const selected = value === s.id;
                    return (
                        <div key={s.id} className={`rounded-md border p-2 text-center text-sm ${selected ? 'border-[#C8662B] ring-2 ring-[#C8662B]' : 'bg-white'}`}>
                            <button className="w-full font-semibold" onClick={() => onSelect?.(s)} disabled={!onSelect}>{fmtTime(s.starts_at)}</button>
                            {hint && <div className="text-xs text-neutral-500">{hint}</div>}
                            <div className="mt-1 flex flex-wrap justify-center gap-1">{s.modes.map((m) => <ModeBadge key={m} mode={m} />)}</div>
                            {cta?.(s)}
                        </div>
                    );
                })}
            </div>
            <p className="text-xs text-neutral-500">Heures affichées en heure de Lomé (UTC+0).</p>
        </div>
    );
}

export function ReserveButton({ children = 'Réserver mon passage', ...q }: { children?: ReactNode; mode?: string }) {
    return <Button asChild><Link href={route('reserve', q)}>{children}</Link></Button>;
}

export function Pagination({ links }: { links: { url: string | null; label: string; active: boolean }[] }) {
    if (links.length <= 3) return null;
    return (
        <div className="mt-4 flex flex-wrap gap-1">
            {links.map((l, i) => l.url ? (
                <Link key={i} href={l.url} preserveScroll className={`rounded border px-3 py-1 text-sm ${l.active ? 'bg-[#C8662B] text-white' : 'bg-white'}`} dangerouslySetInnerHTML={{ __html: l.label }} />
            ) : (
                <span key={i} className="rounded border px-3 py-1 text-sm text-neutral-400" dangerouslySetInnerHTML={{ __html: l.label }} />
            ))}
        </div>
    );
}
