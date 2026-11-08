<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\Project;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(private SlotService $slots, private CreditService $credits) {}

    private function assertSlotBookable(Slot $slot, string $mode, User $user): void
    {
        $session = $slot->session;
        if ($session->status !== 'PROGRAMMEE' || $slot->starts_at->lte(now())) {
            throw new BusinessException('Ce créneau n\'est plus disponible.');
        }
        if (! in_array($mode, $session->accepted_modes ?? [], true)) {
            throw new BusinessException('Ce mode de passage n\'est pas accepté pour cette session.');
        }
        if ($mode === 'A_DISTANCE' && ! $user->tiktok_username) {
            throw new BusinessException('Renseignez votre pseudo TikTok dans votre profil pour passer à distance.');
        }
    }

    private function futureActiveCount(User $user): int
    {
        return Booking::where('user_id', $user->id)->whereIn('status', Booking::ACTIVE)
            ->whereHas('slot', fn ($q) => $q->where('starts_at', '>', now()))->count();
    }

    /** Pose un hold sur le créneau et crée la réservation ; la confirme immédiatement si un crédit existe. */
    public function start(User $user, Project $project, int $slotId, string $mode): Booking
    {
        if ($project->user_id !== $user->id) {
            throw new BusinessException('Projet introuvable.');
        }
        $slot = Slot::with('session')->findOrFail($slotId);
        $this->assertSlotBookable($slot, $mode, $user);

        // Abandonner d'éventuelles réservations en attente de paiement de cet utilisateur.
        Booking::where('user_id', $user->id)->where('status', 'EN_ATTENTE_PAIEMENT')->with('slot')->get()
            ->each(fn (Booking $b) => $this->cancel($b, true, 'Nouvelle réservation commencée'));

        if ($this->futureActiveCount($user) >= Settings::int('max_future_bookings')) {
            throw new BusinessException('Vous avez atteint le nombre maximum de réservations à venir ('.Settings::int('max_future_bookings').').');
        }

        $booking = DB::transaction(function () use ($user, $project, $slot, $mode) {
            if (! $this->slots->hold($slot->id, $user->id)) {
                throw new BusinessException('Ce créneau vient d\'être réservé, choisissez-en un autre.');
            }

            return Booking::create([
                'user_id' => $user->id, 'project_id' => $project->id, 'slot_id' => $slot->id,
                'mode' => $mode, 'status' => 'EN_ATTENTE_PAIEMENT',
            ]);
        });

        if ($this->credits->consume($user, $booking)) {
            $this->confirm($booking);
        }

        return $booking->fresh();
    }

    public function confirm(Booking $booking): Booking
    {
        DB::transaction(function () use ($booking) {
            $booking = Booking::lockForUpdate()->find($booking->id);
            if ($booking->status !== 'EN_ATTENTE_PAIEMENT') {
                return;
            }
            $booking->update(['status' => 'CONFIRMEE']);
            $booking->slot->update(['status' => 'RESERVE', 'hold_expires_at' => null, 'held_by_user_id' => $booking->user_id]);
        });
        $booking->refresh();
        if ($booking->status === 'CONFIRMEE') {
            Notifier::bookingConfirmed($booking);
        }

        return $booking;
    }

    public function deadlinePassed(Booking $b): bool
    {
        return now()->gt($b->slot->starts_at->copy()->subHours(Settings::int('cancel_deadline_hours')));
    }

    public function cancel(Booking $booking, bool $byStaff = false, ?string $reason = null): Booking
    {
        if (! in_array($booking->status, Booking::ACTIVE, true)) {
            throw new BusinessException('Cette réservation ne peut plus être annulée.');
        }
        $late = $this->deadlinePassed($booking);
        if ($late && ! $byStaff && $booking->status === 'CONFIRMEE') {
            throw new BusinessException('Le délai d\'annulation ('.Settings::int('cancel_deadline_hours').' h avant le passage) est dépassé.');
        }

        DB::transaction(function () use ($booking, $reason) {
            $booking->update(['status' => 'ANNULEE', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            $this->slots->release($booking->slot);
            $this->credits->refund($booking);
        });

        if ($booking->wasChanged('status') && $booking->getOriginal('status') === 'CONFIRMEE' && ! $byStaff) {
            Notifier::bookingCancelled($booking, 'Votre crédit vous a été rendu.');
        }

        return $booking->fresh();
    }

    public function reschedule(Booking $booking, int $newSlotId): Booking
    {
        if ($booking->status !== 'CONFIRMEE') {
            throw new BusinessException('Seule une réservation confirmée peut être reportée.');
        }
        if ($this->deadlinePassed($booking)) {
            throw new BusinessException('Le délai pour reporter est dépassé.');
        }
        $new = Slot::with('session')->findOrFail($newSlotId);
        $this->assertSlotBookable($new, $booking->mode, $booking->user);

        DB::transaction(function () use ($booking, $new) {
            if (! $this->slots->hold($new->id, $booking->user_id)) {
                throw new BusinessException('Ce créneau vient d\'être réservé, choisissez-en un autre.');
            }
            $old = $booking->slot;
            $new->update(['status' => 'RESERVE', 'hold_expires_at' => null]);
            $booking->update(['slot_id' => $new->id, 'reminder_24h_sent' => false, 'reminder_2h_sent' => false]);
            $this->slots->release($old);
        });

        $booking->refresh();
        Notifier::bookingConfirmed($booking);

        return $booking;
    }

    public function cancelSession(LiveSession $session): int
    {
        $n = 0;
        DB::transaction(function () use ($session, &$n) {
            $session->update(['status' => 'ANNULEE']);
            Booking::whereIn('status', Booking::ACTIVE)
                ->whereHas('slot', fn ($q) => $q->where('live_session_id', $session->id))
                ->with('slot', 'user')->get()
                ->each(function (Booking $b) use (&$n) {
                    $b->update(['status' => 'ANNULEE_PAR_ORGANISATEUR', 'cancelled_at' => now(), 'cancel_reason' => 'Session annulée']);
                    $this->credits->refund($b);
                    Notifier::bookingCancelled($b, 'La session a été annulée par l\'organisation. Votre crédit vous a été rendu, vous pouvez choisir une nouvelle date.');
                    $n++;
                });
            $session->slots()->update(['status' => 'BLOQUE', 'hold_expires_at' => null, 'held_by_user_id' => null]);
        });

        return $n;
    }

    public function mark(Booking $booking, string $status): Booking
    {
        if (! in_array($status, ['EFFECTUEE', 'ABSENT'], true) || $booking->status !== 'CONFIRMEE' && ! in_array($booking->status, ['EFFECTUEE', 'ABSENT'], true)) {
            throw new BusinessException('Statut invalide pour cette réservation.');
        }
        $booking->update(['status' => $status]);

        return $booking;
    }
}
