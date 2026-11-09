import { Field, inputCls } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status, dev_link }: { status?: string; dev_link?: string | null }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });
    return (
        <SiteLayout title="Mot de passe oublié" centered>
            <Head title="Mot de passe oublié" />
            {status && <div role="status" className="mb-4 text-sm text-green-700">{status}</div>}
            {dev_link && (
                <div className="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm">
                    <b>Mode développement</b> : aucun e-mail n’est envoyé (réglage « log »). Utilisez directement ce lien :
                    <a className="mt-1 block break-all text-[#B85600] underline" href={dev_link}>{dev_link}</a>
                </div>
            )}
            <form onSubmit={(e) => { e.preventDefault(); post(route('password.email')); }} className="grid max-w-md gap-3 rounded-lg border bg-white p-5">
                <p className="text-sm text-neutral-600">Saisissez votre e-mail : nous vous enverrons un lien de réinitialisation (valable 1 heure).</p>
                <Field label="E-mail" error={errors.email}><input type="email" className={inputCls} value={data.email} onChange={(e) => setData('email', e.target.value)} required autoFocus /></Field>
                <button disabled={processing} className="rounded-md bg-primary px-4 py-2 text-white disabled:opacity-60">Envoyer le lien</button>
                <Link className="text-sm text-[#B85600] underline" href={route('login')}>Retour à la connexion</Link>
            </form>
        </SiteLayout>
    );
}
