<?php

namespace App\Support;

/** Catégories de l'annuaire des lives (l'icône de chaque catégorie est choisie dans l'interface, d'après son code). */
class LiveCategories
{
    private const LIST = [
        'nourriture' => 'Nourriture',
        'mode' => 'Mode et vêtements',
        'beaute' => 'Beauté et soins',
        'tech' => 'Téléphones et électronique',
        'maison' => 'Maison et déco',
        'agriculture' => 'Agriculture et produits locaux',
        'services' => 'Services et business',
        'autre' => 'Autres produits',
    ];

    /** @return array<int, array{code:string, name:string}> */
    public static function all(): array
    {
        return array_map(fn ($code, $name) => ['code' => $code, 'name' => $name], array_keys(self::LIST), self::LIST);
    }

    /** @return array<int, string> */
    public static function codes(): array
    {
        return array_keys(self::LIST);
    }

    public static function name(string $code): string
    {
        return self::LIST[$code] ?? $code;
    }
}
