<?php
/** Tanzania's 31 regions (regional capital coordinates) so members' regions can be placed on the map.
 *  Typed regions are matched by name, common alternative spellings and main towns; anything else is left off the map. */
const TZ_REGIONS = [
    'Arusha'            => [-3.3869, 36.6830, ['arusha city']],
    'Dar es Salaam'     => [-6.7924, 39.2083, ['dar', 'dsm', 'dar es salam', 'kinondoni', 'ilala', 'temeke', 'ubungo', 'kigamboni']],
    'Dodoma'            => [-6.1630, 35.7516, []],
    'Geita'             => [-2.8715, 32.2300, []],
    'Iringa'            => [-7.7700, 35.6900, []],
    'Kagera'            => [-1.3318, 31.8120, ['bukoba']],
    'Katavi'            => [-6.3430, 31.0690, ['mpanda']],
    'Kigoma'            => [-4.8769, 29.6266, ['ujiji']],
    'Kilimanjaro'       => [-3.3349, 37.3404, ['moshi']],
    'Lindi'             => [-9.9971, 39.7146, []],
    'Manyara'           => [-4.2100, 35.7500, ['babati']],
    'Mara'              => [-1.5000, 33.8000, ['musoma']],
    'Mbeya'             => [-8.9000, 33.4500, []],
    'Morogoro'          => [-6.8210, 37.6610, ['morogoro town']],
    'Mtwara'            => [-10.2736, 40.1829, []],
    'Mwanza'            => [-2.5164, 32.9175, ['ilemela', 'nyamagana']],
    'Njombe'            => [-9.3420, 34.7680, []],
    'Pwani'             => [-6.7700, 38.9200, ['coast', 'kibaha', 'pwani coast']],
    'Rukwa'             => [-7.9667, 31.6167, ['sumbawanga']],
    'Ruvuma'            => [-10.6833, 35.6500, ['songea']],
    'Shinyanga'         => [-3.6600, 33.4230, []],
    'Simiyu'            => [-2.8000, 33.9800, ['bariadi']],
    'Singida'           => [-4.8160, 34.7500, []],
    'Songwe'            => [-9.1000, 32.9500, ['vwawa', 'mbozi']],
    'Tabora'            => [-5.0167, 32.8000, []],
    'Tanga'             => [-5.0690, 39.0990, []],
    'Mjini Magharibi'   => [-6.1659, 39.2026, ['zanzibar', 'zanzibar city', 'unguja', 'stone town']],
    'Kaskazini Unguja'  => [-5.8800, 39.2900, ['north unguja', 'mkokotoni']],
    'Kusini Unguja'     => [-6.2700, 39.4300, ['south unguja', 'koani']],
    'Kaskazini Pemba'   => [-5.0500, 39.7300, ['north pemba', 'wete']],
    'Kusini Pemba'      => [-5.3000, 39.7500, ['south pemba', 'pemba', 'chake chake']],
];

function region_key(string $name): string {
    $n = mb_strtolower(trim($name));
    $n = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $n);
    $n = (string) preg_replace('/^(mkoa wa|mkoa|region of)\s+/u', '', trim($n));
    $n = (string) preg_replace('/\s+(region|mkoa)$/u', '', $n);
    return trim($n);
}

/** Returns ['name' => canonical region, 'lat' => float, 'lng' => float] or null when the text is not a known Tanzanian region or town. */
function region_place(string $name): ?array {
    static $index = null;
    if ($index === null) {
        $index = [];
        foreach (TZ_REGIONS as $canonical => [$lat, $lng, $aliases]) {
            foreach (array_merge([$canonical], $aliases) as $label) $index[region_key($label)] = [$canonical, $lat, $lng];
        }
    }
    $hit = $index[region_key($name)] ?? null;
    return $hit ? ['name' => $hit[0], 'lat' => $hit[1], 'lng' => $hit[2]] : null;
}
