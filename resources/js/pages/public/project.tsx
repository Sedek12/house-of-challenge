import { Card } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fmtDay } from '@/lib/format';
import { Head } from '@inertiajs/react';

type Project = {
    title: string; sector: string; stage: string; short_pitch: string; description: string; needs: string[] | null; country: string;
    links: Record<string, string> | null; logo_url: string | null; image_urls: string[] | null; owner: string; contact: string; presented_at: string | null;
};

export default function ProjectPage({ project: p }: { project: Project }) {
    return (
        <SiteLayout>
            <Head title={p.title}>
                <meta property="og:title" content={p.title} />
                <meta property="og:description" content={p.short_pitch} />
                {p.logo_url && <meta property="og:image" content={p.logo_url} />}
                <meta property="og:type" content="article" />
            </Head>
            <Card>
                <div className="flex items-start gap-4">
                    {p.logo_url && <img src={p.logo_url} alt={`Logo de ${p.title}`} className="h-20 w-20 rounded object-cover" />}
                    <div>
                        <h1 className="text-2xl font-bold">{p.title}</h1>
                        <div className="text-sm text-neutral-500">{p.sector} · {p.country} · par {p.owner}</div>
                        {p.presented_at && <div className="mt-1 text-sm font-medium text-[#B05A26]">Présenté à House of Challenge le {fmtDay(p.presented_at)}</div>}
                    </div>
                </div>
                <p className="mt-4 font-medium">{p.short_pitch}</p>
                <p className="mt-3 whitespace-pre-line text-sm">{p.description}</p>
                {p.needs && p.needs.length > 0 && <p className="mt-3 text-sm"><b>Besoins :</b> {p.needs.join(', ')}</p>}
                {p.links && (
                    <ul className="mt-3 flex flex-wrap gap-3 text-sm">
                        {Object.entries(p.links).map(([k, v]) => <li key={k}><a className="text-[#B05A26] underline" href={v} target="_blank" rel="noopener noreferrer nofollow">{k}</a></li>)}
                    </ul>
                )}
                {p.image_urls?.length ? <div className="mt-4 grid grid-cols-3 gap-2">{p.image_urls.map((u) => <img key={u} src={u} alt="" loading="lazy" className="rounded" />)}</div> : null}
                <Button asChild className="mt-5"><a href={p.contact} target="_blank" rel="noreferrer">Contacter</a></Button>
            </Card>
        </SiteLayout>
    );
}
