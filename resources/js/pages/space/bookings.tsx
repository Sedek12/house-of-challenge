import { Card, ModeBadge, SlotDto, SlotPicker, StatusBadge } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fmtLome, localHint } from '@/lib/format';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type B = { id: number; starts_at: string; mode: string; project: string; status: string; can_change: boolean; deadline_hours: number };

export default function Bookings({ upcoming, past, slots }: { upcoming: B[]; past: B[]; slots: SlotDto[] }) {
    const [moving, setMoving] = useState<number | null>(null);

    return (
        <SiteLayout portal="space" title="Mes réservations">
            <Head title="Mes réservations" />
            <h2 className="mb-2 font-bold">À venir</h2>
            {upcoming.length === 0 && <Card className="text-sm">Aucune réservation à venir.</Card>}
            <div className="grid gap-3">
                {upcoming.map((b) => (
                    <Card key={b.id}>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <div className="font-semibold">{fmtLome(b.starts_at)}</div>
                                {localHint(b.starts_at) && <div className="text-xs text-neutral-500">{localHint(b.starts_at)}</div>}
                                <div className="text-sm">{b.project}</div>
                            </div>
                            <div className="flex items-center gap-2"><ModeBadge mode={b.mode} /><StatusBadge status={b.status} /></div>
                        </div>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" asChild><a href={route('ics', b.id)}>Agenda (.ics)</a></Button>
                            <Button variant="outline" size="sm" disabled={!b.can_change} onClick={() => confirm('Annuler cette réservation ? Votre crédit vous sera rendu.') && router.post(route('space.bookings.cancel', b.id))}>Annuler</Button>
                            <Button variant="outline" size="sm" disabled={!b.can_change} onClick={() => setMoving(b.id)}>Reporter</Button>
                        </div>
                        {b.status === 'CONFIRMEE' && !b.can_change && (
                            <p className="mt-2 text-xs text-neutral-500">Annulation et report impossibles moins de {b.deadline_hours} h avant le passage.</p>
                        )}
                        {moving === b.id && (
                            <div className="mt-4 border-t pt-3">
                                <SlotPicker slots={slots.filter((x) => x.modes.includes(b.mode))} onSelect={(s) => { router.post(route('space.bookings.reschedule', b.id), { slot_id: s.id }, { onSuccess: () => setMoving(null) }); }} />
                            </div>
                        )}
                    </Card>
                ))}
            </div>
            <h2 className="mb-2 mt-8 font-bold">Passées</h2>
            <div className="grid gap-2">
                {past.map((b) => <Card key={b.id} className="flex flex-wrap justify-between gap-2 text-sm"><span>{fmtLome(b.starts_at)} — {b.project}</span><StatusBadge status={b.status} /></Card>)}
            </div>
        </SiteLayout>
    );
}
