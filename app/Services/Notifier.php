<?php

namespace App\Services;

use App\Mail\GenericMail;
use App\Models\Booking;
use App\Models\Publication;
use App\Models\Purchase;
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
                'Soyez connecté sur TikTok avec le compte @'.($b->user->tiktok_username ?? '…').' 15 minutes avant ; vous recevrez une invitation à rejoindre le live.',
                'Prévoyez une bonne connexion internet et un bon éclairage.',
            ];
        }
        if ($wa = Settings::get('contact_whatsapp')) {
            $lines[] = 'Contact WhatsApp : https://wa.me/'.preg_replace('/\D/', '', $wa);
        }

        return $lines;
    }

    public static function send(?string $to, string $subject, array $lines, ?array $attachment = null): void
    {
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send(new GenericMail($subject, $lines, $attachment));
        } catch (\Throwable $e) {
            Log::error('Échec envoi e-mail : '.$e->getMessage(), ['to' => $to, 'subject' => $subject]);
        }
    }

    public static function bookingConfirmed(Booking $b): void
    {
        $b->loadMissing('user', 'slot', 'project');
        $lines = array_merge([
            'Bonjour '.$b->user->first_name.',',
            'Votre passage à House of Challenge est confirmé.',
            'Date : '.self::format($b->slot->starts_at).' (heure de Lomé)',
            'Projet : '.$b->project->title,
        ], self::modeInstructions($b));
        self::send($b->user->email, 'Réservation confirmée — House of Challenge', $lines, [
            'name' => 'passage.ics', 'mime' => 'text/calendar', 'data' => IcsBuilder::forBooking($b),
        ]);
    }

    public static function bookingCancelled(Booking $b, string $why): void
    {
        $b->loadMissing('user', 'slot');
        self::send($b->user->email, 'Réservation annulée — House of Challenge', [
            'Bonjour '.$b->user->first_name.',',
            'Votre réservation du '.self::format($b->slot->starts_at).' a été annulée.',
            $why,
        ]);
    }

    public static function reminder(Booking $b, string $when): void
    {
        $b->loadMissing('user', 'slot');
        $lines = array_merge([
            'Bonjour '.$b->user->first_name.',',
            "Rappel : votre passage a lieu dans {$when}, le ".self::format($b->slot->starts_at).' (heure de Lomé).',
        ], self::modeInstructions($b));
        self::send($b->user->email, "Rappel : votre passage dans {$when}", $lines);
    }

    public static function paymentReceived(Purchase $p): void
    {
        $p->loadMissing('user');
        self::send($p->user->email, 'Paiement reçu — House of Challenge', [
            'Bonjour '.$p->user->first_name.',',
            'Nous avons bien reçu votre paiement.',
            'Commande n° '.$p->id.' — '.$p->package_name,
            'Montant : '.number_format($p->amount, 0, ',', ' ').' FCFA',
            $p->credits_total.' crédit(s) de passage ajouté(s) à votre compte.',
        ]);
    }

    public static function publicationReviewed(Publication $pub, bool $accepted): void
    {
        $pub->loadMissing('purchase.user', 'project');
        $u = $pub->purchase->user;
        self::send($u->email, $accepted ? 'Votre projet est publié' : 'Publication refusée', $accepted
            ? ['Bonjour '.$u->first_name.',', 'Votre projet « '.$pub->project?->title.' » est maintenant visible sur House of Challenge.']
            : ['Bonjour '.$u->first_name.',', 'Votre demande de publication a été refusée.', 'Motif : '.$pub->reject_reason]);
    }
}
