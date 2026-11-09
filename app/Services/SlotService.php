<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\Slot;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SlotService
{
    /**
     * Calcule les plages [début, fin] sans toucher à la base (testable).
     * Le dernier créneau ne dépasse jamais $end.
     *
     * @return array<int, array{0: CarbonInterface, 1: CarbonInterface}>
     */
    public static function computeRanges(CarbonInterface $start, CarbonInterface $end, int $slotMinutes, int $breakMinutes = 0): array
    {
        $ranges = [];
        if ($slotMinutes <= 0) {
            return $ranges;
        }
        $cursor = $start->copy();
        while ($cursor->copy()->addMinutes($slotMinutes)->lte($end)) {
            $ranges[] = [$cursor->copy(), $cursor->copy()->addMinutes($slotMinutes)];
            $cursor->addMinutes($slotMinutes + $breakMinutes);
        }

        return $ranges;
    }

    public function generate(LiveSession $session): int
    {
        $ranges = self::computeRanges($session->starts_at, $session->ends_at, $session->slot_minutes, $session->break_minutes);
        foreach ($ranges as $i => [$s, $e]) {
            $session->slots()->create(['starts_at' => $s, 'ends_at' => $e, 'position' => $i + 1]);
        }

        return count($ranges);
    }

    /**
     * Blocage d'un créneau par mise à jour conditionnelle atomique (F3).
     * Retourne false si le créneau vient d'être pris par quelqu'un d'autre.
     */
    public function hold(int $slotId): bool
    {
        $now = now();

        return Slot::where('id', $slotId)
            ->where(function ($q) use ($now) {
                $q->where('status', 'DISPONIBLE')
                    ->orWhere(fn ($q2) => $q2->where('status', 'EN_ATTENTE')->where('hold_expires_at', '<', $now));
            })
            ->update(['status' => 'EN_ATTENTE', 'hold_expires_at' => $now->copy()->addMinutes(Settings::int('hold_minutes'))]) === 1;
    }

    public function release(Slot $slot): void
    {
        if ($slot->status !== 'BLOQUE') {
            $slot->update(['status' => 'DISPONIBLE', 'hold_expires_at' => null]);
        }
    }

    /** Libère les blocages expirés et annule les réservations en attente de paiement associées. */
    public function releaseExpiredHolds(): int
    {
        $count = 0;
        Slot::where('status', 'EN_ATTENTE')->where('hold_expires_at', '<', now())->pluck('id')
            ->each(function ($id) use (&$count) {
                $count += $this->releaseIfExpired((int) $id) ? 1 : 0;
            });

        return $count;
    }

    /** Libère un créneau dont le blocage a expiré (utilisé aussi juste avant une nouvelle réservation du même créneau). */
    public function releaseIfExpired(int $slotId): bool
    {
        return DB::transaction(function () use ($slotId) {
            $slot = Slot::lockForUpdate()->find($slotId);
            if (! $slot || $slot->status !== 'EN_ATTENTE' || $slot->hold_expires_at?->gte(now())) {
                return false;
            }
            Booking::where('slot_id', $slot->id)->where('status', 'EN_ATTENTE_PAIEMENT')->each(
                fn (Booking $b) => $b->update(['status' => 'ANNULEE', 'cancelled_at' => now(), 'cancel_reason' => 'Délai de paiement expiré'])
            );
            $this->release($slot);

            return true;
        });
    }
}
