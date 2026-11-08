import SiteLayout from '@/layouts/site-layout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});
    return (
        <SiteLayout title="Vérifiez votre e-mail">
            <Head title="Vérification de l’e-mail" />
            <div className="max-w-md rounded-lg border bg-white p-5 text-sm">
                <p>Cliquez sur le lien que nous venons de vous envoyer par e-mail pour vérifier votre adresse.</p>
                {status === 'verification-link-sent' && <p className="mt-3 font-medium text-green-700">Un nouveau lien a été envoyé.</p>}
                <form onSubmit={(e) => { e.preventDefault(); post(route('verification.send')); }} className="mt-4 flex items-center gap-4">
                    <button disabled={processing} className="rounded-md bg-[#C8662B] px-4 py-2 text-white disabled:opacity-60">Renvoyer le lien</button>
                    <Link method="post" as="button" href={route('logout')} className="underline">Déconnexion</Link>
                </form>
            </div>
        </SiteLayout>
    );
}
