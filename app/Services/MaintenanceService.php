<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Publication;

class MaintenanceService
{
    public function __construct(
        private SlotService $slots,
        private CreditService $credits,
        private PurchaseService $purchases,
    ) {}

    /** Envoie chaque rappel une seule fois (drapeaux sur la réservation). */
    public function sendReminders(): int
    {
        $sent = 0;
        $base = Booking::where('status', 'CONFIRMEE')->with('user', 'slot');

        (clone $base)->where('reminder_2h_sent', false)
            ->whereHas('slot', fn ($q) => $q->where('starts_at', '>', now())->where('starts_at', '<=', now()->addHours(2)))
            ->each(function (Booking $b) use (&$sent) {
                if ($b->newQuery()->whereKey($b->id)->where('reminder_2h_sent', false)->update(['reminder_2h_sent' => true, 'reminder_24h_sent' => true])) {
                    Notifier::reminder($b, '2 heures');
                    $sent++;
                }
            });

        (clone $base)->where('reminder_24h_sent', false)
            ->whereHas('slot', fn ($q) => $q->where('starts_at', '>', now()->addHours(2))->where('starts_at', '<=', now()->addHours(24)))
            ->each(function (Booking $b) use (&$sent) {
                if ($b->newQuery()->whereKey($b->id)->where('reminder_24h_sent', false)->update(['reminder_24h_sent' => true])) {
                    Notifier::reminder($b, '24 heures');
                    $sent++;
                }
            });

        return $sent;
    }

    /** Prévient le participant 3 jours avant la fin de sa publication (une seule fois). */
    public function notifyExpiringPublications(): int
    {
        $n = 0;
        Publication::with('purchase.user', 'project')->where('status', 'PUBLIEE')->where('expiry_notice_sent', false)
            ->where('expires_at', '>', now())->where('expires_at', '<=', now()->addDays(3))
            ->each(function (Publication $p) use (&$n) {
                if (Publication::whereKey($p->id)->where('expiry_notice_sent', false)->update(['expiry_notice_sent' => true])) {
                    $u = $p->purchase->user;
                    Notifier::send($u->email, 'Votre publication expire bientôt', [
                        'Bonjour '.$u->first_name.',',
                        'La publication de « '.$p->project?->title.' » prend fin le '.Notifier::format($p->expires_at).'.',
                        'Vous pouvez renouveler votre visibilité avec une nouvelle formule.',
                    ]);
                    $n++;
                }
            });

        return $n;
    }

    public function expirePublications(): int
    {
        return Publication::where('status', 'PUBLIEE')->where('expires_at', '<=', now())->update(['status' => 'EXPIREE']);
    }

    public function run(): array
    {
        return [
            'holds_released' => $this->slots->releaseExpiredHolds(),
            'payments_reconciled' => $this->purchases->reconcilePending(),
            'reminders_sent' => $this->sendReminders(),
            'credits_expired' => $this->credits->expire(),
            'publication_notices' => $this->notifyExpiringPublications(),
            'publications_expired' => $this->expirePublications(),
        ];
    }
}
