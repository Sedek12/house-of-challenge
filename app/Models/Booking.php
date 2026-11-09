<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Booking extends Model
{
    protected $guarded = [];

    /** Statuts qui occupent le créneau (index unique `active_slot_id`). */
    public const HOLDING = ['EN_ATTENTE_PAIEMENT', 'CONFIRMEE', 'EFFECTUEE', 'ABSENT'];

    /** Réservations vivantes : peuvent encore être annulées. */
    public const ACTIVE = ['EN_ATTENTE_PAIEMENT', 'CONFIRMEE'];

    /** Domaines d'activité proposés au vendeur (liste fermée, facile à exporter et à filtrer). */
    public const DOMAINS = [
        'Mode et vêtements', 'Beauté et cosmétiques', 'Alimentation et boissons', 'Électronique et accessoires',
        'Maison et décoration', 'Artisanat', 'Services', 'Autre',
    ];

    protected function casts(): array
    {
        return ['cancelled_at' => 'datetime', 'consent_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $b) {
            $b->reference ??= self::newReference();
            $b->tracking_token ??= Str::random(40);
        });
        static::saving(function (Booking $b) {
            $b->active_slot_id = in_array($b->status, self::HOLDING, true) ? $b->slot_id : null;
        });
    }

    public static function newReference(): string
    {
        do {
            $ref = 'OCD-'.strtoupper(Str::random(6));
        } while (self::where('reference', $ref)->exists());

        return $ref;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function whatsappDigits(): string
    {
        return preg_replace('/\D/', '', $this->phone);
    }
}
