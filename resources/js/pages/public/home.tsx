import { Card, Pkg, PackageCard, ReserveButton } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fmtLome } from '@/lib/format';
import { Head, Link } from '@inertiajs/react';

type P = { id: number; title: string; slug: string; sector: string; country: string; short_pitch: string; logo_url: string | null };

export default function Home({ packages, sessions, projects }: { packages: Pkg[]; sessions: { id: number; title: string; starts_at: string }[]; projects: P[] }) {
    const steps = ['Créez votre compte et votre fiche projet', 'Choisissez sur place (Togo) ou à distance (TikTok)', 'Réservez votre créneau dans le calendrier', 'Payez et passez en live !'];
    return (
        <SiteLayout>
            <Head title="Accueil" />
            <section className="rounded-xl bg-[#1F1F1F] px-6 py-12 text-center text-white">
                <h1 className="text-3xl font-extrabold md:text-5xl">Présentez votre projet en <span className="text-[#C8662B]">live</span></h1>
                <p className="mx-auto mt-4 max-w-2xl text-neutral-300">
                    House of Challenge, l’émission TikTok de Bovann tournée au studio OCD Live (Togo). Faites interviewer votre projet devant des milliers de spectateurs — sur place ou depuis chez vous.
                </p>
                <div className="mt-6 flex justify-center gap-3">
                    <ReserveButton>Je veux passer à House of Challenge</ReserveButton>
                    <Button asChild variant="secondary"><Link href={route('calendar')}>Voir le calendrier</Link></Button>
                </div>
            </section>

            <section className="mt-10">
                <h2 className="mb-4 text-xl font-bold">Comment ça marche</h2>
                <div className="grid gap-3 md:grid-cols-4">
                    {steps.map((s, i) => (
                        <Card key={i}><div className="text-2xl font-extrabold text-[#C8662B]">{i + 1}</div><p className="text-sm">{s}</p></Card>
                    ))}
                </div>
            </section>

            <section className="mt-10">
                <h2 className="mb-4 text-xl font-bold">Nos formules</h2>
                <div className="grid gap-4 md:grid-cols-3">
                    {packages.map((p) => <PackageCard key={p.id} pkg={p} action={<ReserveButton>Choisir</ReserveButton>} />)}
                </div>
            </section>

            {sessions.length > 0 && (
                <section className="mt-10">
                    <h2 className="mb-4 text-xl font-bold">Prochaines sessions</h2>
                    <div className="grid gap-2">
                        {sessions.map((s) => <Card key={s.id}><b>{s.title}</b> — {fmtLome(s.starts_at)}</Card>)}
                    </div>
                </section>
            )}

            {projects.length > 0 && (
                <section className="mt-10">
                    <h2 className="mb-4 text-xl font-bold">Projets à la une</h2>
                    <div className="grid gap-4 md:grid-cols-3">
                        {projects.map((p) => <ProjectCard key={p.id} p={p} />)}
                    </div>
                </section>
            )}
        </SiteLayout>
    );
}

export function ProjectCard({ p }: { p: P }) {
    return (
        <Link href={route('projects.show', { slug: p.slug })}>
            <Card className="h-full hover:border-[#C8662B]">
                {p.logo_url && <img src={p.logo_url} alt="" loading="lazy" className="mb-2 h-16 w-16 rounded object-cover" />}
                <div className="font-bold">{p.title}</div>
                <div className="text-xs text-neutral-500">{p.sector} · {p.country}</div>
                <p className="mt-1 text-sm">{p.short_pitch}</p>
            </Card>
        </Link>
    );
}
