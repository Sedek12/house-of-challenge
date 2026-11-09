<?php

namespace Database\Seeders;

use App\Models\LiveSession;
use App\Models\Package;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings;
use App\Services\SlotService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin : identifiants issus de l'environnement ; jamais de valeurs par défaut en production.
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        if (app()->isProduction() && (! $email || ! $password)) {
            throw new \RuntimeException('ADMIN_EMAIL et ADMIN_PASSWORD sont obligatoires en production.');
        }
        if (app()->isProduction() && strlen((string) $password) < 12) {
            throw new \RuntimeException('ADMIN_PASSWORD doit faire au moins 12 caractères (mettez la valeur entre guillemets si elle contient « # »).');
        }
        $admin = User::firstOrNew(['email' => $email ?: 'admin@ocdlive.test']);
        $admin->forceFill([
            'name' => 'Administrateur OCD', 'first_name' => 'Admin', 'last_name' => 'OCD', 'phone_e164' => '+22800000000',
            'country' => 'Togo', 'role' => 'ADMIN', 'is_active' => true, 'email_verified_at' => $admin->email_verified_at ?? now(),
            'password' => $admin->exists ? $admin->password : Hash::make($password ?: 'Admin#12345'),
        ])->save();

        // 6 packages aux avantages cumulatifs (le client en demande jusqu'à 6) : noms, prix et avantages à valider avec OCD, modifiables depuis l'admin.
        $packages = [
            [
                'code' => 'DECOUVERTE',
                'name' => 'Package Découverte',
                'description' => 'Faites vos premiers pas en live devant l\'audience de House of Challenge.',
                'price' => 10000,
                'duration_minutes' => 10,
                'max_products' => 3,
                'includes' => [
                    0 => 'Un passage de 10 minutes en direct',
                    1 => 'Jusqu\'à 3 produits présentés à l\'antenne',
                    2 => 'Vos prix spéciaux live annoncés au public',
                ],
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'ESSENTIEL',
                'name' => 'Package Essentiel',
                'description' => 'Plus de temps, plus de produits, et une chance d\'être republié.',
                'price' => 15000,
                'duration_minutes' => 15,
                'max_products' => 6,
                'includes' => [
                    0 => 'Tout le package Découverte, avec en plus :',
                    1 => 'Un passage de 15 minutes',
                    2 => 'Jusqu\'à 6 produits présentés',
                    3 => 'Votre boutique annoncée à l\'antenne',
                    4 => 'Votre passage peut être republié par House of Challenge (selon la décision de l\'équipe)',
                ],
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'PREMIUM',
                'name' => 'Package Premium',
                'description' => 'Un passage plus long, présenté par l\'hôtesse, et votre vidéo republiée.',
                'price' => 25000,
                'duration_minutes' => 20,
                'max_products' => 10,
                'includes' => [
                    0 => 'Tout le package Essentiel, avec en plus :',
                    1 => 'Un passage de 20 minutes',
                    2 => 'Jusqu\'à 10 produits présentés',
                    3 => 'Présentation par l\'hôtesse',
                    4 => 'Votre passage republié sur la page de House of Challenge pour toucher plus de monde',
                    5 => 'Votre compte TikTok cité dans la publication',
                ],
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'code' => 'PRESTIGE',
                'name' => 'Package Prestige',
                'description' => 'Doublez votre visibilité : plus d\'antenne et une vidéo mise en avant.',
                'price' => 35000,
                'duration_minutes' => 25,
                'max_products' => 10,
                'includes' => [
                    0 => 'Tout le package Premium, avec en plus :',
                    1 => 'Un passage de 25 minutes',
                    2 => 'Rappel de votre boutique en fin de live',
                    3 => 'Un extrait de votre passage mis en avant sur la page de House of Challenge',
                    4 => 'Possibilité de relais de votre passage par Bovann sur ses pages (selon sa décision)',
                ],
                'display_order' => 4,
                'is_active' => true,
            ],
            [
                'code' => 'ELITE',
                'name' => 'Package Élite',
                'description' => 'Passez deux fois à l\'antenne et laissez Bovann relayer votre vidéo.',
                'price' => 50000,
                'duration_minutes' => 30,
                'max_products' => 10,
                'includes' => [
                    0 => 'Tout le package Prestige, avec en plus :',
                    1 => 'Un passage de 30 minutes',
                    2 => 'Une seconde présentation de vos produits pendant le live',
                    3 => 'Relais de votre passage par Bovann sur ses pages, pour des milliers de vues en plus',
                    4 => 'Une copie de votre passage pour vos propres réseaux',
                ],
                'display_order' => 5,
                'is_active' => true,
            ],
            [
                'code' => 'PARTENAIRE',
                'name' => 'Package Partenaire',
                'description' => 'Le maximum de temps, de visibilité et de relais pour votre marque.',
                'price' => 75000,
                'duration_minutes' => 45,
                'max_products' => 10,
                'includes' => [
                    0 => 'Tout le package Élite, avec en plus :',
                    1 => 'Un passage de 45 minutes, le plus long',
                    2 => 'Republication garantie de votre passage sur House of Challenge ET relais par Bovann',
                    3 => 'Votre boutique mise en avant dans plusieurs publications',
                    4 => 'Priorité pour choisir vos prochains créneaux',
                ],
                'display_order' => 6,
                'is_active' => true,
            ],
        ];
        foreach ($packages as $p) {
            Package::firstOrCreate(['code' => $p['code']], $p);
        }

        foreach (Settings::DEFAULTS as $k => $v) {
            Setting::firstOrCreate(['key' => $k], ['value' => $v]);
        }

        if (app()->environment('production') || env('SEED_DEMO', true) === false) {
            return;
        }

        // Données de démonstration : animateur + lives de 19 h à 22 h du lundi au samedi (créneaux de 15 min = 12 passages).
        $u = User::firstOrNew(['email' => 'animateur@ocdlive.test']);
        $u->forceFill([
            'name' => 'Animateur OCD', 'first_name' => 'Animateur', 'last_name' => 'OCD', 'phone_e164' => '+22890000001',
            'country' => 'Togo', 'role' => 'INTERVIEWER', 'is_active' => true, 'email_verified_at' => now(),
            'password' => $u->exists ? $u->password : Hash::make('password'),
        ])->save();

        if (LiveSession::count() === 0) {
            $tz = config('app.display_timezone');
            foreach (range(1, 14) as $i) {
                $day = now($tz)->addDays($i);
                if ($day->dayOfWeekIso === 7) {
                    continue;
                }
                $d = $day->format('Y-m-d');
                $s = LiveSession::create([
                    'title' => 'Live OCD — '.$day->locale('fr')->isoFormat('dddd D MMMM'),
                    'starts_at' => Carbon::createFromFormat('Y-m-d H:i', "$d 19:00", $tz)->utc(),
                    'ends_at' => Carbon::createFromFormat('Y-m-d H:i', "$d 22:00", $tz)->utc(),
                    'accepted_modes' => ['SUR_PLACE', 'A_DISTANCE'], 'slot_minutes' => 15, 'break_minutes' => 0,
                ]);
                app(SlotService::class)->generate($s);
            }
        }

        // Vendeurs, boutiques, réservations et paiements de démonstration (désactivable : SEED_DEMO_DATA=false, utilisé par les tests).
        if (env('SEED_DEMO_DATA', true) !== false) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
