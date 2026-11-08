import { Card, Field, inputCls } from '@/components/hoc';
import SiteLayout from '@/layouts/site-layout';
import { Head, useForm } from '@inertiajs/react';

const LABELS: Record<string, string> = {
    studio_address: 'Adresse du studio', studio_maps_url: 'Lien Google Maps', contact_whatsapp: 'WhatsApp de contact (chiffres, avec indicatif)',
    tiktok_show_url: 'Lien TikTok de l’émission', cancel_deadline_hours: 'Délai d’annulation / report (heures)', hold_minutes: 'Durée du hold (minutes)',
    max_future_bookings: 'Réservations futures max par participant', onsite_arrival_minutes: 'Arrivée sur place avant (minutes)',
    faq_markdown: 'FAQ', ocd_page_markdown: 'Page À propos d’OCD', cgu_markdown: 'Conditions générales', privacy_markdown: 'Confidentialité',
};

export default function Settings({ settings }: { settings: Record<string, string> }) {
    const { data, setData, post, processing } = useForm({ settings });
    return (
        <SiteLayout portal="admin" title="Paramètres">
            <Head title="Paramètres" />
            <Card>
                <form onSubmit={(e) => { e.preventDefault(); post(route('admin.settings.save')); }} className="grid gap-3">
                    {Object.keys(LABELS).map((k) => (
                        <Field key={k} label={LABELS[k]}>
                            {k.endsWith('_markdown')
                                ? <textarea rows={6} className={inputCls} value={data.settings[k] ?? ''} onChange={(e) => setData('settings', { ...data.settings, [k]: e.target.value })} />
                                : <input className={inputCls} value={data.settings[k] ?? ''} onChange={(e) => setData('settings', { ...data.settings, [k]: e.target.value })} />}
                        </Field>
                    ))}
                    <button disabled={processing} className="rounded-md bg-[#B05A26] px-4 py-2 text-white">Enregistrer</button>
                </form>
            </Card>
        </SiteLayout>
    );
}
