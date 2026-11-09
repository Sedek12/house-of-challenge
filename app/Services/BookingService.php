<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\Package;
use App\Models\Product;
use App\Models\Slot;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BookingService
{
    private const TAKEN = 'Ce créneau vient d\'être pris, choisissez-en un autre.';

    public function __construct(private SlotService $slots) {}

    private function assertSlotBookable(Slot $slot, string $mode): void
    {
        $session = $slot->session;
        if ($session->status !== 'PROGRAMMEE' || $slot->starts_at->lte(now())) {
            throw new BusinessException('Ce créneau n\'est plus disponible.');
        }
        if (! in_array($mode, $session->accepted_modes ?? [], true)) {
            throw new BusinessException('Ce mode de passage n\'est pas proposé pour ce live.');
        }
    }

    /** Traduit la violation de l'index unique `active_slot_id` en message métier. */
    private function guardDuplicate(\Closure $work): mixed
    {
        try {
            return $work();
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new BusinessException(self::TAKEN);
            }
            throw $e;
        }
    }

    /**
     * Crée la réservation : bloque le créneau (F3), enregistre le vendeur et ses produits (F4, F5).
     *
     * @param  array<string, mixed>  $data  full_name, phone, email, shop_name, city, tiktok, host_notes, mode
     * @param  array<int, array{name:string,price:int,live_price:int,quantity:int,photo?:?UploadedFile}>  $products
     */
    public function start(Package $pkg, int $slotId, array $data, array $products): Booking
    {
        if (! $pkg->is_active) {
            throw new BusinessException('Ce package n\'est plus disponible.');
        }
        if (count($products) < 1 || count($products) > $pkg->max_products) {
            throw new BusinessException("Ce package permet de 1 à {$pkg->max_products} produit(s).");
        }
        $slot = Slot::with('session')->findOrFail($slotId);
        $this->assertSlotBookable($slot, $data['mode']);

        $this->slots->releaseIfExpired($slot->id);

        return $this->guardDuplicate(fn () => DB::transaction(function () use ($pkg, $slot, $data, $products) {
            if (! $this->slots->hold($slot->id)) {
                throw new BusinessException(self::TAKEN);
            }
            $booking = Booking::create([
                ...$data, 'package_id' => $pkg->id, 'slot_id' => $slot->id, 'amount' => $pkg->price,
                'status' => 'EN_ATTENTE_PAIEMENT', 'consent_at' => now(),
            ]);
            foreach (array_values($products) as $i => $r) {
                $booking->products()->create([
                    'name' => $r['name'], 'price' => $r['price'], 'live_price' => $r['live_price'], 'quantity' => $r['quantity'],
                    'photo_path' => ! empty($r['photo']) ? UploadService::storeProductPhoto($r['photo']) : UploadService::duplicate($r['photo_from'] ?? null),
                    'position' => $i,
                ]);
            }

            return $booking;
        }));
    }

    public function editDeadlinePassed(Booking $b): bool
    {
        return now()->gt($b->slot->starts_at->copy()->subHours(Settings::int('edit_deadline_hours')));
    }

    /**
     * Mise à jour par le vendeur via son lien de suivi (F9) : produits et consignes, jusqu'à 24 h avant le live.
     *
     * @param  array<int, array{id?:?int,name:string,price:int,live_price:int,quantity:int,photo?:?UploadedFile}>  $rows
     */
    public function updateFromTracking(Booking $booking, array $rows, ?string $hostNotes): void
    {
        if (! in_array($booking->status, Booking::ACTIVE, true) || $this->editDeadlinePassed($booking)) {
            throw new BusinessException('Les modifications sont closes '.Settings::int('edit_deadline_hours').' h avant le live.');
        }
        $max = $booking->package->max_products;
        if (count($rows) < 1 || count($rows) > $max) {
            throw new BusinessException("Ce package permet de 1 à {$max} produit(s).");
        }

        DB::transaction(function () use ($booking, $rows, $hostNotes) {
            $booking->update(['host_notes' => $hostNotes]);
            $existing = $booking->products()->get()->keyBy('id');
            $keep = [];
            foreach (array_values($rows) as $i => $r) {
                $product = ! empty($r['id']) ? $existing->get((int) $r['id']) : null;
                $fields = ['name' => $r['name'], 'price' => $r['price'], 'live_price' => $r['live_price'], 'quantity' => $r['quantity'], 'position' => $i];
                if (! empty($r['photo'])) {
                    UploadService::delete($product?->photo_path);
                    $fields['photo_path'] = UploadService::storeProductPhoto($r['photo']);
                }
                $keep[] = $product ? tap($product)->update($fields)->id : $booking->products()->create($fields)->id;
            }
            $booking->products()->whereNotIn('id', $keep)->get()->each(function (Product $p) {
                UploadService::delete($p->photo_path);
                $p->delete();
            });
        });
    }

    public function confirm(Booking $booking): Booking
    {
        DB::transaction(function () use ($booking) {
            $booking = Booking::lockForUpdate()->find($booking->id);
            if ($booking->status !== 'EN_ATTENTE_PAIEMENT') {
                return;
            }
            $booking->update(['status' => 'CONFIRMEE']);
            $booking->slot->update(['status' => 'RESERVE', 'hold_expires_at' => null]);
        });
        $booking->refresh();
        if ($booking->status === 'CONFIRMEE') {
            Notifier::bookingConfirmed($booking);
            Notifier::newBookingAlert($booking);
        }

        return $booking;
    }

    /** Annulation (A9) ou échec de paiement : le créneau est libéré immédiatement. Le remboursement se fait hors du site. */
    public function cancel(Booking $booking, ?string $reason = null): Booking
    {
        if (! in_array($booking->status, Booking::ACTIVE, true)) {
            throw new BusinessException('Cette réservation ne peut plus être annulée.');
        }
        $wasConfirmed = $booking->status === 'CONFIRMEE';
        DB::transaction(function () use ($booking, $reason) {
            $booking->update(['status' => 'ANNULEE', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            $this->slots->release($booking->slot);
        });
        if ($wasConfirmed) {
            Notifier::bookingCancelled($booking, 'Pour tout remboursement, contactez-nous sur WhatsApp.');
        }

        return $booking->fresh();
    }

    /** Déplace une réservation confirmée vers un autre créneau libre (A9). */
    public function reschedule(Booking $booking, int $newSlotId): Booking
    {
        if ($booking->status !== 'CONFIRMEE') {
            throw new BusinessException('Seule une réservation confirmée peut être reportée.');
        }
        $new = Slot::with('session')->findOrFail($newSlotId);
        $this->assertSlotBookable($new, $booking->mode);

        $this->slots->releaseIfExpired($new->id);
        $this->guardDuplicate(fn () => DB::transaction(function () use ($booking, $new) {
            if (! $this->slots->hold($new->id)) {
                throw new BusinessException(self::TAKEN);
            }
            $old = $booking->slot;
            $booking->update(['slot_id' => $new->id]);
            $new->update(['status' => 'RESERVE', 'hold_expires_at' => null]);
            $this->slots->release($old);
        }));

        $booking->refresh();
        Notifier::bookingConfirmed($booking, 'Votre passage a été déplacé.');

        return $booking;
    }

    public function cancelSession(LiveSession $session): int
    {
        $n = 0;
        DB::transaction(function () use ($session, &$n) {
            $session->update(['status' => 'ANNULEE']);
            Booking::whereIn('status', Booking::ACTIVE)
                ->whereHas('slot', fn ($q) => $q->where('live_session_id', $session->id))
                ->with('slot')->get()
                ->each(function (Booking $b) use (&$n) {
                    $b->update(['status' => 'ANNULEE', 'cancelled_at' => now(), 'cancel_reason' => 'Live annulé par l\'organisation']);
                    Notifier::bookingCancelled($b, 'Le live a été annulé par l\'organisation. Contactez-nous sur WhatsApp pour choisir une nouvelle date ou être remboursé.');
                    $n++;
                });
            $session->slots()->update(['status' => 'BLOQUE', 'hold_expires_at' => null]);
        });

        return $n;
    }

    /** « Live effectué » ou « Absent » (A5), par l'administrateur. */
    public function mark(Booking $booking, string $status): Booking
    {
        if (! in_array($status, ['EFFECTUEE', 'ABSENT'], true) || ! in_array($booking->status, ['CONFIRMEE', 'EFFECTUEE', 'ABSENT'], true)) {
            throw new BusinessException('Statut invalide pour cette réservation.');
        }
        $booking->update(['status' => $status]);

        return $booking;
    }
}
