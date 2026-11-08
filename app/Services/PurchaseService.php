<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Booking;
use App\Models\CreditLedger;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Publication;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Payments\Providers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseService
{
    public function __construct(private BookingService $bookings, private CreditService $credits) {}

    private function newPurchase(User $user, Package $pkg, ?string $mode): Purchase
    {
        if (! $pkg->is_active) {
            throw new BusinessException('Cette formule n\'est plus disponible.');
        }
        if ($mode && ! $pkg->allows($mode)) {
            throw new BusinessException('Cette formule n\'est pas disponible pour ce mode de passage.');
        }

        return Purchase::create([
            'user_id' => $user->id, 'package_id' => $pkg->id, 'package_name' => $pkg->name,
            'amount' => $pkg->priceFor($mode), 'credits_total' => $pkg->credits_included,
            'credits_remaining' => $pkg->credits_included, 'validity_days' => $pkg->validity_days,
            'includes_publication' => $pkg->includes_publication, 'publication_days' => $pkg->publication_days,
        ]);
    }

    /** @return array{payment: Payment, checkoutUrl: string} */
    public function checkout(User $user, Package $pkg, ?string $mode = null, ?Booking $booking = null): array
    {
        if ($booking && ($booking->user_id !== $user->id || $booking->status !== 'EN_ATTENTE_PAIEMENT')) {
            throw new BusinessException('Réservation introuvable ou expirée.');
        }
        $mode ??= $booking?->mode;

        $payment = DB::transaction(function () use ($user, $pkg, $mode, $booking) {
            $purchase = $this->newPurchase($user, $pkg, $mode);

            return Payment::create([
                'purchase_id' => $purchase->id, 'booking_id' => $booking?->id,
                'provider' => config('services.payment.provider', 'mock'), 'method' => 'EN_LIGNE',
                'amount' => $purchase->amount, 'currency' => 'XOF',
            ]);
        });

        $provider = Providers::make($payment->provider);
        $res = $provider->createCheckout([
            'orderId' => (string) $payment->purchase_id, 'amount' => $payment->amount, 'currency' => 'XOF',
            'customer' => ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone_e164],
            'returnUrl' => route('payment.return', ['payment' => $payment->id]),
            'description' => $pkg->name,
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
                    $payment->purchase->update(['status' => 'ECHOUE']);

                    return 'amount_mismatch';
                }
                $this->markPaid($payment);

                return 'paid';
            }

            $failed = $status === 'CANCELLED' ? 'ANNULE' : 'ECHOUE';
            $payment->update(['status' => $failed]);
            $payment->purchase->update(['status' => $failed === 'ANNULE' ? 'ANNULE' : 'ECHOUE']);

            return 'failed';
        });

        if ($outcome === 'paid') {
            $this->afterPaid(Payment::where('provider_ref', $providerRef)->first());
        }

        return $outcome;
    }

    /** À appeler dans une transaction : l'achat devient PAYE, les crédits sont ajoutés. */
    private function markPaid(Payment $payment): void
    {
        $purchase = Purchase::lockForUpdate()->find($payment->purchase_id);
        $payment->update(['status' => 'PAYE']);
        $purchase->update([
            'status' => 'PAYE', 'paid_at' => now(), 'expires_at' => now()->addDays($purchase->validity_days),
        ]);
        CreditLedger::create([
            'user_id' => $purchase->user_id, 'purchase_id' => $purchase->id, 'delta' => $purchase->credits_total,
            'reason' => 'ACHAT', 'created_by_id' => $payment->recorded_by_id,
        ]);
        if ($purchase->includes_publication) {
            Publication::create([
                'purchase_id' => $purchase->id,
                'project_id' => $payment->booking_id ? Booking::find($payment->booking_id)?->project_id : null,
            ]);
        }
    }

    /** Hors transaction : confirmation de la réservation liée et e-mails. */
    private function afterPaid(Payment $payment): void
    {
        $purchase = $payment->purchase()->first();
        if ($payment->booking_id) {
            $booking = Booking::find($payment->booking_id);
            if ($booking && $booking->status === 'EN_ATTENTE_PAIEMENT' && $this->credits->consume($purchase->user_id, $booking)) {
                $this->bookings->confirm($booking);
            }
        }
        Notifier::paymentReceived($purchase);
    }

    /** Paiement manuel (espèces / transfert) : même effet qu'un paiement en ligne. */
    public function recordManual(User $admin, User $user, Package $pkg, string $method, ?string $mode = null, ?Booking $booking = null): Payment
    {
        $payment = DB::transaction(function () use ($admin, $user, $pkg, $method, $mode, $booking) {
            $purchase = $this->newPurchase($user, $pkg, $mode ?? $booking?->mode);
            $payment = Payment::create([
                'purchase_id' => $purchase->id, 'booking_id' => $booking?->id, 'provider' => 'manuel',
                'provider_ref' => 'manuel_'.uniqid(), 'method' => $method, 'amount' => $purchase->amount,
                'recorded_by_id' => $admin->id,
            ]);
            $this->markPaid($payment);

            return $payment;
        });
        $this->afterPaid($payment->fresh());
        Audit::log('PAYMENT_MANUAL', 'Payment', $payment->id, ['method' => $method, 'user' => $user->id]);

        return $payment;
    }

    /** Tâche planifiée : interroge le prestataire pour les paiements en attente depuis plus de 10 min. */
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
