<?php

namespace App\Console\Commands;

use App\Models\LivePost;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Simulation de l'annuaire des lives (jamais en production) : des vendeurs de démonstration « en direct »,
 * et une demande en attente pour voir la modération. Tout se retire avec --supprimer.
 */
class DemoLives extends Command
{
    protected $signature = 'hoc:demo-lives {--supprimer : retire tous les vendeurs et lives de démonstration} {--heures=12 : durée d\'affichage des lives de démonstration}';

    protected $description = 'Crée (ou retire) des lives de démonstration pour voir l\'annuaire des lives en action';

    private const DOMAIN = '@demo.ocdlive.test';

    /** Vendeurs fictifs : nom, pays, catégorie, compte TikTok fictif, en direct depuis (minutes). */
    private const VENDORS = [
        ['Chez Maman Afi', 'TG', 'nourriture', 'chez_maman_afi_demo', 12],
        ['Wax Élégance', 'BJ', 'mode', 'wax_elegance_demo', 35],
        ['Ablavi Cosmétiques', 'CI', 'beaute', 'ablavi_cosmetiques_demo', 5],
        ['Kodjo Phone', 'TG', 'tech', 'kodjo_phone_demo', 48],
        ['Ferme Agbo', 'CM', 'agriculture', 'ferme_agbo_demo', 20],
        ['Déco Kara', 'TG', 'maison', 'deco_kara_demo', 64],
        ['Sika Services', 'BF', 'services', 'sika_services_demo', 9],
        ['Boutique Yawa', 'SN', 'autre', 'boutique_yawa_demo', 27],
    ];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refusé : la simulation ne s\'utilise jamais en production.');

            return self::FAILURE;
        }

        $removed = $this->purge();
        if ($this->option('supprimer')) {
            $this->info("Simulation retirée ({$removed} vendeur(s) de démonstration supprimé(s)).");

            return self::SUCCESS;
        }

        $hours = max(1, (int) $this->option('heures'));
        foreach (self::VENDORS as $i => [$name, $country, $category, $handle, $since]) {
            $u = $this->vendor($name, $country, $i);
            $sub = $u->liveSubscriptions()->create([
                'status' => 'ACTIVE', 'amount' => 5000, 'quota' => 15, 'days' => 30, 'starts_at' => now(), 'ends_at' => now()->addDays(30),
            ]);
            $u->livePosts()->create([
                'live_subscription_id' => $sub->id, 'seller_name' => $name, 'phone' => $u->phone_e164, 'country' => $country, 'category' => $category,
                'tiktok_url' => "https://www.tiktok.com/@{$handle}/live", 'tiktok_handle' => $handle,
                'status' => 'EN_LIGNE', 'approved_at' => now()->subMinutes($since), 'live_until' => now()->addHours($hours),
            ]);
        }

        // Une demande en attente : à valider ou refuser dans Admin → Lives à valider.
        $u = $this->vendor('Tissus Akossiwa', 'TG', count(self::VENDORS));
        $sub = $u->liveSubscriptions()->create(['status' => 'ACTIVE', 'amount' => 5000, 'quota' => 15, 'days' => 30, 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);
        $u->livePosts()->create([
            'live_subscription_id' => $sub->id, 'seller_name' => 'Tissus Akossiwa', 'phone' => $u->phone_e164, 'country' => 'TG', 'category' => 'mode',
            'tiktok_url' => 'https://www.tiktok.com/@tissus_akossiwa_demo/live', 'tiktok_handle' => 'tissus_akossiwa_demo', 'status' => 'EN_ATTENTE',
        ]);

        $this->info(count(self::VENDORS)." lives de démonstration affichés pendant {$hours} h, et 1 demande en attente de validation.");
        $this->line('Page publique : '.route('lives'));
        $this->line('Modération (administrateur) : '.route('admin.lives'));
        $this->line('Vendeur de démonstration : '.'vendeur0'.self::DOMAIN.' / mot de passe : demo-ocd-live');
        $this->line('Pour tout retirer : php artisan hoc:demo-lives --supprimer');

        return self::SUCCESS;
    }

    private function vendor(string $name, string $country, int $i): User
    {
        $u = new User(['name' => $name, 'email' => "vendeur{$i}".self::DOMAIN, 'password' => Hash::make('demo-ocd-live')]);
        $u->forceFill(['role' => 'VENDOR', 'is_active' => true, 'country' => $country, 'phone_e164' => '+228900000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'email_verified_at' => now(), 'remember_token' => Str::random(10)])->save();

        return $u;
    }

    /** Supprime les vendeurs de démonstration (leurs abonnements et lives partent avec eux). */
    private function purge(): int
    {
        $ids = User::where('email', 'like', '%'.self::DOMAIN)->pluck('id');
        LivePost::whereIn('user_id', $ids)->delete();

        return User::whereIn('id', $ids)->delete();
    }
}
