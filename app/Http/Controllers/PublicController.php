<?php

namespace App\Http\Controllers;

use App\Models\LivePost;
use App\Models\Package;
use App\Models\Slot;
use App\Services\Notifier;
use App\Services\Settings;
use Inertia\Inertia;

class PublicController extends Controller
{
    public function home()
    {
        return Inertia::render('public/home', [
            'packages' => $this->packages(),
            'faq' => Settings::get('faq_markdown'),
            'concept' => Settings::get('concept_markdown'),
            'hero_image' => Settings::get('hero_image_url'),
            'live_count' => LivePost::onAir()->count(),
        ]);
    }

    /** Page dédiée aux packages : détail de chaque package et choix. */
    public function packagesPage()
    {
        return Inertia::render('public/packages', ['packages' => $this->packages()]);
    }

    /** « À propos » : le concept OCD, Bovann et Bovann Group Togo (texte modifiable dans les paramètres). */
    public function about()
    {
        return Inertia::render('public/about', [
            'concept' => Settings::get('concept_markdown'),
            'hero_image' => Settings::get('hero_image_url'),
        ]);
    }

    /** « Nous contacter » : coordonnées de l'équipe (aucun formulaire : WhatsApp, e-mail, TikTok, studio). */
    public function contact()
    {
        $email = trim((string) Settings::get('contact_email'));

        return Inertia::render('public/contact', [
            'whatsapp' => Notifier::whatsappUrl(),
            'whatsapp_display' => ($d = preg_replace('/\D/', '', (string) Settings::get('contact_whatsapp'))) ? '+'.$d : null,
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'studio_address' => Settings::get('studio_address'),
            'maps_url' => Settings::get('studio_maps_url') ?: null,
        ]);
    }

    /** Packages actifs (F1). */
    public function packages()
    {
        return Package::where('is_active', true)->orderBy('display_order')->get();
    }

    /**
     * Créneaux des 30 prochains jours (F2). Un créneau pris (en attente de paiement ou confirmé) reste listé
     * avec `available = false` pour s'afficher « Occupé » ; un créneau fermé par l'admin n'est pas listé.
     * Aucune donnée personnelle.
     */
    public function openSlots(): array
    {
        return Slot::with('session:id,title,accepted_modes')
            ->whereIn('status', ['DISPONIBLE', 'EN_ATTENTE', 'RESERVE'])
            ->where('starts_at', '>', now())->where('starts_at', '<=', now()->addDays(30))
            ->whereHas('session', fn ($q) => $q->where('status', 'PROGRAMMEE'))
            ->orderBy('starts_at')->get()
            ->map(fn (Slot $s) => [
                'id' => $s->id, 'starts_at' => $s->starts_at->toIso8601String(), 'ends_at' => $s->ends_at->toIso8601String(),
                'session_title' => $s->session->title, 'modes' => $s->session->accepted_modes,
                'available' => $s->status === 'DISPONIBLE' || ($s->status === 'EN_ATTENTE' && $s->hold_expires_at?->lt(now())),
            ])->all();
    }

    public function legal()
    {
        return Inertia::render('public/legal', ['markdown' => Settings::get('legal_markdown')]);
    }
}
