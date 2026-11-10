import { Country, flag } from '@/lib/countries';
import { fmtTime } from '@/lib/format';
import { Briefcase, Clock, Globe, LayoutGrid, LucideIcon, Play, ShoppingBag, Shirt, Smartphone, Sofa, Sparkles, UtensilsCrossed, Wheat } from 'lucide-react';
import { useEffect, useState } from 'react';

export type LiveCategory = { code: string; name: string };
export type WindowState = { open: boolean; until: string | null; next: string | null; label: string };
export type LiveDto = {
    id: number; name: string; country: string; category: string; url: string; handle: string | null; photo: string | null; since: string | null; until: string | null;
};

/** Pays proposés en premier (Togo d'abord). */
export const QUICK_COUNTRIES = ['TG', 'BJ', 'CI', 'CM', 'BF', 'SN', 'GA', 'ML', 'NE', 'GH'];

/** Icône (au trait) de chaque catégorie. */
export const CATEGORY_ICON: Record<string, LucideIcon> = {
    nourriture: UtensilsCrossed, mode: Shirt, beaute: Sparkles, tech: Smartphone, maison: Sofa, agriculture: Wheat, services: Briefcase, autre: ShoppingBag,
};
export const categoryIcon = (code: string) => CATEGORY_ICON[code] ?? ShoppingBag;

/** Point rouge animé : signale ce qui est en direct. */
export function LiveDot({ className = 'size-2' }: { className?: string }) {
    return (
        <span className={`relative inline-flex shrink-0 ${className}`} aria-hidden>
            <span className="absolute inline-flex size-full animate-ping rounded-full bg-red-500 opacity-60 motion-reduce:animate-none" />
            <span className="relative inline-flex size-full rounded-full bg-red-600" />
        </span>
    );
}

/** Étiquette « En direct ». */
export function OnAirBadge({ className = '' }: { className?: string }) {
    return (
        <span className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded bg-red-600 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white ${className}`}>
            <span className="size-1.5 rounded-full bg-white" aria-hidden />
            En direct
        </span>
    );
}

/** « depuis 25 min » (mis à jour toutes les 30 s). */
export function useSince(iso: string | null) {
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        const t = setInterval(() => setNow(Date.now()), 30_000);
        return () => clearInterval(t);
    }, []);
    if (!iso) return '';
    const min = Math.max(0, Math.round((now - new Date(iso).getTime()) / 60_000));
    return min < 1 ? 'à l’instant' : min < 60 ? `depuis ${min} min` : `depuis ${Math.floor(min / 60)} h ${String(min % 60).padStart(2, '0')}`;
}

/** Recharge régulièrement une partie de la page, seulement quand l'onglet est visible. */
export function useAutoRefresh(reload: () => void, ms: number) {
    useEffect(() => {
        const t = setInterval(() => document.visibilityState === 'visible' && reload(), ms);
        return () => clearInterval(t);
    }, [reload, ms]);
}

export function Avatar({ name, photo, size = 'size-14' }: { name: string; photo: string | null; size?: string }) {
    return photo ? (
        <img src={photo} alt="" className={`${size} shrink-0 rounded-full object-cover`} loading="lazy" />
    ) : (
        <span aria-hidden className={`${size} flex shrink-0 items-center justify-center rounded-full bg-neutral-900 text-xl font-bold text-white`}>{name.trim().charAt(0).toUpperCase()}</span>
    );
}

/** Carte d'un live : photo, nom, pays, catégorie, et le bouton pour rejoindre le live sur TikTok. */
export function LiveCard({ live, countries, categories }: { live: LiveDto; countries: Country[]; categories: LiveCategory[] }) {
    const since = useSince(live.since);
    const country = countries.find((c) => c.code === live.country);
    const cat = categories.find((c) => c.code === live.category);
    const Icon = categoryIcon(live.category);
    return (
        <article className="flex flex-col overflow-hidden rounded-xl border bg-white shadow-sm transition-shadow hover:shadow-md" aria-label={`${live.name}, en direct`}>
            <div className="flex items-center gap-3 p-4">
                <div className="relative">
                    <Avatar name={live.name} photo={live.photo} />
                    <span className="absolute -right-0.5 -bottom-0.5 rounded-full bg-white p-0.5"><LiveDot className="size-3" /></span>
                </div>
                <div className="min-w-0">
                    <h3 className="truncate text-base font-bold">{live.name}</h3>
                    {live.handle && <div className="truncate text-sm text-neutral-500">@{live.handle}</div>}
                    <div className="mt-0.5 text-xs font-medium text-red-600">En direct {since}</div>
                </div>
            </div>
            <div className="flex flex-wrap gap-x-4 gap-y-1 border-t px-4 py-2.5 text-sm text-neutral-600">
                <span className="flag-font">{flag(live.country)} {country?.name ?? live.country}</span>
                {cat && <span className="inline-flex items-center gap-1.5"><Icon className="size-4" aria-hidden />{cat.name}</span>}
            </div>
            <a href={live.url} target="_blank" rel="noopener noreferrer"
                className="mt-auto flex items-center justify-center gap-2 bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground [forced-color-adjust:none] hover:opacity-90">
                <Play className="size-4 fill-current" aria-hidden /> Rejoindre le live
            </a>
        </article>
    );
}

/** Horaires de vérification : ouverts maintenant, ou prochaine ouverture. */
export function WindowBadge({ window: w, className = '', dark = false }: { window: WindowState; className?: string; dark?: boolean }) {
    const tone = dark ? 'border-white/15 bg-white/5 text-neutral-300 [&_b]:text-white' : 'bg-white text-neutral-700';
    return (
        <div className={`flex items-start gap-2.5 rounded-lg border px-3 py-2.5 text-sm ${tone} ${className}`}>
            {w.open ? <span className="mt-1.5 size-2 shrink-0 rounded-full bg-green-500" aria-hidden /> : <Clock className={`mt-0.5 size-4 shrink-0 ${dark ? 'text-neutral-400' : 'text-neutral-500'}`} aria-hidden />}
            <span>
                {w.open
                    ? <>Vérification des lives <b>ouverte</b> jusqu’à {fmtTime(w.until!)}.</>
                    : <>Vérification des lives : {w.label} (heure de Lomé).{w.next && <> Prochaine ouverture : <b>{fmtTime(w.next)}</b>.</>}</>}
            </span>
        </div>
    );
}

const chip = (active: boolean) =>
    `inline-flex shrink-0 items-center gap-2 rounded-full border px-3.5 py-2 text-sm font-medium transition-colors ${active ? 'border-neutral-900 bg-neutral-900 text-white' : 'border-neutral-300 bg-white text-neutral-700 hover:border-neutral-500'}`;
/** Rangée de pastilles : sur une seule ligne qui défile (téléphone), ou qui passe à la ligne. */
const rowCls = (oneLine: boolean) => `flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden ${oneLine ? '' : 'sm:flex-wrap'}`;
const Count = ({ n, active }: { n?: number; active: boolean }) => (n ? <span className={`text-xs ${active ? 'text-white/70' : 'text-neutral-400'}`}>{n}</span> : null);

/** Choix de la catégorie. */
export function CategoryTiles({ categories, value, onChange, counts, allLabel, name = 'Catégorie', oneLine = false }: {
    categories: LiveCategory[]; value: string | null; onChange: (code: string | null) => void; counts?: Record<string, number>; allLabel?: string; name?: string; oneLine?: boolean;
}) {
    return (
        <div role="radiogroup" aria-label={name} className={rowCls(oneLine)}>
            {allLabel && (
                <button type="button" role="radio" aria-checked={!value} onClick={() => onChange(null)} className={chip(!value)}>
                    <LayoutGrid className="size-4" aria-hidden />{allLabel}
                </button>
            )}
            {categories.map((c) => {
                const Icon = categoryIcon(c.code);
                const active = value === c.code;
                return (
                    <button key={c.code} type="button" role="radio" aria-checked={active} onClick={() => onChange(c.code)} className={chip(active)}>
                        <Icon className="size-4" aria-hidden />{c.name}<Count n={counts?.[c.code]} active={active} />
                    </button>
                );
            })}
        </div>
    );
}

/** Choix du pays : les pays les plus fréquents (Togo d'abord), puis la liste complète. */
export function CountryBubbles({ countries, value, onChange, counts, allLabel, extra = [], oneLine = false }: {
    countries: Country[]; value: string | null; onChange: (code: string | null) => void; counts?: Record<string, number>; allLabel?: string; extra?: string[]; oneLine?: boolean;
}) {
    const codes = [...new Set([...QUICK_COUNTRIES, ...extra, ...(value ? [value] : [])])].filter((c) => countries.some((x) => x.code === c));
    return (
        <div role="radiogroup" aria-label="Pays" className={rowCls(oneLine)}>
            {allLabel && <button type="button" role="radio" aria-checked={!value} onClick={() => onChange(null)} className={chip(!value)}><Globe className="size-4" aria-hidden />{allLabel}</button>}
            {codes.map((code) => {
                const c = countries.find((x) => x.code === code)!;
                const active = value === code;
                return (
                    <button key={code} type="button" role="radio" aria-checked={active} onClick={() => onChange(code)} className={`${chip(active)} flag-font`}>
                        <span aria-hidden>{flag(code)}</span>{c.name}<Count n={counts?.[code]} active={active} />
                    </button>
                );
            })}
            <select aria-label="Autre pays" className={`${chip(false)} flag-font pr-2`} value={value && !codes.slice(0, QUICK_COUNTRIES.length).includes(value) ? value : ''} onChange={(e) => onChange(e.target.value || null)}>
                <option value="">Autre pays…</option>
                {countries.filter((c) => !QUICK_COUNTRIES.includes(c.code)).map((c) => <option key={c.code} value={c.code}>{flag(c.code)} {c.name}</option>)}
            </select>
        </div>
    );
}
