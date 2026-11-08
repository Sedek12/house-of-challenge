import { Card } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { Head } from '@inertiajs/react';

/** Rendu Markdown minimal (titres + paragraphes), sans HTML injecté. */
export function Markdown({ text }: { text: string }) {
    return (
        <div className="grid gap-2 text-sm leading-relaxed">
            {text.split(/\n{2,}|\n(?=#)/).map((block, i) => {
                const m = block.match(/^(#{1,3})\s+(.*)$/s);
                if (m) return m[1].length === 1 ? <h2 key={i} className="mt-3 text-xl font-bold">{m[2]}</h2> : <h3 key={i} className="mt-3 text-lg font-semibold">{m[2]}</h3>;
                return <p key={i} className="whitespace-pre-line">{block}</p>;
            })}
        </div>
    );
}

export default function Page({ title, markdown }: { title: string; markdown: string }) {
    return (
        <SiteLayout title={title}>
            <Head title={title} />
            <Card><Markdown text={markdown ?? ''} /></Card>
        </SiteLayout>
    );
}
