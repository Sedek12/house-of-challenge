<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CreditLedger;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreditService
{
    /** Achats valides (payés, non expirés, avec des crédits), le plus proche de l'expiration d'abord. */
    private function validPurchases(int $userId)
    {
        return Purchase::where('user_id', $userId)
            ->where('status', 'PAYE')
            ->where('credits_remaining', '>', 0)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByRaw('expires_at IS NULL, expires_at ASC')
            ->orderBy('id');
    }

    public function available(User|int $user): int
    {
        $id = $user instanceof User ? $user->id : $user;

        return (int) $this->validPurchases($id)->sum('credits_remaining');
    }

    public function nextExpiry(User|int $user): ?Carbon
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->validPurchases($id)->whereNotNull('expires_at')->min('expires_at')
            ? Carbon::parse($this->validPurchases($id)->whereNotNull('expires_at')->min('expires_at'))
            : null;
    }

    /** Consomme 1 crédit (FIFO). Retourne l'achat utilisé ou null s'il n'y a plus de crédit. */
    public function consume(User|int $user, Booking $booking): ?Purchase
    {
        $id = $user instanceof User ? $user->id : $user;

        return DB::transaction(function () use ($id, $booking) {
            $purchase = $this->validPurchases($id)->lockForUpdate()->first();
            if (! $purchase) {
                return null;
            }
            $purchase->decrement('credits_remaining');
            CreditLedger::create([
                'user_id' => $id, 'purchase_id' => $purchase->id, 'booking_id' => $booking->id,
                'delta' => -1, 'reason' => 'RESERVATION',
            ]);
            $booking->update(['purchase_id' => $purchase->id]);

            return $purchase;
        });
    }

    /** Rend 1 crédit à l'achat d'origine de la réservation. */
    public function refund(Booking $booking, string $reason = 'ANNULATION', ?string $note = null): bool
    {
        if (! $booking->purchase_id) {
            return false;
        }

        return DB::transaction(function () use ($booking, $reason, $note) {
            $purchase = Purchase::lockForUpdate()->find($booking->purchase_id);
            if (! $purchase || $purchase->credits_remaining >= $purchase->credits_total) {
                return false;
            }
            $already = CreditLedger::where('booking_id', $booking->id)->sum('delta');
            if ($already >= 0) { // pas de crédit consommé en attente de restitution
                return false;
            }
            $purchase->increment('credits_remaining');
            CreditLedger::create([
                'user_id' => $booking->user_id, 'purchase_id' => $purchase->id, 'booking_id' => $booking->id,
                'delta' => 1, 'reason' => $reason, 'note' => $note, 'created_by_id' => auth()->id(),
            ]);

            return true;
        });
    }

    /** Ajustement manuel (admin). Crée un achat "ajustement" si positif. */
    public function adjust(User $user, int $delta, string $note): void
    {
        DB::transaction(function () use ($user, $delta, $note) {
            if ($delta > 0) {
                $pkg = Package::orderBy('id')->firstOrFail();
                $p = Purchase::create([
                    'user_id' => $user->id, 'package_id' => $pkg->id, 'package_name' => 'Ajustement admin',
                    'amount' => 0, 'credits_total' => $delta, 'credits_remaining' => $delta,
                    'validity_days' => 90, 'status' => 'PAYE', 'paid_at' => now(), 'expires_at' => now()->addDays(90),
                ]);
                CreditLedger::create(['user_id' => $user->id, 'purchase_id' => $p->id, 'delta' => $delta,
                    'reason' => 'AJUSTEMENT_ADMIN', 'note' => $note, 'created_by_id' => auth()->id()]);

                return;
            }
            $remaining = -$delta;
            while ($remaining > 0 && ($p = $this->validPurchases($user->id)->lockForUpdate()->first())) {
                $take = min($remaining, $p->credits_remaining);
                $p->decrement('credits_remaining', $take);
                CreditLedger::create(['user_id' => $user->id, 'purchase_id' => $p->id, 'delta' => -$take,
                    'reason' => 'AJUSTEMENT_ADMIN', 'note' => $note, 'created_by_id' => auth()->id()]);
                $remaining -= $take;
            }
        });
    }

    /** Expire les crédits restants des achats échus. Idempotent. */
    public function expire(): int
    {
        $count = 0;
        Purchase::where('status', 'PAYE')->where('credits_remaining', '>', 0)
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->each(function (Purchase $p) use (&$count) {
                DB::transaction(function () use ($p, &$count) {
                    $p = Purchase::lockForUpdate()->find($p->id);
                    if ($p->credits_remaining <= 0) {
                        return;
                    }
                    CreditLedger::create(['user_id' => $p->user_id, 'purchase_id' => $p->id,
                        'delta' => -$p->credits_remaining, 'reason' => 'EXPIRATION']);
                    $p->update(['credits_remaining' => 0]);
                    $count++;
                });
            });

        return $count;
    }
}
