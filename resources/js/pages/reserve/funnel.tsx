import { Card, ModeBadge, PackageCard, Pkg, SlotDto, SlotPicker } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fmtLome, MODE_LABEL } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Props = {
    projects: { id: number; title: string }[]; slots: SlotDto[]; packages: Pkg[]; credits: number;
    pending: { id: number; mode: string; starts_at: string; hold_expires_at: string | null } | null;
    query: { mode?: string; slot?: string; project?: string }; needs_tiktok: boolean;
};

function Countdown({ until }: { until: string | null }) {
    const [left, setLeft] = useState(0);
    useEffect(() => {
        const tick = () => setLeft(Math.max(0, Math.floor((new Date(until ?? 0).getTime() - Date.now()) / 1000)));
        tick();
        const t = setInterval(tick, 1000);
        return () => clearInterval(t);
    }, [until]);
    return <b>{String(Math.floor(left / 60)).padStart(2, '0')}:{String(left % 60).padStart(2, '0')}</b>;
}

export default function Funnel({ projects, slots, packages, credits, pending, query, needs_tiktok }: Props) {
    const mode = query.mode;
    const slotId = query.slot ? Number(query.slot) : null;
    const projectId = query.project ? Number(query.project) : null;
    const nav = (q: Record<string, unknown>) => router.get(route('reserve'), { ...query, ...q }, { preserveState: true, replace: true });

    const step = pending ? 5 : !projects.length ? 2 : !projectId ? 2 : !mode ? 3 : !slotId ? 4 : 4;
    const steps = ['Compte', 'Projet', 'Mode', 'Créneau', 'Paiement'];

    return (
        <SiteLayout title="Réserver mon passage">
            <Head title="Réserver" />
            <ol className="mb-6 flex gap-2 text-xs sm:text-sm">
                {steps.map((s, i) => (
                    <li key={s} className={`flex-1 rounded px-2 py-1 text-center ${i + 1 === step ? 'bg-[#C8662B] text-white' : i + 1 < step ? 'bg-green-100' : 'bg-neutral-200'}`}>{i + 1}. {s}</li>
                ))}
            </ol>

            {pending ? (
                <div className="grid gap-4">
                    <Card className="border-amber-300 bg-amber-50">
                        Créneau réservé pour vous : <b>{fmtLome(pending.starts_at)}</b> <ModeBadge mode={pending.mode} /><br />
                        Terminez le paiement dans <Countdown until={pending.hold_expires_at} />, sinon le créneau sera libéré.
                    </Card>
                    <h2 className="text-lg font-bold">Choisissez une formule</h2>
                    {packages.length === 0 && <Card>Aucune formule disponible pour ce mode. Contactez-nous.</Card>}
                    <div className="grid gap-4 md:grid-cols-3">
                        {packages.map((p) => (
                            <PackageCard key={p.id} pkg={p} action={<Button className="w-full" onClick={() => router.post(route('reserve.pay', pending.id), { package_id: p.id })}>Payer et confirmer</Button>} />
                        ))}
                    </div>
                </div>
            ) : (
                <div className="grid gap-6">
                    <section>
                        <h2 className="mb-2 text-lg font-bold">1. Votre projet</h2>
                        {projects.length === 0 ? (
                            <Card>Vous n’avez pas encore de projet. <Link className="text-[#C8662B] underline" href={route('space.projects.create')}>Créer ma fiche projet</Link></Card>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {projects.map((p) => (
                                    <button key={p.id} onClick={() => nav({ project: p.id })} className={`rounded-md border px-3 py-2 text-sm ${projectId === p.id ? 'border-[#C8662B] bg-[#C8662B] text-white' : 'bg-white'}`}>{p.title}</button>
                                ))}
                                <Link className="px-2 py-2 text-sm text-[#C8662B] underline" href={route('space.projects.create')}>+ Nouveau projet</Link>
                            </div>
                        )}
                    </section>

                    {projectId && (
                        <section>
                            <h2 className="mb-2 text-lg font-bold">2. Mode de passage</h2>
                            <div className="grid gap-3 md:grid-cols-2">
                                {(['SUR_PLACE', 'A_DISTANCE'] as const).map((m) => (
                                    <button key={m} onClick={() => nav({ mode: m, slot: undefined })} className={`rounded-lg border p-4 text-left ${mode === m ? 'border-[#C8662B] ring-2 ring-[#C8662B]' : 'bg-white'}`}>
                                        <div className="font-bold">{MODE_LABEL[m]}</div>
                                        <div className="text-sm text-neutral-600">{m === 'SUR_PLACE' ? 'Au studio OCD Live, au Togo.' : 'Depuis chez vous, invité sur le live TikTok.'}</div>
                                    </button>
                                ))}
                            </div>
                            {needs_tiktok && (
                                <Card className="mt-3 border-amber-300 bg-amber-50 text-sm">
                                    Le mode à distance nécessite votre pseudo TikTok. <Link className="text-[#C8662B] underline" href={route('profile.edit')}>Compléter mon profil</Link>
                                </Card>
                            )}
                        </section>
                    )}

                    {projectId && mode && !needs_tiktok && (
                        <section>
                            <h2 className="mb-2 text-lg font-bold">3. Créneau</h2>
                            <SlotPicker slots={slots} value={slotId} onSelect={(s) => nav({ slot: s.id })} />
                            {slotId && (
                                <div className="mt-4 flex flex-wrap items-center gap-3">
                                    <span className="text-sm">{credits > 0 ? `Vous avez ${credits} crédit(s) : il sera utilisé.` : 'Vous choisirez votre formule à l’étape suivante.'}</span>
                                    <Button onClick={() => router.post(route('reserve.start'), { project_id: projectId, slot_id: slotId, mode })}>
                                        {credits > 0 ? 'Confirmer ma réservation' : 'Continuer vers le paiement'}
                                    </Button>
                                </div>
                            )}
                        </section>
                    )}
                </div>
            )}
        </SiteLayout>
    );
}
