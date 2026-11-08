import { Card, Field, inputCls } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Profile = { first_name: string; last_name: string; email: string; phone_e164: string; country: string; city: string | null; tiktok_username: string | null };

export default function ProfilePage({ profile }: { profile: Profile }) {
    const { data, setData, patch, processing, errors } = useForm({ ...profile, city: profile.city ?? '', tiktok_username: profile.tiktok_username ?? '' });
    const [pwd, setPwd] = useState('');
    const [delErr, setDelErr] = useState('');
    const set = (k: keyof typeof data) => (e: React.ChangeEvent<HTMLInputElement>) => setData(k, e.target.value);

    return (
        <SiteLayout portal="space" title="Mon profil">
            <Head title="Profil" />
            <form onSubmit={(e) => { e.preventDefault(); patch(route('profile.update')); }} className="grid max-w-2xl gap-3 rounded-lg border bg-white p-5 md:grid-cols-2">
                <Field label="Prénom" error={errors.first_name}><input className={inputCls} value={data.first_name} onChange={set('first_name')} required /></Field>
                <Field label="Nom" error={errors.last_name}><input className={inputCls} value={data.last_name} onChange={set('last_name')} required /></Field>
                <Field label="E-mail" error={errors.email}><input type="email" className={inputCls} value={data.email} onChange={set('email')} required /></Field>
                <Field label="Téléphone WhatsApp (+22890000000)" error={errors.phone_e164}><input className={inputCls} value={data.phone_e164} onChange={set('phone_e164')} required /></Field>
                <Field label="Pays" error={errors.country}><input className={inputCls} value={data.country} onChange={set('country')} required /></Field>
                <Field label="Ville" error={errors.city}><input className={inputCls} value={data.city} onChange={set('city')} /></Field>
                <Field label="Pseudo TikTok" error={errors.tiktok_username}><input className={inputCls} value={data.tiktok_username} onChange={set('tiktok_username')} /></Field>
                <div />
                <button disabled={processing} className="rounded-md bg-[#B05A26] px-4 py-2 text-white md:col-span-2">Enregistrer</button>
            </form>

            <Card className="mt-6 max-w-2xl border-red-200">
                <h2 className="font-bold text-red-700">Supprimer mon compte</h2>
                <p className="text-sm text-neutral-600">Vos données personnelles sont anonymisées ; l’historique des réservations et paiements est conservé sans lien avec votre identité.</p>
                <div className="mt-3 flex gap-2">
                    <input type="password" aria-label="Mot de passe" placeholder="Mot de passe" className={inputCls} value={pwd} onChange={(e) => setPwd(e.target.value)} />
                    <button className="rounded-md bg-red-600 px-3 text-sm text-white" onClick={() => confirm('Supprimer définitivement mon compte ?') && router.delete(route('profile.destroy'), { data: { password: pwd }, onError: (e) => setDelErr(e.password ?? '') })}>Supprimer</button>
                </div>
                {delErr && <p className="mt-1 text-sm text-red-600">{delErr}</p>}
            </Card>
        </SiteLayout>
    );
}
