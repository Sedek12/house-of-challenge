import { Card, ReserveButton } from '@/components/hoc';
import { Markdown } from '@/components/markdown';
import { OcdBand } from '@/components/ocd-band';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { tiktokHandle } from '@/lib/format';
import { Head, Link, usePage } from '@inertiajs/react';

export default function About({ concept, hero_image }: { concept: string; hero_image: string }) {
    const { site } = usePage().props as unknown as { site: { tiktok: string } };
    return (
        <SiteLayout title="À propos">
            <Head title="À propos" />
            <OcdBand />

            <section className="mt-8 grid gap-6 md:grid-cols-3">
                <div className="md:col-span-2"><Card className="h-full"><Markdown text={concept ?? ''} /></Card></div>
                <div className="grid content-start gap-4">
                    {hero_image && <img src={hero_image} alt="Le studio OCD Live : mur lumineux en forme de carte de l’Afrique" loading="lazy" className="aspect-square w-full rounded-lg object-cover shadow-sm" />}
                    <Button asChild variant="soft"><a href={site.tiktok} target="_blank" rel="noreferrer">{tiktokHandle(site.tiktok) ? `Voir ${tiktokHandle(site.tiktok)} sur TikTok` : 'Voir House of Challenge sur TikTok'}</a></Button>
                </div>
            </section>

            <section className="mt-8 flex flex-wrap items-center gap-3">
                <ReserveButton>Réserver mon passage</ReserveButton>
                <Button asChild variant="soft"><Link href={route('packages')}>Découvrir les packages</Link></Button>
            </section>
        </SiteLayout>
    );
}