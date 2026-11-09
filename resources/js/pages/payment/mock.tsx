import { Card } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { fcfa } from '@/lib/format';
import { Head, router } from '@inertiajs/react';

export default function Mock({ reference, amount, name }: { reference: string; amount: number; name: string }) {
    const send = (result: 'PAID' | 'FAILED') => router.post(route('payment.mock.result'), { ref: reference, result });
    return (
        <SiteLayout title="Paiement simulé (développement)">
            <Head title="Paiement simulé" />
            <Card className="max-w-md">
                <p>Package : <b>{name}</b></p>
                <p>Montant : <b>{fcfa(amount)}</b></p>
                <div className="mt-4 flex gap-2">
                    <Button onClick={() => send('PAID')}>Simuler succès</Button>
                    <Button variant="destructive" onClick={() => send('FAILED')}>Simuler échec</Button>
                </div>
            </Card>
        </SiteLayout>
    );
}
