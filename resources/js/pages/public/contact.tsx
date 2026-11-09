import { Card } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { tiktokHandle } from '@/lib/format';
import { Head, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

type Props = { whatsapp: string | null; whatsapp_display: string | null; email: string | null; studio_address: string; maps_url: string | null };

function Channel({ title, children, action }: { title: string; children: ReactNode; action?: ReactNode }) {
    return (
        <Card className="flex flex-col gap-2">
            <h2 className="text-lg font-bold">{title}</h2>
            <div className="text-sm text-neutral-600">{children}</div>
            {action && <div className="mt-auto pt-2">{action}</div>}
        </Card>
    );
}

export default function Contact({ whatsapp, whatsapp_display, email, studio_address, maps_url }: Props) {
    const { site } = usePage().props as unknown as { site: { tiktok: string } };
    return (
        <SiteLayout title="Nous contacter">
            <Head title="Nous contacter" />
            <p className="mb-6 max-w-2xl text-neutral-600">Une question sur un package, un créneau ou votre réservation ? Écrivez-nous : si vous avez déjà réservé, indiquez votre numéro de réservation (OCD-XXXXXX) pour une réponse plus rapide.</p>
            <div className="grid gap-4 md:grid-cols-2">
                {whatsapp && (
                    <Channel title="WhatsApp" action={<Button asChild><a href={whatsapp} target="_blank" rel="noreferrer">Écrire sur WhatsApp</a></Button>}>
                        Le moyen le plus rapide de joindre l’équipe{whatsapp_display ? ` : ${whatsapp_display}` : ''}.
                    </Channel>
                )}
                {email && (
                    <Channel title="E-mail" action={<Button asChild variant="soft"><a href={`mailto:${email}`}>{email}</a></Button>}>
                        Pour les demandes qui ne sont pas urgentes.
                    </Channel>
                )}
                <Channel title="TikTok" action={<Button asChild variant="soft"><a href={site.tiktok} target="_blank" rel="noreferrer">{tiktokHandle(site.tiktok) || 'Voir le compte'}</a></Button>}>
                    Suivez les lives de Bovann sur le compte TikTok de House of Challenge.
                </Channel>
                <Channel title="Le studio" action={maps_url ? <Button asChild variant="soft"><a href={maps_url} target="_blank" rel="noreferrer">Voir le plan d’accès</a></Button> : undefined}>
                    {studio_address}
                </Channel>
            </div>
        </SiteLayout>
    );
}