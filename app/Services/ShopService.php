<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Boutiques des vendeurs et catalogue de produits réutilisable. */
class ShopService
{
    /** @param array<string, mixed> $d shop_name, city, activity_domain, activity_description, tiktok */
    public static function createFrom(User $user, array $d): Shop
    {
        return $user->shops()->create([
            'name' => trim($d['shop_name']), 'city' => trim($d['city']), 'activity_domain' => $d['activity_domain'],
            'activity_description' => trim($d['activity_description']), 'tiktok' => $d['tiktok'] ?? null,
        ]);
    }

    /**
     * Le catalogue de la boutique devient la liste des produits de cette réservation :
     * à la prochaine réservation, le vendeur les retrouve (photos comprises) au lieu de les ressaisir.
     */
    public static function syncCatalog(Shop $shop, Booking $booking): void
    {
        DB::transaction(function () use ($shop, $booking) {
            $old = $shop->products()->get();
            $shop->products()->delete();
            foreach ($booking->products()->get() as $i => $p) {
                $shop->products()->create([
                    'name' => $p->name, 'price' => $p->price, 'live_price' => $p->live_price, 'quantity' => $p->quantity,
                    'photo_path' => UploadService::duplicate($p->photo_path), 'position' => $i,
                ]);
            }
            foreach ($old as $o) {
                UploadService::delete($o->photo_path);
            }
        });
    }
}
