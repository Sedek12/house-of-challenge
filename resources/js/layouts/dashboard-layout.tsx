import { DEVELOPER, ROLE_LABEL, readableOn, Shared } from '@/lib/layout';
import { Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, ClipboardList, Clock, CreditCard, ExternalLink, LogOut, LucideIcon, Menu, Package, Settings, X } from 'lucide-react';
import { CSSProperties, PropsWithChildren, ReactNode, useState } from 'react';

type Item = { label: string; name: string; icon: LucideIcon; also?: string[] };

const NAV: Record<'admin' | 'team', Item[]> = {
    team: [{ label: 'Planning', name: 'team.planning', icon: CalendarDays, also: ['team.booking'] }],
    admin: [
        { label: 'Planning', name: 'admin.planning', icon: CalendarDays, also: ['team.booking'] },
        { label: 'Réservations', name: 'admin.bookings', icon: ClipboardList },
        { label: 'Créneaux', name: 'admin.sessions', icon: Clock },
        { label: 'Packages', name: 'admin.packages', icon: Package },
        { label: 'Paiements', name: 'admin.payments', icon: CreditCard },
        { label: 'Paramètres', name: 'admin.settings', icon: Settings },
    ],
};

const isActive = (item: Item) => [item.name, ...(item.also ?? [])].some((n) => route().current(n + '*'));

/**
 * Tableau de bord : barre latérale (boutons de direction), en-tête, contenu, et la mention de l'éditeur en bas.
 * Sur téléphone, la barre latérale s'ouvre par le bouton du menu.
 */
export default function DashboardLayout({ portal, title, children }: PropsWithChildren<{ portal: 'admin' | 'team'; title?: ReactNode }>) {
    const { auth, site, flash, errors } = usePage<Shared>().props;
    const [open, setOpen] = useState(false);
    const user = auth.user;
    const brand = { '--primary': site.brand_color, '--primary-foreground': readableOn(site.brand_color) } as CSSProperties;
    const logout = () => router.post(route('logout'));

    return (
        <div style={brand} className="min-h-screen bg-neutral-100 text-neutral-900 lg:flex">
            {open && <div className="fixed inset-0 z-40 bg-black/40 lg:hidden" aria-hidden onClick={() => setOpen(false)} />}

            <aside id="sidebar" aria-label="Menu du tableau de bord"
                className={`fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 flex-col border-r bg-white transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 print:hidden ${open ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <Link href={route('home')} aria-label="OCD Live — voir le site"><img src={site.logo} alt="OCD Live" width={722} height={349} className="h-12 w-auto" /></Link>
                    <button type="button" className="rounded p-1 lg:hidden" aria-label="Fermer le menu" onClick={() => setOpen(false)}><X /></button>
                </div>

                <nav aria-label="Navigation du tableau de bord" className="flex-1 space-y-1 overflow-y-auto p-3">
                    {NAV[portal].map((item) => {
                        const active = isActive(item);
                        const Icon = item.icon;
                        return (
                            <Link key={item.name} href={route(item.name)} aria-current={active ? 'page' : undefined} onClick={() => setOpen(false)}
                                className={`flex items-center gap-3 rounded-md border-l-4 px-3 py-2.5 text-sm ${active ? 'border-primary bg-orange-50 font-semibold text-[#B85600]' : 'border-transparent text-neutral-700 hover:bg-neutral-100'}`}>
                                <Icon className="size-5 shrink-0" aria-hidden />
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                <div className="space-y-1 border-t p-3 text-sm">
                    <div className="px-3 pb-2">
                        <div className="truncate font-semibold">{user?.name}</div>
                        <div className="text-xs text-neutral-500">{ROLE_LABEL[user?.role ?? ''] ?? ''}</div>
                    </div>
                    <Link href={route('home')} className="flex items-center gap-3 rounded-md px-3 py-2 text-neutral-700 hover:bg-neutral-100"><ExternalLink className="size-4" aria-hidden />Voir le site</Link>
                    <button type="button" onClick={logout} className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-neutral-700 hover:bg-neutral-100"><LogOut className="size-4" aria-hidden />Déconnexion</button>
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-30 flex items-center gap-3 border-b bg-white px-4 py-3 lg:px-8 print:hidden">
                    <button type="button" className="rounded p-1 lg:hidden" aria-label="Ouvrir le menu" aria-expanded={open} aria-controls="sidebar" onClick={() => setOpen(true)}><Menu /></button>
                    <h1 className="truncate text-lg font-bold sm:text-xl">{title}</h1>
                    <div className="ml-auto hidden items-center gap-3 text-sm sm:flex">
                        <span className="text-neutral-600">{user?.name}</span>
                        <span className="rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-semibold text-[#B85600]">{ROLE_LABEL[user?.role ?? ''] ?? ''}</span>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6 lg:px-8">
                    {flash?.success && <div role="status" className="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800">{flash.success}</div>}
                    {errors?.error && <div role="alert" className="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">{errors.error}</div>}
                    {children}
                </main>

                <footer className="border-t bg-white px-4 py-3 text-center text-xs text-neutral-500 print:hidden">
                    Développé par <strong className="text-neutral-700">{DEVELOPER}</strong>
                </footer>
            </div>
        </div>
    );
}
