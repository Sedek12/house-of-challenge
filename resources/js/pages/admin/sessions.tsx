import { Card, Field, Pagination, StatusBadge, inputCls } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fmtLome } from '@/lib/format';
import { Head, Link, useForm } from '@inertiajs/react';

type S = { id: number; title: string; starts_at: string; status: string; slots_count: number; booked_count: number };
const DAYS = [[1, 'Lun'], [2, 'Mar'], [3, 'Mer'], [4, 'Jeu'], [5, 'Ven'], [6, 'Sam'], [7, 'Dim']] as const;

export default function Sessions({ sessions }: { sessions: { data: S[]; links: never[] } }) {
    const { data, setData, post, processing, errors } = useForm({
        title: 'Live OCD', repeat: 'once', date: '', weekdays: [1, 2, 3, 4, 5, 6] as number[], weeks: '2',
        start_time: '19:00', end_time: '22:00', accepted_modes: ['SUR_PLACE', 'A_DISTANCE'] as string[], slot_minutes: '15', break_minutes: '0',
    });
    const err = errors as Record<string, string>;
    const toggle = <T,>(list: T[], v: T) => (list.includes(v) ? list.filter((x) => x !== v) : [...list, v]);
    return (
        <SiteLayout portal="admin" title="Créneaux">
            <Head title="Créneaux" />
            <Card className="mb-6">
                <h2 className="mb-3 font-bold">Ouvrir des créneaux</h2>
                <form onSubmit={(e) => { e.preventDefault(); post(route('admin.sessions.store')); }} className="grid gap-3 md:grid-cols-3">
                    <Field label="Titre" error={err.title}><input className={inputCls} value={data.title} onChange={(e) => setData('title', e.target.value)} /></Field>
                    <Field label="Mode d’ouverture">
                        <select className={inputCls} value={data.repeat} onChange={(e) => setData('repeat', e.target.value)}>
                            <option value="once">Une date précise</option><option value="weekly">Modèle hebdomadaire</option>
                        </select>
                    </Field>
                    <Field label={data.repeat === 'weekly' ? 'À partir du' : 'Date'} error={err.date}><input type="date" className={inputCls} value={data.date} onChange={(e) => setData('date', e.target.value)} required /></Field>
                    {data.repeat === 'weekly' && (
                        <>
                            <fieldset className="md:col-span-2"><legend className="mb-1 text-sm font-medium">Jours des lives</legend>
                                <div className="flex flex-wrap gap-2">{DAYS.map(([n, l]) => (
                                    <label key={n} className="flex items-center gap-1 rounded border bg-white px-2 py-1 text-sm"><input type="checkbox" checked={data.weekdays.includes(n)} onChange={() => setData('weekdays', toggle(data.weekdays, n))} />{l}</label>
                                ))}</div>
                                {err.weekdays && <span className="text-sm text-red-600">{err.weekdays}</span>}
                            </fieldset>
                            <Field label="Nombre de semaines" error={err.weeks}><input className={inputCls} inputMode="numeric" value={data.weeks} onChange={(e) => setData('weeks', e.target.value)} /></Field>
                        </>
                    )}
                    <Field label="Début (heure de Lomé)" error={err.start_time}><input type="time" className={inputCls} value={data.start_time} onChange={(e) => setData('start_time', e.target.value)} /></Field>
                    <Field label="Fin" error={err.end_time}><input type="time" className={inputCls} value={data.end_time} onChange={(e) => setData('end_time', e.target.value)} /></Field>
                    <Field label="Durée d’un passage (min)" error={err.slot_minutes}><input className={inputCls} inputMode="numeric" value={data.slot_minutes} onChange={(e) => setData('slot_minutes', e.target.value)} /></Field>
                    <Field label="Pause entre passages (min)" error={err.break_minutes}><input className={inputCls} inputMode="numeric" value={data.break_minutes} onChange={(e) => setData('break_minutes', e.target.value)} /></Field>
                    <fieldset><legend className="mb-1 text-sm font-medium">Lieux de passage</legend>
                        <div className="flex gap-2">{([['SUR_PLACE', 'Au studio'], ['A_DISTANCE', 'À distance']] as const).map(([m, l]) => (
                            <label key={m} className="flex items-center gap-1 text-sm"><input type="checkbox" checked={data.accepted_modes.includes(m)} onChange={() => setData('accepted_modes', toggle(data.accepted_modes, m))} />{l}</label>
                        ))}</div>
                    </fieldset>
                    <div className="md:col-span-3"><Button disabled={processing}>Ouvrir les créneaux</Button></div>
                </form>
            </Card>

            <div className="grid gap-2">
                {sessions.data.map((s) => (
                    <Card key={s.id} className="flex flex-wrap items-center gap-3">
                        <Link className="font-semibold text-[#B85600] underline" href={route('admin.sessions.show', s.id)}>{s.title}</Link>
                        <span className="text-sm">{fmtLome(s.starts_at)}</span><StatusBadge status={s.status} />
                        <span className="ml-auto text-sm">{s.booked_count}/{s.slots_count} réservés</span>
                    </Card>
                ))}
            </div>
            <Pagination links={sessions.links} />
        </SiteLayout>
    );
}
