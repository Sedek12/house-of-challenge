<?php

namespace App\Services;

use App\Mail\GenericMail;
use App\Models\Booking;
use App\Models\LivePost;
use App\Models\LiveSubscription;
use App\Models\User;
use App\Support\Countries;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Envoi des e-mails transactionnels. Un échec d'envoi n'interrompt jamais le flux métier. */
class Notifier
{
    public static function format(\DateTimeInterface $d, ?string $tz = null): string
    {
        $c = Carbon::instance($d)->setTimezone($tz ?? config('app.display_timezone'))->locale('fr');

        return $c->isoFormat('dddd D MMMM YYYY [à] HH [h] mm');
    }

    public static function trackingUrl(Booking $b): string
    {
        return route('track', ['token' => $b->tracking_token]);
    }

    public static function whatsappUrl(?Booking $b = null): ?string
    {
        $wa = preg_replace('/\D/', '', (string) Settings::get('contact_whatsapp'));
        if (! $wa) {
            return null;
        }
        $text = $b ? "Bonjour, je vous contacte au sujet de ma réservation {$b->reference}." : 'Bonjour, je souhaite des informations sur OCD Live.';

        return 'https://wa.me/'.$wa.'?text='.rawurlencode($text);
    }

    public static function modeInstructions(Booking $b): array
    {
        if ($b->mode === 'SUR_PLACE') {
            $lines = [
                'Mode : sur place, au studio OCD Live.',
                'Adresse : '.Settings::get('studio_address'),
                'Arrivez '.Settings::get('onsite_arrival_minutes').' minutes avant l\'heure de passage.',
            ];
            if ($maps = Settings::get('studio_maps_url')) {
                $lines[] = 'Plan : '.$maps;
            }
        } else {
            $lines = [
                'Mode : à distance, via TikTok.',
                'Soyez connecté sur TikTok'.($b->tiktok ? ' avec le compte @'.ltrim($b->tiktok, '@') : '').' 15 minutes avant ; vous recevrez une invitation à rejoindre le live.',
                'Le live a lieu sur le compte TikTok de House of Challenge : '.Settings::get('tiktok_show_url'),
                'Prévoyez une bonne connexion internet et un bon éclairage.',
            ];
        }

        return $lines;
    }

    public static function send(?string $to, string $subject, array $lines, ?array $attachment = null, ?array $action = null): void
    {
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send(new GenericMail($subject, $lines, $attachment, $action));
        } catch (\Throwable $e) {
            Log::error('Échec envoi e-mail : '.$e->getMessage(), ['to' => $to, 'subject' => $subject]);
        }
    }

    /**
     * Destinataires des alertes « nouvelle réservation » : l'adresse réglée dans Admin → Paramètres
     * (plusieurs adresses possibles, séparées par des virgules) ou, à défaut, les administrateurs.
     *
     * @return array<int, string>
     */
    public static function teamRecipients(): array
    {
        $configured = array_filter(array_map('trim', explode(',', (string) Settings::get('notification_email'))), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));

        return $configured ?: User::where('role', 'ADMIN')->where('is_active', true)->pluck('email')->all();
    }

    /** E-mail de bienvenue après la création d'un compte vendeur : confirmation et étapes suivantes. */
    public static function accountCreated(User $u): void
    {
        $lines = [
            'Bonjour '.$u->name.',',
            'Votre compte vendeur OCD Live est bien créé. Bienvenue chez House of Challenge !',
            'Voici la suite :',
            '1. Choisissez le package qui vous convient.',
            '2. Réservez le créneau de votre passage en direct sur TikTok.',
            '3. Renseignez votre boutique et vos produits (ils seront mémorisés pour vos prochaines réservations).',
            '4. Payez en ligne : vous recevez aussitôt la confirmation, le lien de suivi et l\'ajout à votre agenda.',
            'Votre adresse de connexion : '.$u->email.' (mot de passe oublié ? '.route('password.request').')',
        ];
        if ($wa = self::whatsappUrl()) {
            $lines[] = 'Une question ? WhatsApp : '.$wa;
        }
        self::send($u->email, 'Bienvenue sur OCD Live — votre compte est créé', $lines, null, ['label' => 'Réserver mon passage', 'url' => route('reserve')]);
    }

    /* ---------- Annuaire des lives ---------- */

    public static function liveSubscriptionActivated(LiveSubscription $s): void
    {
        $s->loadMissing('user');
        self::send($s->user->email, 'Abonnement Lives activé — OCD Live', [
            'Bonjour '.$s->user->name.',',
            'Votre abonnement est actif jusqu\'au '.self::format($s->ends_at).' (heure de Lomé).',
            'Il comprend '.$s->quota.' lives, au plus '.Settings::int('live_per_day').' par jour. Montant payé : '.number_format($s->amount, 0, ',', ' ').' FCFA (référence '.$s->reference.').',
            'Comment faire : lancez votre live sur TikTok, puis envoyez-nous son lien pendant nos permanences ('.LiveHub::windowsLabel().', heure de Lomé). Après vérification, votre live apparaît sur OCD Live.',
        ], null, ['label' => 'Envoyer mon live', 'url' => route('live.mine')]);
    }

    public static function liveApproved(LivePost $p): void
    {
        $p->loadMissing('user', 'subscription');
        self::send($p->user->email, 'Votre live est en ligne sur OCD Live', [
            'Bonjour '.$p->user->name.',',
            'Votre live « '.$p->seller_name.' » est affiché sur OCD Live jusqu\'à '.self::format($p->live_until).'.',
            'Encore en live à ce moment-là ? Vous pouvez prolonger une fois depuis votre page « Mon live ».',
            'Lives restants sur votre abonnement : '.$p->subscription->remaining().' sur '.$p->subscription->quota.'.',
        ], null, ['label' => 'Voir mon live', 'url' => route('live.mine')]);
    }

    public static function liveRejected(LivePost $p): void
    {
        $p->loadMissing('user');
        $lines = [
            'Bonjour '.$p->user->name.',',
            'Votre live n\'a pas pu être mis en avant. Motif : '.$p->reject_reason,
            'Ce live n\'est pas décompté de votre abonnement. Corrigez puis renvoyez votre lien pendant nos permanences ('.LiveHub::windowsLabel().').',
        ];
        if ($wa = self::whatsappUrl()) {
            $lines[] = 'Besoin d\'aide ? WhatsApp : '.$wa;
        }
        self::send($p->user->email, 'Votre live n\'a pas été validé — OCD Live', $lines, null, ['label' => 'Renvoyer mon live', 'url' => route('live.mine')]);
    }

    public static function liveSubscriptionEnding(LiveSubscription $s): void
    {
        $s->loadMissing('user');
        self::send($s->user->email, 'Votre abonnement Lives se termine bientôt — OCD Live', [
            'Bonjour '.$s->user->name.',',
            'Votre abonnement se termine le '.self::format($s->ends_at).'. Il vous reste '.$s->remaining().' live(s) à utiliser d\'ici là.',
            'Pour continuer à être mis en avant, renouvelez-le : '.number_format(Settings::int('live_price'), 0, ',', ' ').' FCFA pour '.Settings::int('live_days').' jours et '.Settings::int('live_quota').' lives.',
        ], null, ['label' => 'Renouveler', 'url' => route('live.mine')]);
    }

    /** Alerte l'équipe d'une nouvelle réservation confirmée, avec un lien vers le tableau de bord. */
    public static function newBookingAlert(Booking $b): void
    {
        $b->loadMissing('slot', 'package', 'products');
        $when = self::format($b->slot->starts_at);
        $country = collect(Countries::all())->firstWhere('code', $b->country)['name'] ?? $b->country;
        $lines = [
            'Nouvelle réservation confirmée : '.$b->reference,
            'Passage : '.$when.' (heure de Lomé) — '.($b->mode === 'SUR_PLACE' ? 'sur place, au studio' : 'à distance, sur TikTok'),
            'Boutique : '.$b->shop_name.' — '.$b->activity_domain.' ('.$b->city.($country ? ', '.$country : '').')',
            'Activité : '.$b->activity_description,
            'Vendeur : '.$b->full_name.' — WhatsApp '.$b->phone.' — '.$b->email,
            'Package : '.$b->package->name.' ('.$b->package->duration_minutes.' min) — '.number_format($b->amount, 0, ',', ' ').' FCFA — '.$b->products->count().' produit(s)',
            'Connectez-vous au tableau de bord pour voir le planning et la fiche complète du vendeur.',
        ];
        $action = ['label' => 'Voir la réservation', 'url' => route('team.booking', $b)];
        foreach (self::teamRecipients() as $to) {
            self::send($to, 'Nouvelle réservation — '.$b->shop_name.' — '.$when, $lines, null, $action);
        }
    }

    /** F8 : récapitulatif, numéro de réservation et lien de suivi. */
    public static function bookingConfirmed(Booking $b, ?string $intro = null): void
    {
        $b->loadMissing('slot', 'package', 'products');
        $lines = [
            'Bonjour '.$b->full_name.',',
            $intro ?? 'Votre réservation est confirmée, merci !',
            'Numéro de réservation : '.$b->reference,
            'Package : '.$b->package->name.' — '.number_format($b->amount, 0, ',', ' ').' FCFA',
            'Passage : '.self::format($b->slot->starts_at).' (heure de Lomé), '.$b->package->duration_minutes.' min',
            'Boutique : '.$b->shop_name.' ('.$b->products->count().' produit(s))',
            ...self::modeInstructions($b),
            'Suivre ma réservation et modifier mes produits jusqu\'à '.Settings::int('edit_deadline_hours').' h avant le live : '.self::trackingUrl($b),
        ];
        if ($wa = self::whatsappUrl($b)) {
            $lines[] = 'Une question ? WhatsApp : '.$wa;
        }
        self::send($b->email, 'Réservation confirmée '.$b->reference.' — OCD Live', $lines, [
            'name' => 'passage.ics', 'mime' => 'text/calendar', 'data' => IcsBuilder::forBooking($b),
        ]);
    }

    public static function bookingCancelled(Booking $b, string $why): void
    {
        $b->loadMissing('slot');
        self::send($b->email, 'Réservation annulée '.$b->reference.' — OCD Live', [
            'Bonjour '.$b->full_name.',',
            'Votre réservation '.$b->reference.' du '.self::format($b->slot->starts_at).' a été annulée.',
            $why,
        ]);
    }
}
