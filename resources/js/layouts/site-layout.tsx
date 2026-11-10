import { Button } from '@/components/ui/button';
import DashboardLayout from '@/layouts/dashboard-layout';
import { tiktokHandle } from '@/lib/format';
import { DEVELOPER, readableOn, Shared } from '@/lib/layout';
import { Link, router, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { CSSProperties, PropsWithChildren, ReactNode, useState } from 'react';

/** Onglets publics de l'en-tête. */
const publicNav: [string, string][] = [['Accueil', 'home'], ['Lives en direct', 'lives'], ['Packages', 'packages'], ['À propos', 'about'], ['Nous contacter', 'contact']];

export default function SiteLayout({ children, portal, title, centered }: PropsWithChildren<{ portal?: 'team' | 'admin'; title?: ReactNode; centered?: boolean }>) {
    const { auth, site, flash, errors } = usePage<Shared>().props;
    const user = auth.user;
    const [menuOpen, setMenuOpen] = useState(false);

    // Espace équipe (administrateur, animateur) : tableau de bord avec barre latérale.
    if (portal) {
        return <DashboardLayout portal={portal} title={title}>{children}</DashboardLayout>;
    }

    const isStaff = user?.role === 'ADMIN' || user?.role === 'INTERVIEWER';
    const wa = `https://wa.me/${(site.whatsapp || '').replace(/\D/g, '')}?text=${encodeURIComponent('Bonjour, je souhaite des informations sur OCD Live.')}`;
    const home = user?.role === 'ADMIN' ? 'admin.planning' : user?.role === 'VENDOR' ? 'account.home' : 'team.planning';
    const homeLabel = user?.role === 'VENDOR' ? 'Mon compte' : 'Tableau de bord';
    const brand = { '--primary': site.brand_color, '--primary-foreground': readableOn(site.brand_color) } as CSSProperties;
    const handle = tiktokHandle(site.tiktok);
    const linkCls = 'text-neutral-300 hover:text-white';

    return (
        <div style={brand} className="flex min-h-screen flex-col bg-neutral-50 text-neutral-900">
            <header className="sticky top-0 z-40 border-b bg-white print:hidden">
                <div className="mx-auto flex max-w-6xl items-center gap-3 px-4 py-2">
                    <Link href={route('home')} aria-label="OCD Live — accueil" className="shrink-0">
                        <img src={site.logo} alt="OCD Live" width={722} height={349} className="h-12 w-auto sm:h-14" />
                    </Link>

                    {!isStaff && (
                        <nav aria-label="Navigation principale" className="ml-6 hidden items-center gap-6 text-sm font-medium md:flex">
                            {publicNav.map(([label, name]) => (
                                <Link key={name} href={route(name)} aria-current={route().current(name) ? 'page' : undefined} className={route().current(name) ? 'text-[#B85600]' : 'hover:text-[#B85600]'}>{label}</Link>
                            ))}
                        </nav>
                    )}

                    <div className="ml-auto flex items-center gap-3 text-sm">
                        {/* Compte : en ligne sur ordinateur ; dans le menu sur téléphone. */}
                        <div className={`${isStaff ? 'flex' : 'hidden md:flex'} items-center gap-4`}>
                            {user ? (
                                <>
                                    <Link href={route(home)} className="hover:text-[#B85600]">{homeLabel}</Link>
                                    <button onClick={() => router.post(route('logout'))} className="text-neutral-500 hover:text-black">Déconnexion</button>
                                </>
                            ) : (
                                <Link href={route('login')} className="hover:text-[#B85600]">Connexion</Link>
                            )}
                        </div>
                        {!isStaff && <Button asChild size="sm"><Link href={route('reserve')}>Réserver</Link></Button>}
                        {!isStaff && (
                            <button type="button" className="rounded p-1 md:hidden" aria-label="Menu" aria-expanded={menuOpen} aria-controls="menu-mobile" onClick={() => setMenuOpen(!menuOpen)}>
                                {menuOpen ? <X /> : <Menu />}
                            </button>
                        )}
                    </div>
                </div>

                {menuOpen && !isStaff && (
                    <nav id="menu-mobile" aria-label="Menu mobile" className="flex flex-col gap-1 border-t px-4 py-3 text-base md:hidden">
                        {publicNav.map(([label, name]) => (
                            <Link key={name} href={route(name)} className={`rounded px-2 py-2 ${route().current(name) ? 'bg-orange-50 font-semibold text-[#B85600]' : ''}`}>{label}</Link>
                        ))}
                        <div className="my-1 border-t" />
                        {user ? (
                            <>
                                <Link href={route(home)} className="rounded px-2 py-2">{homeLabel}</Link>
                                <button className="rounded px-2 py-2 text-left text-neutral-600" onClick={() => router.post(route('logout'))}>Déconnexion</button>
                            </>
                        ) : (
                            <Link href={route('login')} className="rounded px-2 py-2">Connexion</Link>
                        )}
                    </nav>
                )}
            </header>

            <main className={`mx-auto w-full flex-1 px-4 py-6 ${centered ? 'max-w-md pt-8 sm:pt-14' : 'max-w-6xl'}`}>
                {flash?.success && <div role="status" className="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800">{flash.success}</div>}
                {errors?.error && <div role="alert" className="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">{errors.error}</div>}
                {title && <h1 className={`mb-5 text-2xl font-bold ${centered ? 'text-center' : ''}`}>{title}</h1>}
                {children}
            </main>

            <footer className="mt-10 print:hidden">
                <div className="bg-[#1F1F1F] px-4 py-12 text-sm text-neutral-300">
                    <div className="mx-auto grid max-w-6xl gap-10 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="sm:col-span-2 lg:col-span-1">
                            <div className="text-2xl font-extrabold text-white">OCD <span className="text-[#FE7C02]">Live</span></div>
                            <p className="mt-1 font-semibold text-white">Les Opportunités, C’est Dehors</p>
                            <p className="mt-3 leading-relaxed">Un concept de Bovann, porté par Bovann Group Togo. Les lives ont lieu sur le TikTok de House of Challenge.</p>
                            <div className="mt-5 flex flex-wrap gap-2">
                                <Button asChild size="sm"><a href={site.tiktok} target="_blank" rel="noreferrer">TikTok {handle}</a></Button>
                                <Button asChild size="sm" variant="secondary"><a href={wa} target="_blank" rel="noreferrer">WhatsApp</a></Button>
                            </div>
                        </div>

                        <nav aria-label="Navigation du pied de page">
                            <h2 className="mb-4 text-base font-semibold text-white">Navigation</h2>
                            <ul className="grid gap-2.5">
                                <li><Link className={linkCls} href={route('home')}>Accueil</Link></li>
                                <li><Link className={linkCls} href={route('lives')}>Lives en direct</Link></li>
                                <li><Link className={linkCls} href={route('live.mine')}>Mettre mon live en avant</Link></li>
                                <li><Link className={linkCls} href={route('about')}>À propos</Link></li>
                                <li><Link className={linkCls} href={route('packages')}>Packages</Link></li>
                                <li><Link className={linkCls} href={route('reserve')}>Réserver mon passage</Link></li>
                                <li><Link className={linkCls} href={route('contact')}>Nous contacter</Link></li>
                            </ul>
                        </nav>

                        <div>
                            <h2 className="mb-4 text-base font-semibold text-white">Contact</h2>
                            <ul className="grid gap-2.5">
                                <li><a className={linkCls} href={wa} target="_blank" rel="noreferrer">WhatsApp : écrire à l’équipe</a></li>
                                <li><a className={linkCls} href={site.tiktok} target="_blank" rel="noreferrer">TikTok {handle}</a></li>
                                {site.email && <li><a className={linkCls} href={`mailto:${site.email}`}>{site.email}</a></li>}
                                {site.studio && <li className="text-neutral-400">{site.studio}</li>}
                            </ul>
                        </div>

                        <div>
                            <h2 className="mb-4 text-base font-semibold text-white">Informations</h2>
                            <ul className="grid gap-2.5">
                                <li><Link className={linkCls} href={route('legal')}>Mentions légales et confidentialité</Link></li>
                                <li>{user ? <Link className={linkCls} href={route(home)}>{homeLabel}</Link> : <Link className={linkCls} href={route('login')}>Connexion</Link>}</li>
                                {!user && <li><Link className={linkCls} href={route('account.register')}>Créer un compte vendeur</Link></li>}
                            </ul>
                        </div>
                    </div>
                </div>

                <div className="bg-[#141414] px-4 py-5 text-xs text-neutral-400">
                    <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 text-center sm:flex-row sm:text-left">
                        <span>© {new Date().getFullYear()} OCD Live — House of Challenge. Tous droits réservés.</span>
                        <span>Développé par <strong className="text-white">{DEVELOPER}</strong></span>
                    </div>
                </div>
            </footer>
        </div>
    );
}
