<?php

namespace App\Support;

/**
 * Maps the app's 3-letter team/country codes to ISO 3166-1 alpha-2 codes (or a
 * flag-icons subdivision such as "gb-eng"). Mirrors CODE_MAP in
 * resources/js/Composables/useCountryFlags.js - keep the two in step.
 */
final class CountryFlags
{
    private const CODE_MAP = [
        'ALG' => 'dz', 'ARG' => 'ar', 'AUS' => 'au', 'AUT' => 'at', 'BEL' => 'be', 'BFA' => 'bf', 'BHR' => 'bh', 'BOL' => 'bo', 'BRA' => 'br',
        'CAN' => 'ca', 'CHI' => 'cl', 'CHN' => 'cn', 'CIV' => 'ci', 'CMR' => 'cm', 'COL' => 'co', 'CRC' => 'cr', 'CRO' => 'hr',
        'CUB' => 'cu', 'CZE' => 'cz', 'DEU' => 'de', 'DNK' => 'dk', 'ECU' => 'ec', 'EGY' => 'eg', 'ENG' => 'gb-eng', 'ESP' => 'es', 'FIJ' => 'fj',
        'FRA' => 'fr', 'GBR' => 'gb', 'GRE' => 'gr', 'HAI' => 'ht', 'HON' => 'hn', 'IDN' => 'id', 'IRL' => 'ie', 'IRQ' => 'iq',
        'ITA' => 'it', 'JAM' => 'jm', 'JPN' => 'jp', 'KOR' => 'kr', 'KSA' => 'sa', 'KUW' => 'kw', 'MAR' => 'ma', 'MEX' => 'mx',
        'MLI' => 'ml', 'MNE' => 'me', 'MOZ' => 'mz', 'NCL' => 'nc', 'NOR' => 'no', 'NZL' => 'nz', 'OMA' => 'om', 'PAN' => 'pa', 'PAR' => 'py',
        'POR' => 'pt', 'PRK' => 'kp', 'QAT' => 'qa', 'ROU' => 'ro', 'RSA' => 'za', 'SEN' => 'sn', 'SLV' => 'sv', 'SRB' => 'rs',
        'SUI' => 'ch', 'TAN' => 'tz', 'TJK' => 'tj', 'TUN' => 'tn', 'UAE' => 'ae', 'UGA' => 'ug', 'URU' => 'uy', 'USA' => 'us',
        'UZB' => 'uz', 'VEN' => 've', 'VIE' => 'vn', 'YEM' => 'ye', 'ZAM' => 'zm',
    ];

    public static function iso(?string $code): ?string
    {
        return $code ? (self::CODE_MAP[strtoupper(trim($code))] ?? null) : null;
    }
}
