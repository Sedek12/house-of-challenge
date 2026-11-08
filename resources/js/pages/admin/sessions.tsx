import { Card, Field, Pagination, StatusBadge, inputCls } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { fmtLome } from '@/lib/format';
import { Head, Link, useForm } from '@inertiajs/react';

type S = { id: number; title: string; starts_at: string; status: string; slots_count: number; booked_count: number };

export default function Sessions({ sessions }: { sessions: { data: S[]; links: never[] } }) {
    const { data, setData, post, processing, errors } = useForm({
        title: 'House of Challenge — Live', date: '', start_time: '19:00', end_time: '21:00', accepted_modes: ['SUR_PLACE', 'A_DISTANCE'],
        slot_minutes: 15, break_minutes: 0, tiktok_live_url: '', notes: '',
    });
    const err = errors as Record<string, string>;
    return (
        <SiteLayout portal="admin" title="Sessions de live">
            <Head title="Sessions" />
            <Card className="mb-6">
                <b>Créer une session</b> <span className="text-xs text-neutral-500">(heures saisies en heure de Lomé)</span>
                <form onSubmit={(e) => { e.preventDefault(); post(route('admin.sessions.store')); }} className="mt-3 grid gap-3 md:grid-cols-4">
                    <div className="md:col-span-2"><Field label="Titre" error={err.title}><input className={inputCls} value={data.title} onChange={(e) => setData('title', e.target.value)} required /></Field></div>
                    <Field label="Date" error={err.date}><input type="date" className={inputCls} value={data.date} onChange={(e) => setData('date', e.target.value)} required /></Field>
                    <div />
                    <Field label="Début" error={err.start_time}><input type="time" className={inputCls} value={data.start_time} onChange={(e) => setData('start_time', e.target.value)} required /></Field>
                    <Field label="Fin" error={err.end_time}><input type="time" className={inputCls} value={data.end_time} onChange={(e) => setData('end_time', e.target.value)} required /></Field>
                    <Field label="Durée d’un créneau (min)" error={err.slot_minutes}><input type="number" className={inputCls} value={data.slot_minutes} onChange={(e) => setData('slot_minutes', Number(e.target.value))} /></Field>
                    <Field label="Pause entre créneaux (min)" error={err.break_minutes}><input type="number" className={inputCls} value={data.break_minutes} onChange={(e) => setData('break_minutes', Number(e.target.value))} /></Field>
                    <div className="md:col-span-2"><Field label="Lien du live TikTok" error={err.tiktok_live_url}><input type="url" className={inputCls} value={data.tiktok_live_url} onChange={(e) => setData('tiktok_live_url', e.target.value)} /></Field></div>
                    <div className="flex items-center gap-4 text-sm md:col-span-2">
                        {['SUR_PLACE', 'A_DISTANCE'].map((m) => (
                            <label key={m}><input type="checkbox" checked={data.accepted_modes.includes(m)} onChange={(e) => setData('accepted_modes', e.target.checked ? [...data.accepted_modes, m] : data.accepted_modes.filter((x) => x !== m))} /> {m === 'SUR_PLACE' ? 'Sur place' : 'À distance'}</label>
                        ))}
                    </div>
                    <button disabled={processing} className="rounded-md bg-[#C8662B] px-4 py-2 text-white md:col-span-4">Créer et générer les créneaux</button>
                </form>
            </Card>
            <div className="grid gap-2">
                {sessions.data.map((s) => (
                    <Link key={s.id} href={route('admin.sessions.show', s.id)}>
                        <Card className="flex flex-wrap items-center justify-between gap-2 hover:border-[#C8662B]">
                            <div><b>{s.title}</b><div className="text-sm">{fmtLome(s.starts_at)}</div></div>
                            <div className="flex items-center gap-2 text-sm">{s.booked_count}/{s.slots_count} réservés <StatusBadge status={s.status} /></div>
                        </Card>
                    </Link>
                ))}
            </div>
            <Pagination links={sessions.links} />
        </SiteLayout>
    );
}
