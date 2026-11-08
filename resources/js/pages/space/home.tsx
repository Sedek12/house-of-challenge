import { Card, ModeBadge, ReserveButton } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fmtLome } from '@/lib/format';
import { Head, Link, usePage } from '@inertiajs/react';

type B = { id: number; starts_at: string; mode: string; project: string; status: string };

export default function Home({ credits, next_expiry, upcoming }: { credits: number; next_expiry: string | null; upcoming: B[] }) {
    const { auth } = usePage<{ auth: { user: { first_name: string; email_verified_at: string | null } } }>().props;
    return (
        <SiteLayout portal="space" title={`Bonjour ${auth.user.first_name} 👋`}>
            <Head title="Mon espace" />
            {!auth.user.email_verified_at && (
                <Card className="mb-4 border-amber-300 bg-amber-50 text-sm">
                    Vérifiez votre e-mail (nécessaire pour la publication de votre projet).{' '}
                    <Link method="post" as="button" href={route('verification.send')} className="text-[#C8662B] underline">Renvoyer le lien</Link>
                </Card>
            )}
            <div className="grid gap-4 md:grid-cols-3">
                <Card>
                    <div className="text-sm text-neutral-500">Crédits de passage</div>
                    <div className="text-4xl font-extrabold text-[#C8662B]">{credits}</div>
                    {next_expiry && <div className="text-xs text-neutral-500">Prochaine expiration : {new Date(next_expiry).toLocaleDateString('fr-FR')}</div>}
                    <Button asChild variant="outline" size="sm" className="mt-3"><Link href={route('packages')}>Acheter des crédits</Link></Button>
                </Card>
                <Card className="md:col-span-2">
                    <div className="mb-2 font-bold">Prochains passages</div>
                    {upcoming.length === 0 && <p className="text-sm text-neutral-600">Aucun passage prévu.</p>}
                    {upcoming.map((b) => (
                        <div key={b.id} className="flex flex-wrap items-center justify-between gap-2 border-t py-2 text-sm">
                            <span>{fmtLome(b.starts_at)} — {b.project}</span>
                            <ModeBadge mode={b.mode} />
                        </div>
                    ))}
                    <div className="mt-3"><ReserveButton /></div>
                </Card>
            </div>
        </SiteLayout>
    );
}
