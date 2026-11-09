<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\Planning;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Espace animateur / hôtesse : lecture seule (A1). Planning du jour et fiche live par vendeur (A6). */
class TeamController extends Controller
{
    public function planning(Request $request)
    {
        return Inertia::render('team/planning', Planning::props($request));
    }

    /** Fiche live : boutique, produits, prix spéciaux, consignes, avec précédent / suivant. */
    public function booking(Booking $booking)
    {
        abort_unless(in_array($booking->status, Planning::STATUSES, true), 404);
        $booking->load('slot', 'package', 'products');

        return Inertia::render('team/booking', [
            'booking' => [
                ...Planning::row($booking), 'city' => $booking->city, 'email' => $booking->email, 'phone' => $booking->phone,
                'activity_domain' => $booking->activity_domain, 'activity_description' => $booking->activity_description,
                'host_notes' => $booking->host_notes, 'duration_minutes' => $booking->package->duration_minutes,
                'products' => $booking->products->map->only('id', 'name', 'price', 'live_price', 'quantity', 'photo_url'),
            ],
            'nav' => Planning::neighbours($booking),
            'can_mark' => auth()->user()->isAdmin(),
        ]);
    }
}
