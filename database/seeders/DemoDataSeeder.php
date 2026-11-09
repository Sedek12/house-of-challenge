<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\Slot;
use App\Models\User;
use App\Services\SlotService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Données de démonstration : vendeurs (mot de passe « password »), boutiques, réservations passées et à venir,
 * paiements (en ligne et manuels). Idempotent : ne fait rien si les données existent déjà.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'vendeur1@ocdlive.test')->exists()) {
            return;
        }
        $tz = config('app.display_timezone');

        // Vendeurs, avec leur boutique et leur catalogue.
        $vendors = [
            ['Kofi', 'Mensah', 'vendeur1@ocdlive.test', '+22890110001', 'Togo', 'Lomé', 'Chez Kofi Mode', 'Mode et vêtements', 'Boubous, chemises et tenues en wax.', 'kofimode',
                [['Boubou wax homme', 18000, 15000, 10], ['Chemise bazin', 12000, 9500, 15], ['Ensemble wax femme', 22000, 18000, 8]]],
            ['Akossiwa', 'Adjovi', 'vendeur2@ocdlive.test', '+22891220002', 'Togo', 'Kara', 'Akossiwa Beauté', 'Beauté et cosmétiques', 'Beurre de karité, huiles et soins naturels.', 'akossiwa_beaute',
                [['Beurre de karité 250 g', 3500, 3000, 40], ['Huile de coco 100 ml', 2500, 2000, 50], ['Savon noir', 1500, 1200, 80]]],
            ['Yawo', 'Agbeko', 'vendeur3@ocdlive.test', '+22892330003', 'Togo', 'Lomé', 'Délices de Yawo', 'Alimentation et boissons', 'Jus locaux, épices et gâteaux.', 'delices_yawo',
                [['Jus de bissap 1 L', 1500, 1200, 60], ['Mélange d\'épices', 2000, 1500, 30], ['Gâteau du jour', 5000, 4000, 12]]],
            ['Aminata', 'Diallo', 'vendeur4@ocdlive.test', '+2250707440004', 'Côte d\'Ivoire', 'Abidjan', 'Aminata Déco', 'Maison et décoration', 'Décoration d\'intérieur et paniers tressés.', 'aminata_deco',
                [['Panier tressé', 8000, 6500, 20], ['Coussin bogolan', 6000, 5000, 25]]],
            ['Moussa', 'Traoré', 'vendeur5@ocdlive.test', '+22370550005', 'Mali', 'Bamako', 'Tech Moussa', 'Électronique et accessoires', 'Accessoires téléphone et écouteurs.', 'techmoussa',
                [['Écouteurs sans fil', 9000, 7500, 30], ['Coque téléphone', 2500, 2000, 100], ['Chargeur rapide', 5000, 4000, 40]]],
            ['Ama', 'Koffi', 'vendeur6@ocdlive.test', '+22893660006', 'Togo', 'Lomé', 'Ama Créations', 'Artisanat', 'Bijoux en perles et sacs faits main.', 'ama_creations',
                [['Collier de perles', 4500, 3500, 25], ['Sac en pagne', 11000, 9000, 10]]],
        ];
        $shops = [];
        foreach ($vendors as [$first, $last, $email, $phone, $country, $city, $shop, $domain, $desc, $tiktok, $products]) {
            $u = User::firstOrNew(['email' => $email]);
            $u->forceFill([
                'name' => "$first $last", 'first_name' => $first, 'last_name' => $last, 'phone_e164' => $phone, 'country' => $country, 'city' => $city,
                'role' => 'VENDOR', 'is_active' => true, 'email_verified_at' => now(), 'password' => Hash::make('password'),
            ])->save();
            $s = Shop::create(['user_id' => $u->id, 'name' => $shop, 'city' => $city, 'activity_domain' => $domain, 'activity_description' => $desc, 'tiktok' => $tiktok]);
            foreach ($products as $i => [$n, $price, $live, $qty]) {
                $s->products()->create(['name' => $n, 'price' => $price, 'live_price' => $live, 'quantity' => $qty, 'position' => $i]);
            }
            $shops[] = [$u, $s, $products, $phone, "$first $last"];
        }

        // Lives passés (3 dernières semaines) pour l'historique, des créneaux libres sont déjà créés pour l'avenir par DatabaseSeeder.
        $past = [];
        foreach ([21, 14, 7, 3] as $daysAgo) {
            $day = now($tz)->subDays($daysAgo);
            $d = $day->format('Y-m-d');
            $session = LiveSession::create([
                'title' => 'Live OCD — '.$day->locale('fr')->isoFormat('dddd D MMMM'),
                'starts_at' => Carbon::createFromFormat('Y-m-d H:i', "$d 19:00", $tz)->utc(),
                'ends_at' => Carbon::createFromFormat('Y-m-d H:i', "$d 22:00", $tz)->utc(),
                'accepted_modes' => ['SUR_PLACE', 'A_DISTANCE'], 'slot_minutes' => 15, 'break_minutes' => 0, 'status' => 'TERMINEE',
            ]);
            app(SlotService::class)->generate($session);
            $past[] = $session->slots()->orderBy('position')->get();
        }
        $future = Slot::whereHas('session', fn ($q) => $q->where('status', 'PROGRAMMEE'))->where('starts_at', '>', now())
            ->where('status', 'DISPONIBLE')->orderBy('starts_at')->get();

        $packages = Package::orderBy('display_order')->get()->values();
        $operators = ['MTN', 'MOOV', 'TOGOCEL'];
        $n = 0;

        $make = function (array $v, Slot $slot, string $status, int $pkgIdx, string $payKind, ?string $cancel = null) use ($packages, $operators, &$n) {
            [$user, $shop, $products, $phone, $fullName] = $v;
            $pkg = $packages[$pkgIdx % $packages->count()];
            $remote = $user->country !== 'Togo' || $n % 3 === 0;
            $b = Booking::create([
                'user_id' => $user->id, 'shop_id' => $shop->id, 'package_id' => $pkg->id, 'slot_id' => $slot->id,
                'mode' => $remote ? 'A_DISTANCE' : 'SUR_PLACE', 'status' => $status,
                'full_name' => $fullName, 'phone' => $phone, 'email' => $user->email, 'shop_name' => $shop->name, 'city' => $shop->city,
                'country' => match ($user->country) {
                    'Togo' => 'TG', 'Mali' => 'ML', default => 'CI'
                },
                'activity_domain' => $shop->activity_domain, 'activity_description' => $shop->activity_description, 'tiktok' => $shop->tiktok,
                'host_notes' => 'Merci de présenter les produits dans l\'ordre de la liste.',
                'amount' => $pkg->price, 'consent_at' => $slot->starts_at->copy()->subDays(5),
                'cancelled_at' => $cancel ? $slot->starts_at->copy()->subDays(2) : null, 'cancel_reason' => $cancel,
            ]);
            foreach (array_slice($products, 0, $pkg->max_products) as $i => [$pn, $price, $live, $qty]) {
                $b->products()->create(['name' => $pn, 'price' => $price, 'live_price' => $live, 'quantity' => $qty, 'position' => $i]);
            }
            $slot->update(['status' => in_array($status, Booking::HOLDING, true) && $status !== 'EN_ATTENTE_PAIEMENT' ? 'RESERVE' : 'EN_ATTENTE',
                'hold_expires_at' => $status === 'EN_ATTENTE_PAIEMENT' ? now()->addMinutes(30) : null]);

            if ($payKind === 'online') {
                $paidAt = $slot->starts_at->copy()->subDays(4);
                Payment::create([
                    'booking_id' => $b->id, 'provider' => 'fedapay', 'provider_ref' => 'fp_'.strtolower($b->reference), 'method' => 'EN_LIGNE',
                    'operator' => $operators[$n % 3], 'amount' => $pkg->price, 'currency' => 'XOF', 'status' => 'PAYE',
                    'created_at' => $paidAt, 'updated_at' => $paidAt,
                ]);
            } elseif ($payKind === 'cash' || $payKind === 'transfer') {
                $paidAt = $slot->starts_at->copy()->subDays(3);
                Payment::create([
                    'booking_id' => $b->id, 'provider' => 'manuel', 'provider_ref' => 'manuel_'.strtolower($b->reference),
                    'method' => $payKind === 'cash' ? 'ESPECES' : 'TRANSFERT', 'amount' => $pkg->price, 'currency' => 'XOF', 'status' => 'PAYE',
                    'created_at' => $paidAt, 'updated_at' => $paidAt,
                ]);
            } elseif ($payKind === 'failed') {
                Payment::create([
                    'booking_id' => $b->id, 'provider' => 'fedapay', 'provider_ref' => 'fp_'.strtolower($b->reference), 'method' => 'EN_LIGNE',
                    'operator' => $operators[$n % 3], 'amount' => $pkg->price, 'currency' => 'XOF', 'status' => 'ECHOUE',
                ]);
            } elseif ($payKind === 'pending') {
                Payment::create([
                    'booking_id' => $b->id, 'provider' => 'fedapay', 'provider_ref' => 'fp_'.strtolower($b->reference), 'method' => 'EN_LIGNE',
                    'operator' => $operators[$n % 3], 'amount' => $pkg->price, 'currency' => 'XOF', 'status' => 'EN_ATTENTE',
                ]);
            }
            if ($status === 'ANNULEE') {
                $slot->update(['status' => 'DISPONIBLE']);
            }
            $n++;
        };

        // Historique : réservations effectuées (la plupart), quelques absents, une annulée.
        $pastPlan = [
            [0, 0, 'EFFECTUEE', 1, 'online'], [0, 1, 'EFFECTUEE', 2, 'online'], [0, 2, 'EFFECTUEE', 0, 'cash'], [0, 3, 'ABSENT', 1, 'online'],
            [1, 0, 'EFFECTUEE', 3, 'online'], [1, 1, 'EFFECTUEE', 0, 'transfer'], [1, 2, 'EFFECTUEE', 4, 'online'], [1, 4, 'ANNULEE', 1, 'online'],
            [2, 0, 'EFFECTUEE', 2, 'online'], [2, 1, 'EFFECTUEE', 5, 'online'], [2, 2, 'EFFECTUEE', 1, 'cash'], [2, 3, 'EFFECTUEE', 0, 'online'],
            [3, 0, 'EFFECTUEE', 2, 'online'], [3, 1, 'EFFECTUEE', 1, 'transfer'], [3, 2, 'ABSENT', 0, 'online'],
        ];
        foreach ($pastPlan as $k => [$sess, $pos, $status, $pkgIdx, $pay]) {
            $make($shops[$k % count($shops)], $past[$sess][$pos], $status, $pkgIdx, $pay, $status === 'ANNULEE' ? 'Annulée à la demande du vendeur' : null);
        }

        // À venir : confirmées (payées), une en attente de paiement, une échouée puis annulée.
        $futurePlan = [
            [0, 'CONFIRMEE', 1, 'online'], [3, 'CONFIRMEE', 2, 'online'], [6, 'CONFIRMEE', 0, 'cash'], [9, 'CONFIRMEE', 4, 'online'],
            [14, 'CONFIRMEE', 3, 'transfer'], [18, 'CONFIRMEE', 5, 'online'], [22, 'CONFIRMEE', 1, 'online'],
            [26, 'EN_ATTENTE_PAIEMENT', 2, 'pending'], [30, 'ANNULEE', 1, 'failed'],
        ];
        foreach ($futurePlan as $k => [$idx, $status, $pkgIdx, $pay]) {
            if (! isset($future[$idx])) {
                continue;
            }
            $make($shops[($k + 2) % count($shops)], $future[$idx], $status, $pkgIdx, $pay, $status === 'ANNULEE' ? 'Paiement échoué' : null);
        }
    }
}
