import { Button } from '@/components/ui/button';
import { Link, router, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { PropsWithChildren, ReactNode, useState } from 'react';

type Shared = {
    auth: { user: null | { first_name: string; role: string; credits: number } };
    site: { whatsapp: string; tiktok: string };
    flash: { success?: string };
    errors: Record<string, string>;
};

const publicNav = [
    ['Formules', 'packages'], ['Calendrier', 'calendar'], ['Projets', 'projects'], ['OCD', 'page', { key: 'ocd' }], ['FAQ', 'page', { key: 'faq' }],
] as const;

const portals: Record<string, [string, string][]> = {
    space: [['Mon espace', 'space.home'], ['Réservations', 'space.bookings'], ['Projets', 'space.projects'], ['Achats', 'space.purchases'], ['Publications', 'space.publications'], ['Profil', 'profile.edit']],
    team: [['Planning', 'team.planning']],
    admin: [['Tableau de bord', 'admin.dashboard'], ['Formules', 'admin.packages'], ['Sessions', 'admin.sessions'], ['Réservations', 'admin.bookings'], ['Paiements', 'admin.payments'], ['Utilisateurs', 'admin.users'], ['Publications', 'admin.publications'], ['Paramètres', 'admin.settings'], ['Audit', 'admin.audit']],
};

export default function SiteLayout({ children, portal, title }: PropsWithChildren<{ portal?: 'space' | 'team' | 'admin'; title?: ReactNode }>) {
    const { auth, site, flash, errors } = usePage<Shared>().props;
    const [open, setOpen] = useState(false);
    const user = auth.user;
    const wa = `https://wa.me/${(site.whatsapp || '').replace(/\D/g, '')}?text=${encodeURIComponent('Bonjour, je souhaite des informations sur House of Challenge.')}`;
    const myPortal = user?.role === 'ADMIN' ? 'admin.dashboard' : user?.role === 'INTERVIEWER' ? 'team.planning' : 'space.home';

    return (
        <div className="flex min-h-screen flex-col bg-neutral-50 text-neutral-900">
            <header className="sticky top-0 z-40 bg-[#1F1F1F] text-white print:hidden">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                    <Link href={route('home')} className="text-lg font-bold">
                        House of <span className="text-[#C8662B]">Challenge</span>
                    </Link>
                    <nav className="hidden items-center gap-5 text-sm md:flex">
                        {publicNav.map(([label, name, params]) => (
                            <Link key={label} href={route(name, params as never)} className="hover:text-[#C8662B]">{label}</Link>
                        ))}
                        <Button asChild size="sm"><Link href={route('reserve')}>Réserver mon passage</Link></Button>
                        {user ? (
                            <>
                                <Link href={route(myPortal)} className="hover:text-[#C8662B]">{user.role === 'PARTICIPANT' ? 'Mon espace' : 'Back-office'}</Link>
                                <button onClick={() => router.post(route('logout'))} className="text-neutral-300 hover:text-white">Déconnexion</button>
                            </>
                        ) : (
                            <Link href={route('login')} className="hover:text-[#C8662B]">Connexion</Link>
                        )}
                    </nav>
                    <button className="md:hidden" aria-label="Menu" onClick={() => setOpen(!open)}>{open ? <X /> : <Menu />}</button>
                </div>
                {open && (
                    <nav className="flex flex-col gap-3 border-t border-white/10 px-4 py-4 text-sm md:hidden">
                        {publicNav.map(([label, name, params]) => (
                            <Link key={label} href={route(name, params as never)} onClick={() => setOpen(false)}>{label}</Link>
                        ))}
                        <Link href={route('reserve')} className="font-semibold text-[#C8662B]">Réserver mon passage</Link>
                        {user ? (
                            <>
                                <Link href={route(myPortal)}>{user.role === 'PARTICIPANT' ? 'Mon espace' : 'Back-office'}</Link>
                                <button className="text-left" onClick={() => router.post(route('logout'))}>Déconnexion</button>
                            </>
                        ) : (
                            <Link href={route('login')}>Connexion</Link>
                        )}
                    </nav>
                )}
            </header>

            {portal && (
                <div className="overflow-x-auto border-b bg-white print:hidden">
                    <div className="mx-auto flex max-w-6xl gap-1 px-4 text-sm">
                        {portals[portal].map(([label, name]) => (
                            <Link key={name} href={route(name)} className={`whitespace-nowrap px-3 py-3 ${route().current(name + '*') ? 'border-b-2 border-[#C8662B] font-semibold' : 'text-neutral-600 hover:text-black'}`}>{label}</Link>
                        ))}
                        {portal === 'admin' && (
                            <Link href={route('team.planning')} className="whitespace-nowrap px-3 py-3 text-neutral-600">Espace équipe</Link>
                        )}
                    </div>
                </div>
            )}

            <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6">
                {flash?.success && <div role="status" className="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800">{flash.success}</div>}
                {errors?.error && <div role="alert" className="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">{errors.error}</div>}
                {title && <h1 className="mb-5 text-2xl font-bold">{title}</h1>}
                {children}
            </main>

            <footer className="bg-[#1F1F1F] px-4 py-8 text-sm text-neutral-300 print:hidden">
                <div className="mx-auto flex max-w-6xl flex-col gap-4 md:flex-row md:justify-between">
                    <div>
                        <div className="font-bold text-white">House of Challenge</div>
                        <div>Émission en live TikTok — Studio OCD Live, Togo</div>
                    </div>
                    <div className="flex flex-wrap gap-4">
                        <a href={wa} className="hover:text-white" target="_blank" rel="noreferrer">WhatsApp</a>
                        <a href={site.tiktok} className="hover:text-white" target="_blank" rel="noreferrer">TikTok</a>
                        <Link href={route('contact')}>Contact</Link>
                        <Link href={route('page', { key: 'cgu' })}>CGU</Link>
                        <Link href={route('page', { key: 'confidentialite' })}>Confidentialité</Link>
                    </div>
                </div>
            </footer>
        </div>
    );
}
