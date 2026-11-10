import { Avatar, categoryIcon, LiveCategory, LiveDto, OnAirBadge, useSince } from '@/components/live';
import { Country, flag } from '@/lib/countries';
import { ChevronDown, ChevronUp, Eye, Heart, Play, X } from 'lucide-react';
import { CSSProperties, useCallback, useEffect, useRef, useState } from 'react';

const NAMES = ['Kofi', 'Ama', 'Yawo', 'Aminata', 'Mariam', 'Koffi', 'Fatou', 'Eric', 'Chantal', 'Ibrahim', 'Josiane', 'Serge', 'Adjoa', 'Moussa', 'Estelle', 'Rodrigue'];
const MESSAGES = [
    'Bonjour 👋', 'Quel est le prix ?', 'Livraison possible à Lomé ?', 'Magnifique 😍', 'Je prends 2 !', 'Disponible en taille M ?', 'Votre numéro WhatsApp ?',
    'Bravo, très beau !', 'Le prix live, c’est combien ?', 'Bonsoir depuis Abidjan', 'Vous livrez à Cotonou ?', 'Montrez de plus près svp', 'J’achète !', 'Il en reste combien ?',
];
const pick = <T,>(a: T[]) => a[Math.floor(Math.random() * a.length)];

type Msg = { id: number; name: string; text: string };

/** Simulation d'un live (spectateurs, commentaires, cœurs) pour donner l'aperçu d'un vrai direct. Aucune donnée réelle. */
export function useSimulation(seed: number) {
    const [viewers, setViewers] = useState(0);
    const [chat, setChat] = useState<Msg[]>([]);
    const [hearts, setHearts] = useState<{ id: number; x: number }[]>([]);
    const n = useRef(0);

    const heart = useCallback(() => {
        const id = ++n.current;
        setHearts((h) => [...h.slice(-12), { id, x: Math.round(Math.random() * 36 - 18) }]);
        setTimeout(() => setHearts((h) => h.filter((x) => x.id !== id)), 2300);
    }, []);

    useEffect(() => {
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        setViewers(80 + ((seed * 137) % 420));
        setChat([{ id: ++n.current, name: pick(NAMES), text: pick(MESSAGES) }]);
        const timers = [
            setInterval(() => setViewers((v) => Math.max(20, v + Math.round(Math.random() * 14 - 5))), 2500),
            setInterval(() => setChat((c) => [...c.slice(-4), { id: ++n.current, name: pick(NAMES), text: pick(MESSAGES) }]), 2200),
        ];
        if (!reduce) timers.push(setInterval(heart, 1400));
        return () => timers.forEach(clearInterval);
    }, [seed, heart]);

    return { viewers, chat, hearts, heart };
}

/** Couleur propre à chaque vendeur (quand il n'a pas de photo). */
export const hueOf = (name: string) => [...name].reduce((h, c) => (h * 31 + c.charCodeAt(0)) % 360, 3);
const hue = (name: string) => [...name].reduce((h, c) => (h * 31 + c.charCodeAt(0)) % 360, 7);
export const tint = (name: string): CSSProperties => ({ background: `linear-gradient(160deg, hsl(${hue(name)} 65% 42%), hsl(${(hue(name) + 45) % 360} 70% 18%))` });

/** Tuile verticale d'un live (comme dans TikTok) : touchez-la pour ouvrir le live. */
export function LiveTile({ live, countries, categories, onOpen }: { live: LiveDto; countries: Country[]; categories: LiveCategory[]; onOpen: () => void }) {
    const country = countries.find((c) => c.code === live.country);
    const cat = categories.find((c) => c.code === live.category);
    const Icon = categoryIcon(live.category);
    return (
        <button type="button" onClick={onOpen} aria-label={`${live.name}, en direct. Ouvrir`}
            className="group relative aspect-[3/4] overflow-hidden rounded-xl bg-neutral-900 text-left text-white shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
            {live.photo
                ? <img src={live.photo} alt="" loading="lazy" className="absolute inset-0 size-full object-cover transition-transform duration-300 group-hover:scale-105" />
                : <div style={tint(live.name)} className="absolute inset-0 flex items-center justify-center text-6xl font-extrabold text-white/30" aria-hidden>{live.name.trim().charAt(0).toUpperCase()}</div>}
            <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-black/30" aria-hidden />
            <OnAirBadge className="absolute left-2 top-2" />
            <div className="absolute inset-x-0 bottom-0 p-3">
                <div className="truncate text-sm font-bold leading-tight">{live.name}</div>
                <div className="mt-1 flex items-center gap-2 text-[11px] text-white/85">
                    <span className="flag-font truncate">{flag(live.country)} {country?.name ?? live.country}</span>
                    {cat && <span className="inline-flex shrink-0 items-center gap-1"><Icon className="size-3.5" aria-hidden />{cat.name.split(' ')[0]}</span>}
                </div>
            </div>
        </button>
    );
}

/** Écran de live en plein écran (format vertical) : infos du vendeur, passage au live suivant, et bouton pour rejoindre sur TikTok. */
export function LiveViewer({ lives, index, onIndex, onClose, countries, categories }: {
    lives: LiveDto[]; index: number; onIndex: (i: number) => void; onClose: () => void; countries: Country[]; categories: LiveCategory[];
}) {
    const live = lives[index];
    const since = useSince(live?.since ?? null);
    const touchY = useRef<number | null>(null);
    const sim = useSimulation(live?.id ?? 0);
    const go = (d: number) => lives.length > 1 && onIndex((index + d + lives.length) % lives.length);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
            if (e.key === 'ArrowDown') go(1);
            if (e.key === 'ArrowUp') go(-1);
        };
        document.addEventListener('keydown', onKey);
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => { document.removeEventListener('keydown', onKey); document.body.style.overflow = prev; };
    });
    if (!live) return null;

    const country = countries.find((c) => c.code === live.country);
    const cat = categories.find((c) => c.code === live.category);
    const Icon = categoryIcon(live.category);
    const circle = 'flex size-11 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur hover:bg-black/60';

    return (
        <div role="dialog" aria-modal="true" aria-label={`Live de ${live.name}`} className="fixed inset-0 z-50 flex items-center justify-center bg-black/90 sm:p-4">
            <div
                className="relative flex h-dvh w-full max-w-md flex-col overflow-hidden bg-neutral-900 text-white sm:h-[min(100dvh-2rem,52rem)] sm:rounded-2xl"
                onTouchStart={(e) => { touchY.current = e.touches[0].clientY; }}
                onTouchEnd={(e) => {
                    const d = touchY.current === null ? 0 : e.changedTouches[0].clientY - touchY.current;
                    touchY.current = null;
                    if (d < -70) go(1);
                    else if (d > 70) go(-1);
                }}>
                {/* Fond : photo floutée ou couleur du vendeur */}
                {live.photo ? <img src={live.photo} alt="" className="absolute inset-0 size-full scale-110 object-cover blur-2xl" /> : <div style={tint(live.name)} className="absolute inset-0" />}
                <div className="absolute inset-0 bg-gradient-to-b from-black/60 via-black/10 to-black/85" aria-hidden />

                {/* Haut : vendeur et bouton fermer */}
                <div className="relative flex items-center gap-3 p-4">
                    <Avatar name={live.name} photo={live.photo} size="size-11" />
                    <div className="min-w-0 flex-1">
                        <div className="truncate font-bold leading-tight">{live.name}</div>
                        {live.handle && <div className="truncate text-xs text-white/75">@{live.handle}</div>}
                    </div>
                    <button type="button" onClick={onClose} aria-label="Fermer" className={circle}><X className="size-5" aria-hidden /></button>
                </div>
                <style>{`@keyframes ocd-float{0%{opacity:0;transform:translate(0,0) scale(.6)}15%{opacity:1}100%{opacity:0;transform:translate(var(--x),-220px) scale(1.25)}}@keyframes ocd-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}`}</style>
                <div className="relative flex flex-wrap items-center gap-2 px-4">
                    <OnAirBadge />
                    <span className="inline-flex items-center gap-1 rounded bg-black/40 px-2 py-0.5 text-[11px] font-semibold backdrop-blur"><Eye className="size-3.5" aria-hidden />{sim.viewers}</span>
                    <span className="text-xs text-white/80">{since}</span>
                    <span className="ml-auto rounded bg-white/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white/80">Aperçu simulé</span>
                </div>

                {/* Centre : le vendeur à l'écran, et les commentaires du public en bas */}
                <div className="relative flex flex-1 flex-col items-center justify-between px-4 pb-2 pt-6">
                    <div className="relative">
                        <span className="absolute inset-0 animate-ping rounded-full bg-red-500/40 motion-reduce:animate-none" aria-hidden />
                        <div className="relative rounded-full border-4 border-red-500 p-1"><Avatar name={live.name} photo={live.photo} size="size-28" /></div>
                    </div>
                    <ul aria-hidden className="mt-4 flex max-h-44 w-full flex-col justify-end gap-1.5 overflow-hidden pr-16">
                        {sim.chat.map((m) => (
                            <li key={m.id} style={{ animation: 'ocd-in .3s ease-out' }} className="w-fit max-w-full rounded-2xl bg-black/35 px-3 py-1.5 text-sm leading-snug backdrop-blur">
                                <b style={{ color: `hsl(${hueOf(m.name)} 90% 75%)` }}>{m.name}</b> {m.text}
                            </li>
                        ))}
                    </ul>
                </div>

                {/* Actions à droite : cœur, et passage d'un live à l'autre */}
                <div className="absolute right-3 top-1/2 flex -translate-y-1/2 flex-col items-center gap-2">
                    <div className="pointer-events-none absolute -top-1 left-1/2 size-0" aria-hidden>
                        {sim.hearts.map((h) => (
                            <Heart key={h.id} style={{ '--x': `${h.x}px`, animation: 'ocd-float 2.3s ease-out forwards' } as CSSProperties} className="absolute -left-3 size-6 fill-red-500 text-red-500" />
                        ))}
                    </div>
                    <button type="button" onClick={sim.heart} aria-label="Envoyer un cœur" className={circle}><Heart className="size-6 fill-red-500 text-red-500" aria-hidden /></button>
                    {lives.length > 1 && (
                        <>
                            <button type="button" onClick={() => go(-1)} aria-label="Live précédent" className={circle}><ChevronUp className="size-6" aria-hidden /></button>
                            <button type="button" onClick={() => go(1)} aria-label="Live suivant" className={circle}><ChevronDown className="size-6" aria-hidden /></button>
                        </>
                    )}
                </div>

                {/* Bas : infos et bouton pour rejoindre */}
                <div className="relative grid gap-3 p-4 pb-6">
                    <div className="flex flex-wrap gap-2 text-sm">
                        <span className="flag-font rounded-full bg-black/40 px-3 py-1.5 backdrop-blur">{flag(live.country)} {country?.name ?? live.country}</span>
                        {cat && <span className="inline-flex items-center gap-1.5 rounded-full bg-black/40 px-3 py-1.5 backdrop-blur"><Icon className="size-4" aria-hidden />{cat.name}</span>}
                    </div>
                    <p className="text-sm text-white/85">En direct sur TikTok. Touchez le bouton, posez vos questions et achetez auprès du vendeur pendant son live.</p>
                    <a href={live.url} target="_blank" rel="noopener noreferrer"
                        className="flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-4 text-base font-bold text-primary-foreground [forced-color-adjust:none] hover:opacity-90">
                        <Play className="size-5 fill-current" aria-hidden /> Rejoindre le live sur TikTok
                    </a>
                    {lives.length > 1 && <div className="text-center text-xs text-white/60">{index + 1} / {lives.length} · glissez vers le haut pour le live suivant</div>}
                </div>
            </div>
        </div>
    );
}
