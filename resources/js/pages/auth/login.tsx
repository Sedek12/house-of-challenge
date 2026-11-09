import { Field, inputCls } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }: { status?: string; canResetPassword: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ email: string; password: string; remember: boolean }>({ email: '', password: '', remember: false });
    return (
        <SiteLayout title="Connexion" centered>
            <Head title="Connexion" />
            {status && <div className="mb-4 text-sm text-green-700">{status}</div>}
            <form onSubmit={(e) => { e.preventDefault(); post(route('login'), { onFinish: () => reset('password') }); }} className="grid max-w-md gap-3 rounded-lg border bg-white p-5">
                <Field label="E-mail" error={errors.email}><input type="email" className={inputCls} value={data.email} onChange={(e) => setData('email', e.target.value)} required autoFocus autoComplete="email" /></Field>
                <Field label="Mot de passe" error={errors.password}><input type="password" className={inputCls} value={data.password} onChange={(e) => setData('password', e.target.value)} required autoComplete="current-password" /></Field>
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} /> Rester connecté</label>
                <button disabled={processing} className="rounded-md bg-primary px-4 py-2 text-white disabled:opacity-60">Se connecter</button>
                <div className="flex justify-between text-sm">
                    {canResetPassword && <Link className="text-[#B85600] underline" href={route('password.request')}>Mot de passe oublié ?</Link>}
                    <Link className="text-[#B85600] underline" href={route('account.register')}>Créer un compte vendeur</Link>
                </div>
            </form>
        </SiteLayout>
    );
}
