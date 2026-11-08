<?php

namespace App\Services;

use App\Models\Setting;

/** Paramètres modifiables depuis l'admin (jamais de valeurs métier en dur). */
class Settings
{
    public const DEFAULTS = [
        'studio_address' => 'Studio OCD Live, Lomé, Togo',
        'studio_maps_url' => '',
        'contact_whatsapp' => '22800000000',
        'tiktok_show_url' => 'https://www.tiktok.com/@bovann',
        'cancel_deadline_hours' => '48',
        'hold_minutes' => '20',
        'max_future_bookings' => '2',
        'onsite_arrival_minutes' => '30',
        'faq_markdown' => "## Comment ça marche ?\nCréez un compte, présentez votre projet, choisissez votre mode de passage et votre créneau, puis payez.\n\n## Puis-je annuler ?\nOui, jusqu'au délai indiqué dans les conditions : votre crédit vous est alors rendu.",
        'ocd_page_markdown' => "## À propos d'OCD\nContenu à compléter par l'équipe.",
        'cgu_markdown' => "## Conditions générales\nContenu à compléter.",
        'privacy_markdown' => "## Confidentialité\nContenu à compléter.",
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
