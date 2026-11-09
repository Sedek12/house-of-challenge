import { CountrySelect, PhoneInput } from '@/components/country-fields';
import { Card, Field, inputCls } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { buildPhone, Country } from '@/lib/countries';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Register({ countries, home_country }: { countries: Country[]; home_country: string }) {
    const [phoneCode, setPhoneCode] = useState(home_country);
    const [national, setNational] = useState('');
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', email: '', password: '', password_confirmation: '', country: home_country, phone: '' });
    const err = errors as Record<string, string>;
    const dial = (code: string) => countries.find((c) => c.code === code)?.dial ?? '';

    return (
        <SiteLayout title="Créer mon compte" centered>
            <Head title="Créer mon compte" />
            <Card>
                <p className="mb-4 text-sm text-neutral-600">Avec un compte, vos informations sont préremplies à chaque réservation et vous retrouvez toutes vos réservations au même endroit.</p>
                <form onSubmit={(e) => { e.preventDefault(); post(route('account.store'), { onFinish: () => reset('password', 'password_confirmation') }); }} className="grid gap-3">
                    <Field label="Nom complet" error={err.name}><input className={inputCls} autoComplete="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required /></Field>
                    <Field label="E-mail" error={err.email}><input type="email" className={inputCls} autoComplete="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required /></Field>
                    <Field label="Votre pays" error={err.country}>
                        <CountrySelect value={data.country} countries={countries} onChange={(code) => {
                            setData('country', code);
                            if (!national) setPhoneCode(code);
                        }} />
                    </Field>
                    <PhoneInput label="Téléphone WhatsApp" countries={countries} code={phoneCode} national={national} error={err.phone}
                        onChange={(code, n) => { setPhoneCode(code); setNational(n); setData('phone', buildPhone(dial(code), n)); }} />
                    <Field label="Mot de passe (8 caractères minimum)" error={err.password}><input type="password" className={inputCls} autoComplete="new-password" value={data.password} onChange={(e) => setData('password', e.target.value)} required /></Field>
                    <Field label="Confirmer le mot de passe"><input type="password" className={inputCls} autoComplete="new-password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} required /></Field>
                    <Button disabled={processing}>Créer mon compte</Button>
                    <p className="text-sm">Déjà un compte ? <Link className="text-[#B85600] underline" href={route('login')}>Se connecter</Link></p>
                </form>
            </Card>
        </SiteLayout>
    );
}
