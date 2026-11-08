<?php

namespace Database\Seeders;

use App\Models\LiveSession;
use App\Models\Package;
use App\Models\Project;
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
        // Admin : identifiants issus de l'environnement.
        $admin = User::firstOrNew(['email' => env('ADMIN_EMAIL', 'admin@houseofchallenge.test')]);
        $admin->forceFill([
            'name' => 'Administrateur', 'first_name' => 'Admin', 'last_name' => 'HOC', 'phone_e164' => '+22800000000',
            'country' => 'Togo', 'role' => 'ADMIN', 'is_active' => true, 'email_verified_at' => now(),
            'password' => $admin->exists ? $admin->password : Hash::make(env('ADMIN_PASSWORD', 'Admin#12345')),
        ])->save();

        // Formules (exemples à confirmer : modifiables depuis l'admin).
        $packages = [
            ['code' => 'PASSAGE_1', 'name' => 'Passage unique', 'description' => '1 passage en direct à House of Challenge.', 'price' => 15000, 'credits_included' => 1, 'validity_days' => 90, 'includes_publication' => false, 'publication_days' => null, 'display_order' => 1],
            ['code' => 'PACK_4', 'name' => 'Pack 4 passages', 'description' => '4 passages à utiliser quand vous voulez.', 'price' => 25000, 'credits_included' => 4, 'validity_days' => 180, 'includes_publication' => false, 'publication_days' => null, 'display_order' => 2],
            ['code' => 'ABONNEMENT', 'name' => 'Abonnement visibilité', 'description' => '1 passage + votre projet publié sur le site.', 'price' => 30000, 'credits_included' => 1, 'validity_days' => 30, 'includes_publication' => true, 'publication_days' => 30, 'display_order' => 3],
        ];
        foreach ($packages as $p) {
            Package::firstOrCreate(['code' => $p['code']], $p + ['allowed_modes' => ['SUR_PLACE', 'A_DISTANCE'], 'is_active' => true]);
        }

        foreach (Settings::DEFAULTS as $k => $v) {
            Setting::firstOrCreate(['key' => $k], ['value' => $v]);
        }

        if (app()->environment('production') || env('SEED_DEMO', true) === false) {
            return;
        }

        // Données de démonstration.
        $demo = [
            ['equipe@houseofchallenge.test', 'Interviewer', 'Équipe', 'INTERVIEWER', 'Togo', 'interviewer_hoc'],
            ['aya@houseofchallenge.test', 'Aya', 'Koffi', 'PARTICIPANT', 'Togo', 'aya_koffi'],
            ['kofi@houseofchallenge.test', 'Kofi', 'Mensah', 'PARTICIPANT', 'Bénin', null],
        ];
        foreach ($demo as [$email, $first, $last, $role, $country, $tiktok]) {
            $u = User::firstOrCreate(['email' => $email], [
                'name' => "$first $last", 'first_name' => $first, 'last_name' => $last, 'phone_e164' => '+2289000000'.rand(10, 99),
                'country' => $country, 'tiktok_username' => $tiktok, 'password' => Hash::make('password'),
            ]);
            $u->forceFill(['role' => $role, 'email_verified_at' => now()])->save();
            if ($role === 'PARTICIPANT' && ! $u->projects()->exists()) {
                $u->projects()->create([
                    'title' => "Projet de {$first}", 'slug' => Project::uniqueSlug("Projet de {$first}"), 'sector' => 'Tech', 'stage' => 'DEMARRAGE',
                    'short_pitch' => 'Une idée qui change le quotidien.', 'description' => 'Description détaillée du projet.',
                    'needs' => ['Visibilité'], 'country' => $country,
                ]);
            }
        }

        if (LiveSession::count() === 0) {
            $tz = config('app.display_timezone');
            foreach ([3, 7] as $i => $days) {
                $day = now($tz)->addDays($days)->format('Y-m-d');
                $s = LiveSession::create([
                    'title' => 'House of Challenge — Live #'.($i + 1),
                    'starts_at' => Carbon::createFromFormat('Y-m-d H:i', "$day 19:00", $tz)->utc(),
                    'ends_at' => Carbon::createFromFormat('Y-m-d H:i', "$day 21:00", $tz)->utc(),
                    'accepted_modes' => ['SUR_PLACE', 'A_DISTANCE'], 'slot_minutes' => 15, 'break_minutes' => 0,
                ]);
                app(SlotService::class)->generate($s);
            }
        }
    }
}
