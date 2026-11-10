<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['raw_payload' => 'array'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** Paiement d'un abonnement à l'annuaire des lives (sinon : paiement d'une réservation). */
    public function liveSubscription(): BelongsTo
    {
        return $this->belongsTo(LiveSubscription::class);
    }
}
