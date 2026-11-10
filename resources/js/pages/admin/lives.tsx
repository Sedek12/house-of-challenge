import { Card, inputCls } from '@/components/hoc';
import { Avatar, LiveCategory, LiveDto, OnAirBadge, useAutoRefresh, useSince, WindowBadge, WindowState } from '@/components/live';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { Country, flag } from '@/lib/countries';
import { fmtLome, fmtTime } from '@/lib/format';
import { Head, router } from '@inertiajs/react';
import { PhoneDialog } from '@/components/live-phone';
import { ArrowUpRight, Check, Smartphone } from 'lucide-react';
import { useCallback, useState } from 'react';

type Row = LiveDto & { status: string; phone: string; vendor: string | null; email: string | null; submitted_at: string; remaining: number | null; quota: number | null };
type Sub = { reference: string; vendor: string; email: string; phone: string | null; ends_at: string; used: number; quota: number };
type Props = { pending: Row[]; on_air: Row[]; recent: Row[]; subscriptions: Sub[]; countries: Country[]; categories: LiveCategory[]; window: WindowState };

const REASONS = ['Le live n’est pas en cours sur TikTok', 'Le lien ne mène pas à un live', 'Contenu non conforme (pas de vente)'];
const RECENT_LABEL: Record<string, string> = { TERMINE: 'Terminé', REFUSE: 'Refusé', EXPIRE: 'Expiré (non traité)' };

/** Modération des lives : l'administrateur ouvre le live sur TikTok, puis valide (affiché sur le site) ou refuse avec un motif. */
export default function AdminLives({ pending, on_air, recent, subscriptions, countries, categories, window: w }: Props) {
    const reload = useCallback(() => router.reload({ only: ['pending', 'on_air', 'recent', 'subscriptions', 'window'] }), []);
    useAutoRefresh(reload, 15_000);
    const where = (r: Row) => `${flag(r.country)} ${countries.find((c) => c.code === r.country)?.name ?? r.country}`;
    const cat = (r: Row) => categories.find((c) => c.code === r.category);
    const [preview, setPreview] = useState<Row | null>(null);

    return (
        <SiteLayout portal="admin" title="Lives à valider">
            <Head title="Lives à valider" />
            <WindowBadge window={w} className="mb-6" />

            <section className="mb-8">
                <h2 className="mb-3 text-lg font-bold">À vérifier ({pending.length})</h2>
                {pending.length === 0 && <Card className="text-neutral-600">Aucune demande en attente. Cette page se met à jour toute seule.</Card>}
                <div className="grid gap-3">
                    {pending.map((r) => <PendingRow key={r.id} row={r} where={where(r)} cat={cat(r)} onPreview={() => setPreview(r)} />)}
                </div>
            </section>

            <section className="mb-8">
                <h2 className="mb-3 text-lg font-bold">Affichés sur le site ({on_air.length})</h2>
                <div className="grid gap-3 md:grid-cols-2">
                    {on_air.map((r) => (
                        <Card key={r.id} className="flex items-center gap-3">
                            <Avatar name={r.name} photo={r.photo} size="size-12" />
                            <div className="min-w-0 flex-1">
                                <OnAirBadge />
                                <div className="truncate font-semibold">{r.name}</div>
                                <div className="flag-font text-xs text-neutral-500">{where(r)} · jusqu’à {fmtTime(r.until!)}</div>
                            </div>
                            <div className="grid gap-1">
                                <Button size="sm" variant="outline" onClick={() => setPreview(r)}><Smartphone className="size-3.5" aria-hidden />Aperçu</Button>
                                <Button asChild size="sm" variant="outline"><a href={r.url} target="_blank" rel="noreferrer">Ouvrir</a></Button>
                                <Button size="sm" variant="destructive" onClick={() => confirm(`Retirer le live de ${r.name} du site ?`) && router.post(route('admin.lives.remove', r.id), {}, { preserveScroll: true })}>Retirer</Button>
                            </div>
                        </Card>
                    ))}
                    {on_air.length === 0 && <Card className="text-neutral-600">Aucun live affiché en ce moment.</Card>}
                </div>
            </section>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <h2 className="mb-2 font-bold">Abonnés actifs ({subscriptions.length})</h2>
                    <table className="w-full text-sm">
                        <thead><tr className="text-left text-neutral-500"><th className="py-1">Vendeur</th><th>Lives</th><th>Fin</th></tr></thead>
                        <tbody className="divide-y">
                            {subscriptions.map((s) => (
                                <tr key={s.reference}>
                                    <td className="py-1.5"><div className="font-medium">{s.vendor}</div><div className="text-xs text-neutral-500">{s.phone} · {s.email}</div></td>
                                    <td>{s.used} / {s.quota}</td>
                                    <td className="text-xs">{fmtLome(s.ends_at).replace(' (heure de Lomé)', '')}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {subscriptions.length === 0 && <p className="text-sm text-neutral-600">Aucun abonnement actif.</p>}
                </Card>
                <Card>
                    <h2 className="mb-2 font-bold">Derniers lives traités</h2>
                    <ul className="divide-y text-sm">
                        {recent.map((r) => (
                            <li key={r.id} className="py-1.5"><b>{r.name}</b> · {RECENT_LABEL[r.status] ?? r.status} · <span className="text-neutral-500">{fmtLome(r.submitted_at).replace(' (heure de Lomé)', '')}</span></li>
                        ))}
                    </ul>
                    {recent.length === 0 && <p className="text-sm text-neutral-600">Rien pour le moment.</p>}
                </Card>
            </div>

            {preview && <PhoneDialog live={preview} url={preview.url} countries={countries} categories={categories} onClose={() => setPreview(null)} />}
        </SiteLayout>
    );
}

function PendingRow({ row: r, where, cat, onPreview }: { row: Row; where: string; cat?: LiveCategory; onPreview: () => void }) {
    const [refusing, setRefusing] = useState(false);
    const [reason, setReason] = useState(REASONS[0]);
    const waiting = useSince(r.submitted_at);
    const wa = `https://wa.me/${r.phone.replace(/\D/g, '')}`;
    return (
        <Card className="grid gap-3 border-l-4 border-l-amber-400">
            <div className="flex flex-wrap items-center gap-3">
                <Avatar name={r.name} photo={r.photo} size="size-12" />
                <div className="min-w-0 flex-1">
                    <div className="text-lg font-bold">{r.name}</div>
                    <div className="flag-font text-sm text-neutral-600">{where} · {cat?.name ?? r.category} · envoyé {waiting}</div>
                    <div className="text-xs text-neutral-500">{r.vendor} · <a className="underline" href={wa} target="_blank" rel="noreferrer">{r.phone}</a> · {r.email} · {r.remaining} live(s) restant(s) sur {r.quota}</div>
                </div>
                <Button variant="outline" onClick={onPreview}><Smartphone className="size-4" aria-hidden />Aperçu téléphone</Button>
                <Button asChild variant="outline"><a href={r.url} target="_blank" rel="noreferrer">Ouvrir le live sur TikTok <ArrowUpRight className="size-4" aria-hidden /></a></Button>
            </div>
            <div className="break-all text-xs text-neutral-500">{r.url}</div>
            {refusing ? (
                <div className="grid gap-2 rounded-md bg-red-50 p-3 sm:grid-cols-[1fr_auto_auto]">
                    <label className="sr-only" htmlFor={`reason-${r.id}`}>Motif du refus</label>
                    <input id={`reason-${r.id}`} list={`reasons-${r.id}`} className={inputCls} value={reason} onChange={(e) => setReason(e.target.value)} />
                    <datalist id={`reasons-${r.id}`}>{REASONS.map((x) => <option key={x} value={x} />)}</datalist>
                    <Button variant="destructive" onClick={() => router.post(route('admin.lives.reject', r.id), { reason }, { preserveScroll: true })}>Confirmer le refus</Button>
                    <Button variant="outline" onClick={() => setRefusing(false)}>Annuler</Button>
                </div>
            ) : (
                <div className="flex flex-wrap gap-2">
                    <Button onClick={() => router.post(route('admin.lives.approve', r.id), {}, { preserveScroll: true })}><Check className="size-4" aria-hidden />Valider et afficher</Button>
                    <Button variant="outline" onClick={() => setRefusing(true)}>Refuser…</Button>
                </div>
            )}
        </Card>
    );
}
