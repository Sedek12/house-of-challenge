import { Card, Field, inputCls } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { Head, router, useForm } from '@inertiajs/react';

const LABELS: Record<string, string> = {
    notification_email: 'E-mail qui reçoit les nouvelles réservations (plusieurs adresses possibles, séparées par des virgules)', contact_whatsapp: 'WhatsApp de l’équipe (chiffres, avec indicatif, ex. 22890123456)', contact_email: 'E-mail de contact',
    tiktok_show_url: 'Compte TikTok de House of Challenge (adresse complète, ex. https://www.tiktok.com/@nom_du_compte)', studio_address: 'Adresse du studio', studio_maps_url: 'Lien Google Maps du studio',
    onsite_arrival_minutes: 'Arrivée au studio avant le passage (minutes)', hold_minutes: 'Durée du blocage d’un créneau pendant le paiement (minutes)',
    cancel_deadline_hours: 'Délai de report gratuit avant le live (heures)', edit_deadline_hours: 'Modification des produits possible jusqu’à (heures avant le live)',
    concept_markdown: 'Présentation du concept (page d’accueil)', faq_markdown: 'FAQ de la page d’accueil', legal_markdown: 'Mentions légales et confidentialité',
};

function ImageUpload({ kind, label, current }: { kind: 'logo' | 'hero'; label: string; current: string }) {
    return (
        <div className="grid gap-2">
            <span className="text-sm font-medium">{label}</span>
            {current && <img src={current} alt="" className="max-h-24 w-auto self-start rounded border bg-white p-1" />}
            <input type="file" accept="image/*" className="text-sm" onChange={(e) => { const image = e.target.files?.[0]; if (image) router.post(route('admin.settings.image', kind), { image }, { forceFormData: true, preserveScroll: true }); }} />
        </div>
    );
}

export default function Settings({ settings }: { settings: Record<string, string> }) {
    const { data, setData, post, processing, transform } = useForm({ settings });
    // Le logo et l'image d'accueil sont envoyés séparément : ne pas les réécrire avec une valeur périmée.
    transform((d) => ({ settings: Object.fromEntries(Object.entries(d.settings).filter(([k]) => k in LABELS || k === 'brand_color')) }));
    const set = (k: string, v: string) => setData('settings', { ...data.settings, [k]: v });
    return (
        <SiteLayout portal="admin" title="Paramètres">
            <Head title="Paramètres" />
            <Card className="mb-6 grid gap-4 md:grid-cols-3">
                <ImageUpload kind="logo" label="Logo (fond transparent conseillé)" current={settings.brand_logo_url} />
                <ImageUpload kind="hero" label="Image de la page d’accueil" current={settings.hero_image_url} />
                <Field label="Couleur principale (boutons)">
                    <div className="flex items-center gap-2">
                        <input type="color" aria-label="Couleur principale" value={/^#[0-9a-f]{6}$/i.test(data.settings.brand_color) ? data.settings.brand_color : '#fe7c02'} onChange={(e) => set('brand_color', e.target.value)} className="h-10 w-14 rounded border" />
                        <input className={inputCls} value={data.settings.brand_color ?? ''} onChange={(e) => set('brand_color', e.target.value)} />
                    </div>
                </Field>
            </Card>
            <Card>
                <form onSubmit={(e) => { e.preventDefault(); post(route('admin.settings.save')); }} className="grid gap-3">
                    {Object.keys(LABELS).map((k) => (
                        <Field key={k} label={LABELS[k]}>
                            {k.endsWith('_markdown')
                                ? <textarea rows={8} className={inputCls} value={data.settings[k] ?? ''} onChange={(e) => set(k, e.target.value)} />
                                : <input className={inputCls} value={data.settings[k] ?? ''} onChange={(e) => set(k, e.target.value)} />}
                        </Field>
                    ))}
                    <Button disabled={processing} className="justify-self-start">Enregistrer</Button>
                </form>
            </Card>
        </SiteLayout>
    );
}
