import { Button } from '@/components/ui/button';
import { fcfa, fmtDay, fmtLome, fmtTime, MODE_LABEL, STATUS_LABEL, tiktokHandle } from '@/lib/format';
import { Shared } from '@/lib/layout';
import { usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';

export type PosterData = { shop: string; startsAt: string; mode: string; handle: string; brand: string };

/** Découpe un texte en lignes qui tiennent dans la largeur donnée. */
function wrap(ctx: CanvasRenderingContext2D, text: string, maxWidth: number, maxLines: number): string[] {
    const lines: string[] = [];
    let line = '';
    for (const word of text.split(/\s+/)) {
        const test = line ? `${line} ${word}` : word;
        if (ctx.measureText(test).width > maxWidth && line) {
            lines.push(line);
            line = word;
        } else line = test;
    }
    if (line) lines.push(line);
    if (lines.length > maxLines) {
        lines.length = maxLines;
        lines[maxLines - 1] = lines[maxLines - 1].replace(/\s*\S*$/, '…');
    }
    return lines;
}

/** Affiche à partager (1080 × 1350, format réseaux sociaux) : « Je passe en live » avec le nom de la boutique, la date et l'heure. */
function drawPoster(d: PosterData): Promise<Blob> {
    const W = 1080;
    const H = 1350;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d')!;
    const font = (w: number, px: number) => `${w} ${px}px system-ui, -apple-system, "Segoe UI", Arial, sans-serif`;

    ctx.fillStyle = '#1F1F1F';
    ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = d.brand;
    ctx.fillRect(0, 0, W, 24);

    ctx.textBaseline = 'alphabetic';
    ctx.fillStyle = d.brand;
    ctx.font = font(800, 46);
    ctx.fillText('OCD LIVE', 80, 130);
    ctx.fillStyle = '#9a9a9a';
    ctx.font = font(500, 30);
    ctx.fillText('Les opportunités, c’est dehors', 80, 176);

    ctx.fillStyle = '#ffffff';
    ctx.font = font(900, 168);
    ctx.fillText('JE PASSE', 80, 400);
    ctx.fillStyle = d.brand;
    ctx.fillText('EN LIVE', 80, 570);

    ctx.fillStyle = '#9a9a9a';
    ctx.font = font(600, 34);
    ctx.fillText('SUR HOUSE OF CHALLENGE', 80, 650);

    ctx.fillStyle = '#ffffff';
    ctx.font = font(800, 84);
    wrap(ctx, d.shop, W - 160, 3).forEach((l, i) => ctx.fillText(l, 80, 780 + i * 98));

    ctx.fillStyle = d.brand;
    ctx.fillRect(80, 1040, 140, 8);

    const day = fmtDay(d.startsAt);
    ctx.fillStyle = '#ffffff';
    ctx.font = font(700, 54);
    ctx.fillText(day.charAt(0).toUpperCase() + day.slice(1), 80, 1120);
    ctx.font = font(800, 54);
    ctx.fillStyle = d.brand;
    ctx.fillText(`à ${fmtTime(d.startsAt)}`, 80, 1190);
    ctx.fillStyle = '#c9c9c9';
    ctx.font = font(500, 36);
    ctx.fillText(d.mode === 'SUR_PLACE' ? 'En studio, à Lomé' : 'À distance, sur TikTok', 80, 1245);

    if (d.handle) {
        ctx.fillStyle = '#9a9a9a';
        ctx.font = font(600, 34);
        ctx.textAlign = 'right';
        ctx.fillText(d.handle, W - 80, 1290);
        ctx.textAlign = 'left';
    }
    return new Promise((res, rej) => c.toBlob((b) => (b ? res(b) : rej(new Error('Image impossible à créer'))), 'image/png'));
}

/** Bouton « Mon affiche à partager » : partage direct sur téléphone (WhatsApp, Instagram…), ou téléchargement de l'image sur ordinateur. */
export function PosterButton({ shop, startsAt, mode }: { shop: string; startsAt: string; mode: string }) {
    const { site } = usePage<Shared>().props;
    const [busy, setBusy] = useState(false);

    const run = async () => {
        setBusy(true);
        try {
            const blob = await drawPoster({ shop, startsAt, mode, handle: tiktokHandle(site.tiktok), brand: site.brand_color || '#EE6400' });
            const file = new File([blob], 'mon-passage-ocd-live.png', { type: 'image/png' });
            if (navigator.canShare?.({ files: [file] })) {
                await navigator.share({ files: [file], title: 'Je passe en live' }).catch(() => undefined);
            } else {
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = file.name;
                a.click();
                setTimeout(() => URL.revokeObjectURL(a.href), 4000);
            }
        } finally {
            setBusy(false);
        }
    };

    return <Button onClick={run} disabled={busy}><Download className="size-4" aria-hidden />{busy ? 'Création de l’affiche…' : 'Mon affiche à partager'}</Button>;
}

/** Reçu de réservation : invisible à l'écran, il n'apparaît qu'à l'impression (A4) ou dans « Enregistrer en PDF ». */
export function Receipt({ reference, status, name, shop, city, packageName, minutes, startsAt, mode, amount }: {
    reference: string; status: string; name: string; shop: string; city: string; packageName: string; minutes: number; startsAt: string; mode: string; amount: number;
}) {
    const { site } = usePage<Shared>().props;
    const paid = ['CONFIRMEE', 'EFFECTUEE'].includes(status);
    const rows: [string, string][] = [
        ['Référence', reference], ['Nom', name], ['Boutique', `${shop} — ${city}`], ['Package', `${packageName} (${minutes} min)`],
        ['Date du passage', fmtLome(startsAt)], ['Lieu', MODE_LABEL[mode] ?? mode], ['Statut', paid ? 'Payé, réservation confirmée' : (STATUS_LABEL[status] ?? status)],
    ];
    return (
        <div className="hidden p-10 text-neutral-900 print:block">
            <div className="flex items-start justify-between border-b-2 border-neutral-900 pb-4">
                <div><div className="text-2xl font-extrabold">OCD Live</div><div className="text-sm text-neutral-600">House of Challenge · Bovann Group Togo</div></div>
                <div className="text-right text-sm"><div className="text-lg font-bold">Reçu de réservation</div><div>Édité le {new Date().toLocaleDateString('fr-FR')}</div></div>
            </div>
            <table className="mt-6 w-full text-sm">
                <tbody>{rows.map(([k, v]) => <tr key={k} className="border-b"><th className="w-48 py-2 text-left font-medium text-neutral-600">{k}</th><td className="py-2">{v}</td></tr>)}</tbody>
            </table>
            <div className="mt-6 flex items-center justify-between rounded border-2 border-neutral-900 p-4"><span className="font-semibold">Montant {paid ? 'payé' : 'à payer'}</span><span className="text-2xl font-extrabold">{fcfa(amount)}</span></div>
            <p className="mt-8 text-xs text-neutral-500">{site.studio}. Ce document confirme votre réservation ; conservez-le avec votre référence {reference}.</p>
        </div>
    );
}
