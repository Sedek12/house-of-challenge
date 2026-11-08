import { Field, inputCls } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        first_name: '', last_name: '', email: '', phone_e164: '+228', country: 'Togo', city: '', tiktok_username: '', password: '', password_confirmation: '',
    });
    const set = (k: keyof typeof data) => (e: React.ChangeEvent<HTMLInputElement>) => setData(k, e.target.value);
    return (
        <SiteLayout title="Créer mon compte">
            <Head title="Inscription" />
            <form onSubmit={(e) => { e.preventDefault(); post(route('register'), { onFinish: () => reset('password', 'password_confirmation') }); }}
                className="grid max-w-2xl gap-3 rounded-lg border bg-white p-5 md:grid-cols-2">
                <Field label="Prénom" error={errors.first_name}><input className={inputCls} value={data.first_name} onChange={set('first_name')} required autoComplete="given-name" /></Field>
                <Field label="Nom" error={errors.last_name}><input className={inputCls} value={data.last_name} onChange={set('last_name')} required autoComplete="family-name" /></Field>
                <Field label="E-mail" error={errors.email}><input type="email" className={inputCls} value={data.email} onChange={set('email')} required autoComplete="email" /></Field>
                <Field label="Téléphone WhatsApp (ex. +22890000000)" error={errors.phone_e164}><input type="tel" className={inputCls} value={data.phone_e164} onChange={set('phone_e164')} required autoComplete="tel" /></Field>
                <Field label="Pays" error={errors.country}><input className={inputCls} value={data.country} onChange={set('country')} required /></Field>
                <Field label="Ville" error={errors.city}><input className={inputCls} value={data.city} onChange={set('city')} /></Field>
                <Field label="Pseudo TikTok (obligatoire pour passer à distance)" error={errors.tiktok_username}><input className={inputCls} placeholder="@votre_pseudo" value={data.tiktok_username} onChange={set('tiktok_username')} /></Field>
                <div />
                <Field label="Mot de passe" error={errors.password}><input type="password" className={inputCls} value={data.password} onChange={set('password')} required autoComplete="new-password" /></Field>
                <Field label="Confirmer le mot de passe" error={errors.password_confirmation}><input type="password" className={inputCls} value={data.password_confirmation} onChange={set('password_confirmation')} required autoComplete="new-password" /></Field>
                <button disabled={processing} className="rounded-md bg-[#B05A26] px-4 py-2 text-white disabled:opacity-60 md:col-span-2">Créer mon compte</button>
                <p className="text-sm md:col-span-2">Déjà inscrit ? <Link className="text-[#B05A26] underline" href={route('login')}>Se connecter</Link></p>
            </form>
        </SiteLayout>
    );
}
