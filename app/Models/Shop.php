<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Boutique d'un vendeur : enregistrée une seule fois, réutilisée à chaque réservation. */
class Shop extends Model
{
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(ShopProduct::class)->orderBy('position');
    }

    /** Informations de la boutique au format du formulaire de réservation. */
    public function toForm(): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'city' => $this->city, 'activity_domain' => $this->activity_domain,
            'activity_description' => $this->activity_description, 'tiktok' => $this->tiktok,
            'products' => $this->products->map->only('id', 'name', 'price', 'live_price', 'quantity', 'photo_url')->values(),
        ];
    }
}
