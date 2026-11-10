import { Button } from '@/components/ui/button';
import { dayKey, fmtDay, fmtTime, localHint, MODE_LABEL, STATUS_LABEL } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { CalendarDays, Check, ChevronLeft, ChevronRight, Lock } from 'lucide-react';
import { ReactNode, useMemo, useRef, useState } from 'react';

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

/** Bouton « Choisir » à mettre dans la carte quand toute la carte est déjà cliquable (évite un bouton dans un bouton). */
export function PickLabel({ children = 'Choisir ce package' }: { children?: ReactNode }) {
    return <span className="block w-full rounded-lg bg-primary px-4 py-3 text-center text-sm font-bold text-primary-foreground [forced-color-adjust:none]">{children}</span>;
}

/**
 * Carte d'un package, volontairement sobre : texte sombre sur fond blanc, filets fins, la couleur de la marque réservée au bouton et à la sélection.
 * Le nom du package est dans un bandeau coloré dont la teinte s'assombrit du premier au dernier package (`rank` / `total`) :
 * on voit la montée en gamme d'un coup d'œil. Le texte reste blanc, donc lisible sur toutes les teintes.
 */
export function PackageCard({ pkg, action, selected, rank = 0, total = 6 }: { pkg: Pkg; action?: ReactNode; selected?: boolean; rank?: number; total?: number }) {
    const t = total > 1 ? Math.min(1, Math.max(0, rank / (total - 1))) : 0;
    const band = { background: `hsl(${Math.round(26 - 6 * t)} ${Math.round(85 - 25 * t)}% ${Math.round(41 - 23 * t)}%)` };
    const items = pkg.includes?.length ? pkg.includes : [`1 passage de ${pkg.duration_minutes} min`, `Jusqu’à ${pkg.max_products} produits`];
    // « Tout le package X, avec en plus : » est une phrase d'introduction, pas un avantage.
    const lead = items[0]?.endsWith(':') ? items[0] : null;
    const perks = lead ? items.slice(1) : items;

    return (
        <div className={`flex h-full flex-col overflow-hidden rounded-lg border bg-white transition-shadow hover:shadow-md ${selected ? 'border-primary ring-2 ring-primary' : 'border-neutral-200'}`}>
            <div style={band} className="flex items-center justify-between gap-2 px-5 py-4 text-white [forced-color-adjust:none]">
                <h3 className="text-lg font-bold uppercase tracking-wide">{pkg.name.replace(/^Package\s+/i, '')}</h3>
                {selected && <Check className="size-5 shrink-0" aria-label="Choisi" />}
            </div>
            <div className="flex flex-1 flex-col p-5">
            <p className="text-sm leading-snug text-neutral-600">{pkg.description}</p>

            <div className="mt-4 flex items-baseline gap-1.5">
                <span className="text-3xl font-bold tracking-tight text-neutral-900">{new Intl.NumberFormat('fr-FR').format(pkg.price).replace(/[  ]/g, ' ')}</span>
                <span className="text-sm text-neutral-500">FCFA</span>
            </div>

            <dl className="mt-4 divide-y border-y text-sm">
                <div className="flex items-center justify-between py-2"><dt className="text-neutral-600">Passage à l’antenne</dt><dd className="font-medium text-neutral-900">{pkg.duration_minutes} min</dd></div>
                <div className="flex items-center justify-between py-2"><dt className="text-neutral-600">Produits présentés</dt><dd className="font-medium text-neutral-900">jusqu’à {pkg.max_products}</dd></div>
            </dl>

            {lead && <p className="mt-4 text-sm font-medium text-neutral-900">{lead}</p>}
            <ul className={`grid gap-2 text-sm text-neutral-700 ${lead ? 'mt-2' : 'mt-4'}`}>
                {perks.map((i) => (
                    <li key={i} className="flex gap-2"><Check className="mt-0.5 size-4 shrink-0 text-neutral-500" aria-hidden /><span className="leading-snug">{i}</span></li>
                ))}
            </ul>

            {action && <div className="mt-auto pt-5">{action}</div>}
            </div>
        </div>
    );
}

export type SlotDto = { id: number; starts_at: string; ends_at: string; session_title: string; modes: string[]; available: boolean };

const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const WEEKDAYS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
const pad = (n: number) => String(n).padStart(2, '0');
const cap = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

/** Cases d'un mois (semaine commençant le lundi) : « AAAA-MM-JJ », ou null pour les cases vides. */
function monthCells(y: number, m: number): (string | null)[] {
    const offset = (new Date(Date.UTC(y, m, 1)).getUTCDay() + 6) % 7;
    const count = new Date(Date.UTC(y, m + 1, 0)).getUTCDate();
    const cells: (string | null)[] = [...Array(offset).fill(null), ...Array.from({ length: count }, (_, i) => `${y}-${pad(m + 1)}-${pad(i + 1)}`)];
    while (cells.length % 7) cells.push(null);
    return cells;
}

/**
 * Choix du créneau : un vrai calendrier du mois (les dates avec des créneaux libres sont marquées), puis les heures du jour choisi, rangées par heure.
 * Un créneau déjà pris par un autre vendeur reste visible mais grisé « Occupé ». Aucune donnée personnelle.
 */
export function SlotPicker({ slots, value, onSelect }: { slots: SlotDto[]; value?: number | null; onSelect: (s: SlotDto) => void }) {
    const byDay = useMemo(() => {
        const m = new Map<string, SlotDto[]>();
        for (const s of slots) {
            const k = dayKey(s.starts_at);
            m.set(k, [...(m.get(k) ?? []), s]);
        }
        return m;
    }, [slots]);
    const days = useMemo(() => [...byDay.keys()].sort(), [byDay]);
    const free = (d: string) => byDay.get(d)?.filter((s) => s.available).length ?? 0;
    const chosen = slots.find((s) => s.id === value);

    const [day, setDay] = useState<string | null>(null);
    const [view, setView] = useState<{ y: number; m: number } | null>(null);
    const panel = useRef<HTMLDivElement>(null);

    if (!days.length) return <Card>Aucun créneau ouvert pour le moment. Revenez bientôt !</Card>;

    const firstFree = days.find((d) => free(d) > 0) ?? days[0];
    const current = day ?? (chosen ? dayKey(chosen.starts_at) : firstFree);
    const shown = view ?? { y: Number(current.slice(0, 4)), m: Number(current.slice(5, 7)) - 1 };
    const monthIdx = (k: string) => Number(k.slice(0, 4)) * 12 + Number(k.slice(5, 7)) - 1;
    const canPrev = shown.y * 12 + shown.m > monthIdx(days[0]);
    const canNext = shown.y * 12 + shown.m < monthIdx(days[days.length - 1]);
    const moveMonth = (d: number) => { const i = shown.y * 12 + shown.m + d; setView({ y: Math.floor(i / 12), m: i % 12 }); };
    const today = dayKey(new Date().toISOString());

    const pickDay = (d: string) => {
        setDay(d);
        // Sur téléphone, les heures s'affichent sous le calendrier : on y descend.
        if (window.matchMedia('(max-width: 767px)').matches) setTimeout(() => panel.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 60);
    };

    const list = byDay.get(current) ?? [];
    const nFree = free(current);
    // Heures rangées par heure de début : « 19 h » → 19 h 00, 19 h 15, 19 h 30, 19 h 45.
    const rows = [...list.reduce((m, s) => m.set(fmtTime(s.starts_at).split(' ')[0], [...(m.get(fmtTime(s.starts_at).split(' ')[0]) ?? []), s]), new Map<string, SlotDto[]>())];
    const nextFree = days.find((d) => d > current && free(d) > 0);
    const hint = chosen ? localHint(chosen.starts_at) : null;

    return (
        <div className="grid gap-4 md:grid-cols-[minmax(0,21rem)_1fr]">
            {/* Calendrier */}
            <div className="rounded-2xl border bg-white p-4 shadow-sm">
                <div className="mb-3 flex items-center justify-between">
                    <button type="button" onClick={() => moveMonth(-1)} disabled={!canPrev} aria-label="Mois précédent" className="flex size-9 items-center justify-center rounded-full border hover:bg-neutral-50 disabled:opacity-30"><ChevronLeft className="size-5" aria-hidden /></button>
                    <div className="font-bold" aria-live="polite">{cap(MONTHS[shown.m])} {shown.y}</div>
                    <button type="button" onClick={() => moveMonth(1)} disabled={!canNext} aria-label="Mois suivant" className="flex size-9 items-center justify-center rounded-full border hover:bg-neutral-50 disabled:opacity-30"><ChevronRight className="size-5" aria-hidden /></button>
                </div>
                <div className="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase text-neutral-400">
                    {WEEKDAYS.map((w) => <div key={w}>{w}</div>)}
                </div>
                <div className="mt-1 grid grid-cols-7 gap-1">
                    {monthCells(shown.y, shown.m).map((d, i) => {
                        if (!d) return <div key={`e${i}`} />;
                        const n = free(d);
                        const has = byDay.has(d);
                        const sel = d === current;
                        const base = 'relative flex aspect-square flex-col items-center justify-center rounded-xl text-sm';
                        if (!has) return <div key={d} className={`${base} text-neutral-300`}>{Number(d.slice(8))}</div>;
                        return (
                            <button key={d} type="button" onClick={() => pickDay(d)} aria-pressed={sel}
                                aria-label={`${fmtDay(`${d}T12:00:00+00:00`)}, ${n > 0 ? `${n} créneau${n > 1 ? 'x' : ''} libre${n > 1 ? 's' : ''}` : 'complet'}`}
                                className={`${base} font-bold transition-colors ${sel ? 'bg-primary text-primary-foreground shadow [forced-color-adjust:none]' : n > 0 ? 'border border-neutral-300 bg-white text-neutral-900 hover:border-neutral-900' : 'border border-red-200 bg-red-50 text-red-800'} ${d === today && !sel ? 'underline decoration-2 underline-offset-4' : ''}`}>
                                {Number(d.slice(8))}
                                {n === 0 && !sel && <span className="mt-0.5 text-[9px] font-bold uppercase leading-none tracking-wide text-red-600">Complet</span>}
                            </button>
                        );
                    })}
                </div>
                <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-neutral-500">
                    <span className="inline-flex items-center gap-1.5"><span className="size-3.5 rounded border border-neutral-300 bg-white" aria-hidden />Places libres</span>
                    <span className="inline-flex items-center gap-1.5"><span className="size-3.5 rounded border border-red-200 bg-red-50" aria-hidden />Complet</span>
                    <span className="inline-flex items-center gap-1.5"><span className="size-3.5 rounded bg-primary" aria-hidden />Choisi</span>
                </div>
            </div>

            {/* Heures du jour choisi */}
            <div ref={panel} className="scroll-mt-24 rounded-2xl border bg-white p-4 shadow-sm">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div className="flex items-center gap-2 text-lg font-bold"><CalendarDays className="size-5 text-[#B85600]" aria-hidden />{cap(fmtDay(`${current}T12:00:00+00:00`))}</div>
                        <div className="mt-0.5 text-sm text-neutral-600">{nFree > 0 ? `${nFree} créneau${nFree > 1 ? 'x' : ''} libre${nFree > 1 ? 's' : ''} sur ${list.length}` : 'Cette date est complète'}</div>
                    </div>
                    {list.length > 0 && <div className="h-2 w-28 overflow-hidden rounded-full bg-neutral-200" aria-hidden><div className="h-full rounded-full bg-[#B85600]" style={{ width: `${(nFree / list.length) * 100}%` }} /></div>}
                </div>

                {nFree === 0 && nextFree && (
                    <button type="button" onClick={() => { setView(null); pickDay(nextFree); }} className="mt-3 rounded-lg bg-orange-50 px-3 py-2 text-sm font-semibold text-[#B85600] ring-1 ring-orange-200 hover:bg-orange-100">
                        Voir la prochaine date libre : {cap(fmtDay(`${nextFree}T12:00:00+00:00`))} →
                    </button>
                )}

                <div className="mt-4 grid gap-3">
                    {rows.map(([hour, items]) => (
                        <div key={hour} className="grid grid-cols-[2.75rem_1fr] items-center gap-2">
                            <div className="text-sm font-bold text-neutral-500">{hour} h</div>
                            <div className="grid grid-cols-4 gap-1.5">
                                {items.map((s) => {
                                    const selected = value === s.id;
                                    return (
                                        <button key={s.id} type="button" disabled={!s.available} aria-pressed={selected} aria-label={`${fmtTime(s.starts_at)}${s.available ? '' : ', occupé'}`} onClick={() => onSelect(s)}
                                            className={`flex flex-col items-center justify-center rounded-lg border text-[13px] font-semibold transition-colors ${s.available ? 'h-11' : 'h-14'} ${selected ? 'border-primary bg-primary text-primary-foreground [forced-color-adjust:none]' : s.available ? 'border-neutral-300 bg-white hover:border-neutral-900' : 'cursor-not-allowed border-red-200 bg-red-50 text-red-300'}`}>
                                            <span className={s.available ? '' : 'line-through decoration-red-300'}>{fmtTime(s.starts_at)}</span>
                                            {!s.available && <span className="mt-0.5 inline-flex items-center gap-0.5 text-[10px] font-bold uppercase leading-none tracking-wide text-red-700"><Lock className="size-2.5" aria-hidden />Occupé</span>}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>

                <p className="mt-4 border-t pt-3 text-xs text-neutral-500">
                    {chosen ? <><b className="text-neutral-800">Votre créneau : {cap(fmtDay(chosen.starts_at))}, {fmtTime(chosen.starts_at)}.</b> </> : null}
                    Heures de Lomé.{hint ? ` ${hint}.` : ''} Un créneau marqué « Occupé » est déjà réservé : il n’est plus possible de le choisir.
                </p>
            </div>
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
