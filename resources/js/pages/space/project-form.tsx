import { Field, inputCls } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { Head, useForm } from '@inertiajs/react';

type Project = {
    id: number; title: string; sector: string; stage: string; short_pitch: string; description: string; needs: string[] | null;
    country: string; links: Record<string, string> | null; contact_pref: string;
} | null;

const LINKS = ['website', 'facebook', 'instagram', 'tiktok', 'linkedin'];

export default function ProjectForm({ project, sectors, stages, needs }: { project: Project; sectors: string[]; stages: Record<string, string>; needs: string[] }) {
    const { data, setData, post, processing, errors } = useForm<any>({
        title: project?.title ?? '', sector: project?.sector ?? sectors[0], stage: project?.stage ?? 'IDEE', short_pitch: project?.short_pitch ?? '',
        description: project?.description ?? '', needs: project?.needs ?? [], country: project?.country ?? 'Togo',
        links: project?.links ?? {}, contact_pref: project?.contact_pref ?? 'WHATSAPP', logo: null, images: [],
    });
    const links = data.links as Record<string, string>;
    const sel = data.needs as string[];
    const err = errors as Record<string, string>;

    return (
        <SiteLayout portal="space" title={project ? 'Modifier le projet' : 'Nouveau projet'}>
            <Head title="Projet" />
            <form onSubmit={(e) => { e.preventDefault(); post(project ? route('space.projects.update', project.id) : route('space.projects.store'), { forceFormData: true }); }}
                className="grid max-w-2xl gap-3 rounded-lg border bg-white p-5">
                <Field label="Titre" error={err.title}><input className={inputCls} value={data.title as string} onChange={(e) => setData('title', e.target.value)} required /></Field>
                <div className="grid gap-3 md:grid-cols-2">
                    <Field label="Secteur" error={err.sector}><select className={inputCls} value={data.sector as string} onChange={(e) => setData('sector', e.target.value)}>{sectors.map((s) => <option key={s}>{s}</option>)}</select></Field>
                    <Field label="Stade" error={err.stage}><select className={inputCls} value={data.stage as string} onChange={(e) => setData('stage', e.target.value)}>{Object.entries(stages).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></Field>
                </div>
                <Field label={`Pitch court (${(data.short_pitch as string).length}/280)`} error={err.short_pitch}><textarea rows={2} maxLength={280} className={inputCls} value={data.short_pitch as string} onChange={(e) => setData('short_pitch', e.target.value)} required /></Field>
                <Field label="Description" error={err.description}><textarea rows={6} className={inputCls} value={data.description as string} onChange={(e) => setData('description', e.target.value)} required /></Field>
                <fieldset className="text-sm"><legend className="mb-1 font-medium">Besoins</legend>
                    <div className="flex flex-wrap gap-3">{needs.map((n) => (
                        <label key={n} className="flex items-center gap-1"><input type="checkbox" checked={sel.includes(n)} onChange={(e) => setData('needs', e.target.checked ? [...sel, n] : sel.filter((x) => x !== n))} />{n}</label>
                    ))}</div>
                </fieldset>
                <Field label="Pays du projet" error={err.country}><input className={inputCls} value={data.country as string} onChange={(e) => setData('country', e.target.value)} required /></Field>
                <div className="grid gap-3 md:grid-cols-2">
                    {LINKS.map((k) => <Field key={k} label={`Lien ${k}`} error={err[`links.${k}`]}><input type="url" className={inputCls} placeholder="https://" value={links[k] ?? ''} onChange={(e) => setData('links', { ...links, [k]: e.target.value })} /></Field>)}
                </div>
                <Field label="Contact public via" error={err.contact_pref}>
                    <select className={inputCls} value={data.contact_pref as string} onChange={(e) => setData('contact_pref', e.target.value)}><option value="WHATSAPP">WhatsApp</option><option value="EMAIL">E-mail</option></select>
                </Field>
                <Field label="Logo (≤ 2 Mo)" error={err.logo}><input type="file" accept="image/*" onChange={(e) => setData('logo', e.target.files?.[0] ?? null)} /></Field>
                <Field label="Images (3 max, ≤ 2 Mo)" error={err.images}><input type="file" accept="image/*" multiple onChange={(e) => setData('images', Array.from(e.target.files ?? []).slice(0, 3))} /></Field>
                <button disabled={processing} className="rounded-md bg-[#C8662B] px-4 py-2 text-white disabled:opacity-60">Enregistrer</button>
            </form>
        </SiteLayout>
    );
}
