<?php

namespace App\Services;

use App\Models\Setting;

/** Paramètres modifiables depuis l'admin (jamais de valeurs métier en dur). */
class Settings
{
    public const DEFAULTS = [
        'brand_logo_url' => '/images/logo-stacked.webp',
        'brand_color' => '#EE6400',
        'hero_image_url' => '/images/studio.webp',
        'concept_markdown' => '## OCD : Les Opportunités, C\'est Dehors
OCD est un concept de Bovann, porté par son entreprise, Bovann Group Togo. Son message tient en une phrase : les opportunités ne sont pas à la maison.

## Que veut dire « les opportunités, c\'est dehors » ?
Les opportunités ne viennent pas frapper à votre porte pendant que vous restez chez vous. Il faut sortir, chercher, se montrer, oser. C\'est en sortant et en cherchant qu\'on finit, un jour, par trouver. OCD Live donne cette occasion aux vendeurs et aux entrepreneurs : sortir de l\'ombre et se présenter devant toute une audience.

## Bovann, au cœur de House of Challenge
Bovann, panafricain, est le visage de House of Challenge. À chaque édition, il lance un live sur le compte TikTok de House of Challenge, et toute l\'audience se retrouve en direct pour suivre l\'émission. OCD Live est porté par son entreprise, Bovann Group Togo.

## Le concept OCD Live
Les vendeurs et les entrepreneurs viennent présenter leurs produits devant le live, aux côtés de Bovann, pour les faire découvrir à tous ceux qui regardent House of Challenge.

## Pour vendre et gagner de l\'argent
Pendant votre passage, vous montrez vos produits, vous annoncez vos prix spéciaux live et vous vendez en direct à l\'audience. L\'objectif est simple : vous faire connaître, vendre davantage et gagner de l\'argent grâce à la visibilité du live.

## Comment passer à l\'antenne ?
Réservez votre créneau sur ce site, décrivez vos produits à l\'avance et payez en ligne. Le jour du live, l\'équipe prépare votre passage et vous êtes présenté à l\'antenne. La vente se fait ensuite sur TikTok et auprès de vous : ce site sert uniquement à organiser les passages.',
        'studio_address' => 'Studio OCD Live, Lomé, Togo',
        'studio_maps_url' => '',
        'contact_whatsapp' => '22800000000',
        'notification_email' => '',
        'contact_email' => '',
        'tiktok_show_url' => 'https://www.tiktok.com/@houseofchallenge',
        'cancel_deadline_hours' => '48',
        'edit_deadline_hours' => '24',
        'hold_minutes' => '15',
        'onsite_arrival_minutes' => '30',
        // Annuaire des lives TikTok (abonnement, quota, permanences de vérification, durée d'affichage).
        'live_price' => '5000',
        'live_days' => '30',
        'live_quota' => '15',
        'live_per_day' => '1',
        'live_windows' => '08:00-11:00, 18:00-21:00',
        'live_pending_minutes' => '60',
        'live_display_minutes' => '120',
        'live_extend_minutes' => '60',
        'live_reminder_days' => '3',
        'faq_markdown' => "## Comment ça marche ?\nChoisissez un package, un créneau libre, remplissez le formulaire avec vos produits et payez par Flooz ou T-Money. Vous recevez aussitôt votre confirmation et votre lien de suivi.\n\n## Puis-je modifier mes produits ?\nOui, depuis votre lien de suivi, jusqu'à 24 h avant le live.\n\n## Puis-je reporter mon passage ?\nUn report est gratuit si la demande arrive au moins 48 h avant le live : contactez-nous sur WhatsApp.\n\n## Et si je ne me présente pas ?\nLe passage est noté « Absent » et le paiement est conservé.",
        'legal_markdown' => "## Mentions légales\nContenu à compléter par OCD Live (éditeur, hébergeur, contact).\n\n## Politique de confidentialité\nNous collectons uniquement les données nécessaires à votre réservation (nom, téléphone WhatsApp, e-mail, boutique, produits), conformément à la loi togolaise n° 2019-014 sur la protection des données personnelles. Contenu à compléter.",
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = array_merge(self::DEFAULTS, Setting::pluck('value', 'key')->all());
        }

        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key] ?? $default;
    }

    public static function int(string $key): int
    {
        return (int) self::get($key, '0');
    }

    public static function set(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        self::flush();
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
