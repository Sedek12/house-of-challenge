import { Avatar, categoryIcon, LiveCategory, LiveDto, OnAirBadge, useSince } from '@/components/live';
import { hueOf, tint, useSimulation } from '@/components/live-viewer';
import { Button } from '@/components/ui/button';
import { Country, flag } from '@/lib/countries';
import { Eye, Heart, Play, ShoppingBag, X } from 'lucide-react';
import { CSSProperties, ReactNode, useEffect } from 'react';

export type PhoneLive = Pick<LiveDto, 'name' | 'handle' | 'photo' | 'country' | 'category'> & { id?: number; since?: string | null };

/**
 * Téléphone avec un live en simulation : le vendeur à l'écran, le nombre de spectateurs, les commentaires du public, les cœurs.
 * Tout est simulé (aperçu), rien n'est réel.
 */
export function LivePhone({ live, countries, categories, className = 'w-64' }: { live: PhoneLive; countries: Country[]; categories: LiveCategory[]; className?: string }) {
    const sim = useSimulation(live.id || 7);
    const since = useSince(live.since ?? null);
    const country = countries.find((c) => c.code === live.country);
    const cat = categories.find((c) => c.code === live.category);
    const Icon = categoryIcon(live.category);

    return (
        <figure className={`mx-auto ${className}`}>
            <style>{`@keyframes ocd-float{0%{opacity:0;transform:translate(0,0) scale(.6)}15%{opacity:1}100%{opacity:0;transform:translate(var(--x),-200px) scale(1.25)}}@keyframes ocd-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}`}</style>
            <div className="relative rounded-[2.4rem] border-[7px] border-neutral-900 bg-neutral-900 shadow-2xl ring-1 ring-neutral-600">
                <div className="absolute left-1/2 top-1.5 z-20 h-4 w-20 -translate-x-1/2 rounded-full bg-black" aria-hidden />
                <div className="relative flex aspect-[9/18.5] flex-col overflow-hidden rounded-[1.9rem] bg-neutral-800 text-white">
                    {live.photo ? <img src={live.photo} alt="" className="absolute inset-0 size-full scale-110 object-cover blur-xl" /> : <div style={tint(live.name)} className="absolute inset-0" />}
                    <div className="absolute inset-0 bg-gradient-to-b from-black/55 via-transparent to-black/80" aria-hidden />

                    {/* Haut : le vendeur et le nombre de spectateurs */}
                    <div className="relative flex items-center gap-1.5 px-2.5 pt-8">
                        <div className="flex min-w-0 flex-1 items-center gap-1.5 rounded-full bg-black/40 p-1 pr-2 backdrop-blur">
                            <Avatar name={live.name} photo={live.photo} size="size-7" />
                            <div className="min-w-0 text-[10px] leading-tight"><div className="truncate font-bold">{live.name}</div><div className="truncate text-white/70">{live.handle ? `@${live.handle}` : 'House of Challenge'}</div></div>
                            <span className="shrink-0 rounded-full bg-red-600 px-1.5 py-0.5 text-[9px] font-bold">Suivre</span>
                        </div>
                        <span className="flex shrink-0 items-center gap-1 rounded-full bg-black/40 px-2 py-1 text-[10px] font-semibold backdrop-blur"><Eye className="size-3" aria-hidden />{sim.viewers}</span>
                    </div>
                    <div className="relative mt-1.5 flex items-center gap-1.5 px-3"><OnAirBadge /><span className="text-[10px] text-white/80">{since || 'à l’instant'}</span></div>

                    {/* Centre : le vendeur à l'écran */}
                    <div className="relative flex flex-1 items-center justify-center">
                        <div className="relative">
                            <span className="absolute inset-0 animate-ping rounded-full bg-red-500/40 motion-reduce:animate-none" aria-hidden />
                            <div className="relative rounded-full border-[3px] border-red-500 p-0.5"><Avatar name={live.name} photo={live.photo} size="size-20" /></div>
                        </div>
                    </div>

                    {/* Cœurs */}
                    <div className="pointer-events-none absolute bottom-16 right-6 size-0" aria-hidden>
                        {sim.hearts.map((h) => <Heart key={h.id} style={{ '--x': `${h.x}px`, animation: 'ocd-float 2.3s ease-out forwards' } as CSSProperties} className="absolute -left-2.5 size-5 fill-red-500 text-red-500" />)}
                    </div>

                    {/* Commentaires */}
                    <ul aria-hidden className="relative mb-1.5 flex max-h-28 flex-col justify-end gap-1 overflow-hidden px-2.5 pr-12">
                        {sim.chat.slice(-4).map((m) => (
                            <li key={m.id} style={{ animation: 'ocd-in .3s ease-out' }} className="w-fit max-w-full rounded-2xl bg-black/35 px-2.5 py-1 text-[10px] leading-snug backdrop-blur">
                                <b style={{ color: `hsl(${hueOf(m.name)} 90% 75%)` }}>{m.name}</b> {m.text}
                            </li>
                        ))}
                    </ul>

                    {/* Bandeau : ce que le public voit à l'écran */}
                    <div className="relative mx-2.5 mb-2 rounded-md border-l-4 border-primary bg-white px-2 py-1.5 text-neutral-900">
                        <div className="text-[8px] font-bold uppercase tracking-wide text-[#B85600]">En direct avec</div>
                        <div className="truncate text-xs font-extrabold">{live.name}</div>
                        <div className="flag-font flex items-center gap-1.5 truncate text-[9px] text-neutral-600">{flag(live.country)} {country?.name ?? live.country}{cat && <><span aria-hidden>·</span><Icon className="size-3" aria-hidden />{cat.name}</>}</div>
                    </div>

                    {/* Barre du bas */}
                    <div className="relative flex items-center gap-1.5 px-2.5 pb-3">
                        <div className="flex-1 rounded-full bg-black/40 px-3 py-1.5 text-[10px] text-white/70 backdrop-blur">Dire quelque chose…</div>
                        <span className="flex size-7 items-center justify-center rounded-full bg-black/40 backdrop-blur"><ShoppingBag className="size-3.5" aria-hidden /></span>
                        <button type="button" onClick={sim.heart} aria-label="Envoyer un cœur" className="flex size-7 items-center justify-center rounded-full bg-black/40 backdrop-blur"><Heart className="size-3.5 fill-red-500 text-red-500" aria-hidden /></button>
                    </div>
                </div>
            </div>
            <figcaption className="mt-3 text-center text-xs text-neutral-500">Aperçu simulé : spectateurs et commentaires sont fictifs.</figcaption>
        </figure>
    );
}

/** Fenêtre « aperçu téléphone » : le téléphone à gauche, les informations et actions du live à droite. */
export function PhoneDialog({ live, url, countries, categories, onClose, children }: {
    live: PhoneLive; url: string; countries: Country[]; categories: LiveCategory[]; onClose: () => void; children?: ReactNode;
}) {
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => { document.removeEventListener('keydown', onKey); document.body.style.overflow = prev; };
    }, [onClose]);
    const country = countries.find((c) => c.code === live.country);
    const cat = categories.find((c) => c.code === live.category);

    return (
        <div role="dialog" aria-modal="true" aria-label={`Aperçu du live de ${live.name}`} className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/70 p-4" onClick={onClose}>
            <div className="relative grid w-full max-w-2xl gap-6 rounded-2xl bg-white p-6 sm:grid-cols-[auto_1fr] sm:items-center" onClick={(e) => e.stopPropagation()}>
                <button type="button" onClick={onClose} aria-label="Fermer" className="absolute right-3 top-3 rounded-full p-1.5 text-neutral-500 hover:bg-neutral-100"><X className="size-5" aria-hidden /></button>
                <LivePhone live={live} countries={countries} categories={categories} />
                <div className="grid content-center gap-3">
                    <div>
                        <div className="text-xs font-semibold uppercase tracking-widest text-neutral-500">Aperçu du live</div>
                        <h2 className="text-2xl font-extrabold">{live.name}</h2>
                        {live.handle && <div className="text-neutral-600">@{live.handle}</div>}
                    </div>
                    <div className="flag-font text-sm text-neutral-700">{flag(live.country)} {country?.name ?? live.country}{cat ? ` · ${cat.name}` : ''}</div>
                    <p className="text-sm text-neutral-600">Voici comment ce live apparaîtra aux spectateurs. Le vrai live se vérifie sur TikTok.</p>
                    <Button asChild size="lg"><a href={url} target="_blank" rel="noreferrer"><Play className="size-4 fill-current" aria-hidden />Ouvrir le live sur TikTok</a></Button>
                    {children}
                </div>
            </div>
        </div>
    );
}
