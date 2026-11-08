import { Card, Field, inputCls } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function Contact() {
    const { site } = usePage<{ site: { whatsapp: string } }>().props;
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', email: '', message: '' });
    return (
        <SiteLayout title="Contact">
            <Head title="Contact" />
            <Card className="max-w-xl">
                <form onSubmit={(e) => { e.preventDefault(); post(route('contact'), { onSuccess: () => reset() }); }} className="grid gap-3">
                    <Field label="Nom" error={errors.name}><input className={inputCls} value={data.name} onChange={(e) => setData('name', e.target.value)} required /></Field>
                    <Field label="E-mail" error={errors.email}><input type="email" className={inputCls} value={data.email} onChange={(e) => setData('email', e.target.value)} required /></Field>
                    <Field label="Message" error={errors.message}><textarea rows={5} className={inputCls} value={data.message} onChange={(e) => setData('message', e.target.value)} required /></Field>
                    <button disabled={processing} className="rounded-md bg-[#C8662B] px-4 py-2 text-white disabled:opacity-60">Envoyer</button>
                </form>
                <a className="mt-4 inline-block text-sm text-[#C8662B] underline" target="_blank" rel="noreferrer"
                    href={`https://wa.me/${site.whatsapp.replace(/\D/g, '')}?text=${encodeURIComponent('Bonjour, j’ai une question sur House of Challenge.')}`}>
                    Ou écrivez-nous sur WhatsApp
                </a>
            </Card>
        </SiteLayout>
    );
}
