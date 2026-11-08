import { Field, inputCls } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { Head, useForm } from '@inertiajs/react';

type Form = { token: string; email: string; password: string; password_confirmation: string };

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { data, setData, post, processing, errors, reset } = useForm<Form>({ token, email, password: '', password_confirmation: '' });
    return (
        <SiteLayout title="Nouveau mot de passe">
            <Head title="Nouveau mot de passe" />
            <form onSubmit={(e) => { e.preventDefault(); post(route('password.store'), { onFinish: () => reset('password', 'password_confirmation') }); }} className="grid max-w-md gap-3 rounded-lg border bg-white p-5">
                <Field label="E-mail" error={errors.email}><input type="email" className={inputCls} value={data.email} readOnly /></Field>
                <Field label="Nouveau mot de passe" error={errors.password}><input type="password" className={inputCls} value={data.password} onChange={(e) => setData('password', e.target.value)} required autoFocus autoComplete="new-password" /></Field>
                <Field label="Confirmer" error={errors.password_confirmation}><input type="password" className={inputCls} value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} required autoComplete="new-password" /></Field>
                <button disabled={processing} className="rounded-md bg-[#B05A26] px-4 py-2 text-white disabled:opacity-60">Réinitialiser</button>
            </form>
        </SiteLayout>
    );
}
