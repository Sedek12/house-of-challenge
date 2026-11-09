<?php

namespace App\Support;

/**
 * Pays proposés (code ISO 3166-1 alpha-2 => nom en français et indicatif téléphonique).
 * Source unique : envoyée au frontend, qui en tire le drapeau (à partir du code) et l'indicatif du numéro.
 */
class Countries
{
    public const HOME = 'TG';

    private const LIST = [
        // Togo en premier, puis l'Afrique par ordre alphabétique, puis le reste du monde.
        'TG' => ['Togo', '+228'],
        'DZ' => ['Algérie', '+213'], 'AO' => ['Angola', '+244'], 'BJ' => ['Bénin', '+229'], 'BW' => ['Botswana', '+267'],
        'BF' => ['Burkina Faso', '+226'], 'BI' => ['Burundi', '+257'], 'CM' => ['Cameroun', '+237'], 'CV' => ['Cap-Vert', '+238'],
        'CF' => ['Centrafrique', '+236'], 'KM' => ['Comores', '+269'], 'CG' => ['Congo', '+242'], 'CD' => ['RD Congo', '+243'],
        'CI' => ['Côte d\'Ivoire', '+225'], 'DJ' => ['Djibouti', '+253'], 'EG' => ['Égypte', '+20'], 'ER' => ['Érythrée', '+291'],
        'SZ' => ['Eswatini', '+268'], 'ET' => ['Éthiopie', '+251'], 'GA' => ['Gabon', '+241'], 'GM' => ['Gambie', '+220'],
        'GH' => ['Ghana', '+233'], 'GN' => ['Guinée', '+224'], 'GW' => ['Guinée-Bissau', '+245'], 'GQ' => ['Guinée équatoriale', '+240'],
        'KE' => ['Kenya', '+254'], 'LS' => ['Lesotho', '+266'], 'LR' => ['Libéria', '+231'], 'LY' => ['Libye', '+218'],
        'MG' => ['Madagascar', '+261'], 'MW' => ['Malawi', '+265'], 'ML' => ['Mali', '+223'], 'MA' => ['Maroc', '+212'],
        'MU' => ['Maurice', '+230'], 'MR' => ['Mauritanie', '+222'], 'MZ' => ['Mozambique', '+258'], 'NA' => ['Namibie', '+264'],
        'NE' => ['Niger', '+227'], 'NG' => ['Nigéria', '+234'], 'UG' => ['Ouganda', '+256'], 'RW' => ['Rwanda', '+250'],
        'ST' => ['Sao Tomé-et-Principe', '+239'], 'SN' => ['Sénégal', '+221'], 'SC' => ['Seychelles', '+248'], 'SL' => ['Sierra Leone', '+232'],
        'SO' => ['Somalie', '+252'], 'SD' => ['Soudan', '+249'], 'SS' => ['Soudan du Sud', '+211'], 'ZA' => ['Afrique du Sud', '+27'],
        'TZ' => ['Tanzanie', '+255'], 'TD' => ['Tchad', '+235'], 'TN' => ['Tunisie', '+216'], 'ZM' => ['Zambie', '+260'],
        'ZW' => ['Zimbabwe', '+263'],
        'DE' => ['Allemagne', '+49'], 'BE' => ['Belgique', '+32'], 'BR' => ['Brésil', '+55'], 'CA' => ['Canada', '+1'],
        'CN' => ['Chine', '+86'], 'ES' => ['Espagne', '+34'], 'US' => ['États-Unis', '+1'], 'FR' => ['France', '+33'],
        'HT' => ['Haïti', '+509'], 'IT' => ['Italie', '+39'], 'LU' => ['Luxembourg', '+352'], 'NL' => ['Pays-Bas', '+31'],
        'PT' => ['Portugal', '+351'], 'GB' => ['Royaume-Uni', '+44'], 'SA' => ['Arabie saoudite', '+966'], 'AE' => ['Émirats arabes unis', '+971'],
        'QA' => ['Qatar', '+974'], 'CH' => ['Suisse', '+41'], 'TR' => ['Turquie', '+90'],
    ];

    /** @return array<int, array{code:string, name:string, dial:string}> */
    public static function all(): array
    {
        $out = [];
        foreach (self::LIST as $code => [$name, $dial]) {
            $out[] = ['code' => $code, 'name' => $name, 'dial' => $dial];
        }

        return $out;
    }

    /** @return array<int, string> */
    public static function codes(): array
    {
        return array_keys(self::LIST);
    }
}
