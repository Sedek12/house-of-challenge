<?php

namespace Database\Seeders;

use App\Models\LivePost;
use App\Models\LiveSubscription;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Lives TikTok de démonstration pour l'annuaire /lives : abonnements actifs (5 000 FCFA payés), lives en ligne, en attente, terminés, refusé.
 * Relançable à volonté : `php artisan db:seed --class=LiveDemoSeeder` remet les lives « en ligne » pour 7 jours.
 */
class LiveDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'ADMIN')->first();

        // [email, nom affiché, pays, catégorie, pseudo TikTok, statut]
        $lives = [
            ['vendeur1@ocdlive.test', 'Chez Kofi Mode', 'TG', 'mode', 'kofimode', 'EN_LIGNE'],
            ['vendeur2@ocdlive.test', 'Akossiwa Beauté', 'TG', 'beaute', 'akossiwa_beaute', 'EN_LIGNE'],
            ['vendeur3@ocdlive.test', 'Délices de Yawo', 'TG', 'nourriture', 'delices_yawo', 'EN_LIGNE'],
            ['vendeur6@ocdlive.test', 'Ama Créations', 'TG', 'autre', 'ama_creations', 'EN_LIGNE'],
            ['vendeur4@ocdlive.test', 'Aminata Déco', 'CI', 'maison', 'aminata_deco', 'EN_LIGNE'],
            ['vendeur5@ocdlive.test', 'Tech Moussa', 'ML', 'tech', 'techmoussa', 'EN_LIGNE'],
            ['bj1@ocdlive.test', 'Bio Cotonou', 'BJ', 'agriculture', 'biocotonou', 'EN_LIGNE'],
            ['bj2@ocdlive.test', 'Chic Bénin', 'BJ', 'mode', 'chicbenin', 'EN_LIGNE'],
            ['sn1@ocdlive.test', 'Teranga Services', 'SN', 'services', 'teranga_services', 'EN_LIGNE'],
            ['vendeur1@ocdlive.test', 'Chez Kofi Mode (2e live)', 'TG', 'mode', 'kofimode', 'EN_ATTENTE'],
            ['vendeur3@ocdlive.test', 'Délices de Yawo (soir)', 'TG', 'nourriture', 'delices_yawo', 'EN_ATTENTE'],
            ['vendeur2@ocdlive.test', 'Akossiwa Beauté (hier)', 'TG', 'beaute', 'akossiwa_beaute', 'TERMINE'],
            ['bj1@ocdlive.test', 'Bio Cotonou (hier)', 'BJ', 'agriculture', 'biocotonou', 'TERMINE'],
            ['vendeur5@ocdlive.test', 'Tech Moussa (test)', 'ML', 'tech', 'techmoussa', 'REFUSE'],
        ];

        $subs = [];
        foreach ($lives as $i => [$email, $name, $country, $category, $handle, $status]) {
            $user = User::firstOrNew(['email' => $email]);
            if (! $user->exists) {
                $user->forceFill([
                    'name' => $name, 'first_name' => $name, 'last_name' => '', 'phone_e164' => '+2290'.(1000000 + $i * 1111),
                    'country' => match ($country) { 'BJ' => 'Bénin', 'SN' => 'Sénégal', default => 'Togo' },
                    'role' => 'VENDOR', 'is_active' => true, 'email_verified_at' => now(), 'password' => Hash::make('password'),
                ])->save();
            }

            // Un abonnement actif (payé en ligne) par vendeur.
            $sub = $subs[$user->id] ??= tap(LiveSubscription::firstOrCreate(
                ['user_id' => $user->id, 'status' => 'ACTIVE'],
                ['amount' => 5000, 'quota' => 15, 'days' => 30, 'starts_at' => now()->subDays(5), 'ends_at' => now()->addDays(25)],
            ), function (LiveSubscription $s) {
                Payment::firstOrCreate(['provider_ref' => 'fp_'.strtolower($s->reference)], [
                    'live_subscription_id' => $s->id, 'provider' => 'fedapay', 'method' => 'EN_LIGNE',
                    'operator' => ['MTN', 'MOOV', 'TOGOCEL'][$s->id % 3], 'amount' => 5000, 'currency' => 'XOF', 'status' => 'PAYE',
                    'created_at' => $s->starts_at, 'updated_at' => $s->starts_at,
                ]);
            });

            $phone = $user->phone_e164 ?: '+22890000000';
            $base = [
                'user_id' => $user->id, 'live_subscription_id' => $sub->id, 'seller_name' => $name, 'phone' => $phone,
                'country' => $country, 'category' => $category, 'tiktok_handle' => $handle, 'status' => $status,
            ];
            $url = "https://www.tiktok.com/@$handle/live".($i >= 9 ? '?r='.$i : '');
            $extra = match ($status) {
                // Affiché pendant 7 jours : la démo reste visible (la tâche de maintenance ne le retire qu'après).
                'EN_LIGNE' => ['approved_at' => now()->subMinutes(10 + $i * 7), 'live_until' => now()->addDays(7), 'reviewed_by_id' => $admin?->id, 'ended_at' => null, 'reject_reason' => null],
                'EN_ATTENTE' => ['approved_at' => null, 'live_until' => null, 'ended_at' => null, 'reject_reason' => null],
                'TERMINE' => ['approved_at' => now()->subDay()->subHours(2), 'live_until' => now()->subDay(), 'ended_at' => now()->subDay(), 'reviewed_by_id' => $admin?->id, 'reject_reason' => null],
                'REFUSE' => ['approved_at' => null, 'live_until' => null, 'ended_at' => null, 'reviewed_by_id' => $admin?->id, 'reject_reason' => 'Aucun live en cours sur ce lien au moment de la vérification.'],
            };
            LivePost::updateOrCreate(['tiktok_url' => $url], $base + $extra);
        }
    }
}
