import { Card } from '@/components/hoc';
import { Markdown } from '@/components/markdown';
import SiteLayout from '@/layouts/site-layout';
import { Head } from '@inertiajs/react';

export default function Legal({ markdown }: { markdown: string }) {
    return (
        <SiteLayout title="Mentions légales et confidentialité">
            <Head title="Mentions légales et confidentialité" />
            <Card><Markdown text={markdown ?? ''} /></Card>
        </SiteLayout>
    );
}
