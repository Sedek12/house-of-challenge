import SiteLayout from '@/layouts/site-layout';
import { Head, Link } from '@inertiajs/react';

const TEXT: Record<number, [string, string]> = {
    403: ['Accès refusé', 'Vous n’avez pas l’autorisation d’ouvrir cette page.'],
    404: ['Page introuvable', 'Cette page n’existe pas ou n’est plus disponible.'],
    419: ['Session expirée', 'Rechargez la page puis réessayez.'],
    429: ['Trop de requêtes', 'Patientez un instant avant de réessayer.'],
    500: ['Erreur du serveur', 'Un problème est survenu de notre côté. Réessayez dans quelques minutes.'],
    503: ['Maintenance', 'Le site revient très vite.'],
};

export default function ErrorPage({ status }: { status: number }) {
    const [title, text] = TEXT[status] ?? ['Erreur', 'Une erreur est survenue.'];
    return (
        <SiteLayout>
            <Head title={title} />
            <div className="py-16 text-center">
                <div className="text-6xl font-extrabold text-[#B05A26]">{status}</div>
                <h1 className="mt-2 text-2xl font-bold">{title}</h1>
                <p className="mt-2 text-neutral-600">{text}</p>
                <Link href={route('home')} className="mt-6 inline-block rounded-md bg-[#B05A26] px-4 py-2 text-white">Retour à l’accueil</Link>
            </div>
        </SiteLayout>
    );
}
