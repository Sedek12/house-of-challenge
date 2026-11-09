<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\Providers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Paiement d'une réservation (F7). Une réservation n'est jamais confirmée par la page de retour :
 * uniquement par la notification serveur de l'agrégateur (ou par réconciliation / saisie manuelle de l'admin).
 */
class PaymentService
{
    public function __construct(private BookingService $bookings) {}

    /** @return array{payment: Payment, checkoutUrl: string} */
    public function checkout(Booking $booking): array
    {
        if ($booking->status !== 'EN_ATTENTE_PAIEMENT') {
            throw new BusinessException('Réservation introuvable ou expirée.');
        }
        $payment = Payment::create([
            'booking_id' => $booking->id, 'provider' => config('services.payment.provider', 'mock'),
            'method' => 'EN_LIGNE', 'amount' => $booking->amount, 'currency' => 'XOF',
        ]);

        $res = Providers::make($payment->provider)->createCheckout([
            'orderId' => (string) $booking->id, 'amount' => $payment->amount, 'currency' => 'XOF',
            'customer' => ['name' => $booking->full_name, 'email' => $booking->email, 'phone' => $booking->phone],
            'returnUrl' => route('payment.return', ['token' => $booking->tracking_token]),
            'description' => 'Réservation '.$booking->reference.' — '.$booking->package->name,
        ]);
        $payment->update(['provider_ref' => $res['providerRef']]);

        return ['payment' => $payment, 'checkoutUrl' => $res['checkoutUrl']];
    }

    /** Traitement d'un événement fournisseur (webhook ou réconciliation). Idempotent. */
    public function handleEvent(string $providerRef, string $status, int $amount): string
    {
        $outcome = DB::transaction(function () use ($providerRef, $status, $amount) {
            $payment = Payment::where('provider_ref', $providerRef)->lockForUpdate()->first();
            if (! $payment) {
                return 'unknown';
            }
            if ($payment->status !== 'EN_ATTENTE') {
                return 'already';
            }
            if ($status === 'PAID') {
                if ($amount !== $payment->amount) {
                    Log::warning('Montant du paiement incohérent', ['ref' => $providerRef, 'attendu' => $payment->amount, 'recu' => $amount]);
                    $payment->update(['status' => 'ECHOUE', 'raw_payload' => ['error' => 'amount_mismatch', 'received' => $amount]]);

                    return 'amount_mismatch';
                }
                $payment->update(['status' => 'PAYE']);

                return 'paid';
            }
            $payment->update(['status' => $status === 'CANCELLED' ? 'ANNULE' : 'ECHOUE']);

            return 'failed';
        });

        $payment = Payment::where('provider_ref', $providerRef)->with('booking')->first();
        if ($outcome === 'paid') {
            $this->bookings->confirm($payment->booking);
        } elseif (in_array($outcome, ['failed', 'amount_mismatch'], true) && $payment->booking->status === 'EN_ATTENTE_PAIEMENT') {
            // Le paiement n'a pas abouti : le créneau redevient libre (F2).
            $this->bookings->cancel($payment->booking, 'Paiement non abouti');
        }

        return $outcome;
    }

    /**
     * Interroge l'agrégateur, de serveur à serveur, sur le paiement en attente d'une réservation.
     * Appelé quand le vendeur revient du paiement : c'est la réponse de l'agrégateur qui fait foi, jamais l'adresse de retour.
     * Utile quand la notification (webhook) tarde ou ne peut pas arriver (développement en local). Au plus un appel toutes les 4 s.
     */
    public function syncPending(Booking $booking): void
    {
        $p = Payment::where('booking_id', $booking->id)->where('status', 'EN_ATTENTE')->where('provider', '!=', 'manuel')
            ->whereNotNull('provider_ref')->latest('id')->first();
        if (! $p || ! Cache::add('pay-sync-'.$p->id, 1, 4)) {
            return;
        }
        try {
            $s = Providers::make($p->provider)->fetchStatus($p->provider_ref);
            if ($s !== 'PENDING') {
                $this->handleEvent($p->provider_ref, $s, $p->amount);
            }
        } catch (\Throwable $e) {
            Log::warning('Vérification du paiement impossible : '.$e->getMessage(), ['payment' => $p->id]);
        }
    }

    /** Paiement hors ligne (espèces / transfert) saisi par l'admin : même effet qu'un paiement en ligne. */
    public function recordManual(User $admin, Booking $booking, string $method): Payment
    {
        if ($booking->status !== 'EN_ATTENTE_PAIEMENT') {
            throw new BusinessException('Cette réservation n\'attend pas de paiement.');
        }
        $payment = Payment::create([
            'booking_id' => $booking->id, 'provider' => 'manuel', 'provider_ref' => 'manuel_'.uniqid(), 'method' => $method,
            'amount' => $booking->amount, 'status' => 'PAYE', 'recorded_by_id' => $admin->id,
        ]);
        $this->bookings->confirm($booking);
        Audit::log('PAYMENT_MANUAL', 'Payment', $payment->id, ['method' => $method, 'booking' => $booking->id]);

        return $payment;
    }

    /** Tâche planifiée : interroge l'agrégateur pour les paiements en attente depuis plus de 10 min. */
    public function reconcilePending(): int
    {
        $n = 0;
        Payment::where('status', 'EN_ATTENTE')->where('provider', '!=', 'manuel')->whereNotNull('provider_ref')
            ->where('created_at', '<', now()->subMinutes(10))->each(function (Payment $p) use (&$n) {
                try {
                    $s = Providers::make($p->provider)->fetchStatus($p->provider_ref);
                    if ($s !== 'PENDING') {
                        $this->handleEvent($p->provider_ref, $s, $p->amount);
                        $n++;
                    }
                } catch (\Throwable $e) {
                    Log::error('Réconciliation paiement échouée : '.$e->getMessage(), ['payment' => $p->id]);
                }
            });

        return $n;
    }
}
