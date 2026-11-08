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
     * Prise d'un créneau avec mise à jour conditionnelle atomique.
     * Retourne false si le créneau vient d'être pris.
     */
    public function hold(int $slotId, int $userId): bool
    {
        $now = now();
        $affected = Slot::where('id', $slotId)
            ->where(function ($q) use ($now) {
                $q->where('status', 'DISPONIBLE')
                    ->orWhere(fn ($q2) => $q2->where('status', 'EN_ATTENTE')->where('hold_expires_at', '<', $now));
            })
            ->update([
                'status' => 'EN_ATTENTE',
                'hold_expires_at' => $now->copy()->addMinutes(Settings::int('hold_minutes')),
                'held_by_user_id' => $userId,
            ]);

        return $affected === 1;
    }

    public function release(Slot $slot): void
    {
        if ($slot->status !== 'BLOQUE') {
            $slot->update(['status' => 'DISPONIBLE', 'hold_expires_at' => null, 'held_by_user_id' => null]);
        }
    }

    /** Libère les holds expirés et annule les réservations en attente de paiement associées. */
    public function releaseExpiredHolds(): int
    {
        $count = 0;
        Slot::where('status', 'EN_ATTENTE')->where('hold_expires_at', '<', now())->each(function (Slot $slot) use (&$count) {
            DB::transaction(function () use ($slot, &$count) {
                $slot = Slot::lockForUpdate()->find($slot->id);
                if ($slot->status !== 'EN_ATTENTE' || $slot->hold_expires_at?->gte(now())) {
                    return;
                }
                Booking::where('slot_id', $slot->id)->where('status', 'EN_ATTENTE_PAIEMENT')
                    ->update(['status' => 'ANNULEE', 'cancelled_at' => now(), 'cancel_reason' => 'Délai de paiement expiré']);
                $this->release($slot);
                $count++;
            });
        });

        return $count;
    }
}
