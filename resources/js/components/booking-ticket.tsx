import { OnAirBadge } from '@/components/live';
import { fcfa, fmtLome, MODE_LABEL } from '@/lib/format';
import { User } from 'lucide-react';

const Row = ({ label, value }: { label: string; value?: string | null }) => (
    <div>
        <dt className="text-xs text-neutral-400">{label}</dt>
        <dd className={value ? 'font-medium' : 'text-neutral-500'}>{value || '—'}</dd>
    </div>
);

/** Fiche « Votre réservation » : elle se remplit au fil des étapes (lieu, date, package) et affiche le total. */
export function BookingTicket({ pkgName, minutes, slotAt, mode, total }: { pkgName?: string; minutes?: number; slotAt?: string; mode?: string; total?: number }) {
    return (
        <div className="rounded-xl bg-[#1F1F1F] p-5 text-white">
            <div className="text-xs font-semibold uppercase tracking-widest text-[#FE7C02]">Votre réservation</div>
            <dl className="mt-4 grid gap-3 text-sm">
                <Row label="Où" value={mode ? MODE_LABEL[mode] : null} />
                <Row label="Quand" value={slotAt ? fmtLome(slotAt) : null} />
                <Row label="Package" value={pkgName ? `${pkgName}${minutes ? ` · ${minutes} min` : ''}` : null} />
            </dl>
            <div className="mt-4 flex items-end justify-between border-t border-dashed border-white/25 pt-4">
                <span className="text-sm text-neutral-300">Total</span>
                <span className="text-2xl font-extrabold">{total ? fcfa(total) : '—'}</span>
            </div>
        </div>
    );
}

/** Aperçu indicatif du live : le nom, le numéro et la ville du vendeur s'affichent à l'écran, et se mettent à jour pendant la saisie. */
export function LivePhonePreview({ shopName, phone, city }: { shopName?: string; phone?: string; city?: string }) {
    return (
        <figure className="mx-auto w-44">
            <div className="overflow-hidden rounded-[2rem] border-[6px] border-neutral-900 bg-neutral-900 shadow-xl">
                <div className="relative flex aspect-[9/17] flex-col bg-gradient-to-b from-neutral-600 via-neutral-800 to-black text-white">
                    <div className="flex items-center justify-between gap-2 p-3">
                        <OnAirBadge />
                        <span className="truncate text-[10px] font-semibold text-white/70">@houseofchallenge</span>
                    </div>
                    <div className="flex flex-1 items-center justify-center" aria-hidden><User className="size-20 text-white/15" /></div>
                    <ul className="mx-3 grid gap-1 text-[10px]" aria-hidden>
                        {['Quel est le prix ?', 'Vous livrez à Lomé ?', 'Je prends 2 !'].map((t) => <li key={t} className="w-fit rounded-full bg-black/40 px-2.5 py-1">{t}</li>)}
                    </ul>
                    <div className="m-3 rounded-lg border-l-4 border-primary bg-white p-2.5 text-neutral-900">
                        <div className="text-[9px] font-bold uppercase tracking-wide text-[#B85600]">En direct avec</div>
                        <div className={`truncate text-sm font-extrabold ${shopName ? '' : 'text-neutral-300'}`}>{shopName || 'Le nom de votre boutique'}</div>
                        <div className={`truncate text-[11px] ${phone || city ? 'text-neutral-600' : 'text-neutral-300'}`}>{[phone, city].filter(Boolean).join(' · ') || 'Votre numéro · Votre ville'}</div>
                    </div>
                </div>
            </div>
            <figcaption className="mt-2 text-center text-xs text-neutral-500">Aperçu indicatif : votre boutique à l’écran.</figcaption>
        </figure>
    );
}
