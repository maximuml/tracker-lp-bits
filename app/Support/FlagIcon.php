<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolve a legacy `pic/flag/<name>.gif` flag picture to its vector
 * equivalent in `pic/flags/<iso>.svg` (flag-icons 4x3 set, MIT).
 *
 * The `countries`/`language` tables store arbitrary `flagpic` basenames
 * (e.g. `usa.gif`, `czechrep.gif`) rather than ISO codes, so the map is
 * keyed by basename. Basenames without a vector equivalent — historical
 * flags (`yugoslavia`, `nethantilles`, `ussr`), the fictional
 * `jollyroger`, or any custom flagpic a site ships — fall back to the
 * original `pic/flag/` raster path, which stays installed.
 */
final class FlagIcon
{
    private const SVG_DIR = 'pic/flags/';

    private const GIF_DIR = 'pic/flag/';

    /** @var array<string, string> flagpic basename (no ext) → ISO 3166-1 alpha-2 */
    private const FLAGPIC_TO_ISO = [
        'sweden' => 'se',
        'usa' => 'us',
        'russia' => 'ru',
        'finland' => 'fi',
        'canada' => 'ca',
        'france' => 'fr',
        'germany' => 'de',
        'china' => 'cn',
        'italy' => 'it',
        'denmark' => 'dk',
        'norway' => 'no',
        'uk' => 'gb',
        'ireland' => 'ie',
        'poland' => 'pl',
        'netherlands' => 'nl',
        'belgium' => 'be',
        'japan' => 'jp',
        'brazil' => 'br',
        'argentina' => 'ar',
        'australia' => 'au',
        'newzealand' => 'nz',
        'spain' => 'es',
        'portugal' => 'pt',
        'mexico' => 'mx',
        'singapore' => 'sg',
        'india' => 'in',
        'albania' => 'al',
        'southafrica' => 'za',
        'southkorea' => 'kr',
        'jamaica' => 'jm',
        'luxembourg' => 'lu',
        'belize' => 'bz',
        'algeria' => 'dz',
        'angola' => 'ao',
        'austria' => 'at',
        'westernsamoa' => 'ws',
        'malaysia' => 'my',
        'dominicanrep' => 'do',
        'greece' => 'gr',
        'guatemala' => 'gt',
        'israel' => 'il',
        'pakistan' => 'pk',
        'czechrep' => 'cz',
        'serbia' => 'rs',
        'seychelles' => 'sc',
        'puertorico' => 'pr',
        'chile' => 'cl',
        'cuba' => 'cu',
        'congo' => 'cg',
        'afghanistan' => 'af',
        'turkey' => 'tr',
        'uzbekistan' => 'uz',
        'switzerland' => 'ch',
        'kiribati' => 'ki',
        'philippines' => 'ph',
        'burkinafaso' => 'bf',
        'nigeria' => 'ng',
        'iceland' => 'is',
        'nauru' => 'nr',
        'slovenia' => 'si',
        'turkmenistan' => 'tm',
        'bosniaherzegovina' => 'ba',
        'andorra' => 'ad',
        'lithuania' => 'lt',
        'ukraine' => 'ua',
        'venezuela' => 've',
        'hungary' => 'hu',
        'romania' => 'ro',
        'vanuatu' => 'vu',
        'vietnam' => 'vn',
        'trinidadandtobago' => 'tt',
        'honduras' => 'hn',
        'kyrgyzstan' => 'kg',
        'ecuador' => 'ec',
        'bahamas' => 'bs',
        'peru' => 'pe',
        'cambodia' => 'kh',
        'barbados' => 'bb',
        'bangladesh' => 'bd',
        'laos' => 'la',
        'uruguay' => 'uy',
        'antiguabarbuda' => 'ag',
        'paraguay' => 'py',
        'thailand' => 'th',
        'senegal' => 'sn',
        'togo' => 'tg',
        'northkorea' => 'kp',
        'croatia' => 'hr',
        'estonia' => 'ee',
        'colombia' => 'co',
        'lebanon' => 'lb',
        'latvia' => 'lv',
        'costarica' => 'cr',
        'egypt' => 'eg',
        'bulgaria' => 'bg',
    ];

    /**
     * @param  string|null  $flagpic  stored flagpic value (e.g. `usa.gif`)
     * @return string web-root-relative image path (`pic/flags/us.svg` or `pic/flag/usa.gif`)
     */
    public static function path(?string $flagpic): string
    {
        $base = strtolower(pathinfo((string) $flagpic, PATHINFO_FILENAME));
        $iso = self::FLAGPIC_TO_ISO[$base] ?? null;

        return $iso !== null ? self::SVG_DIR.$iso.'.svg' : self::GIF_DIR.(string) $flagpic;
    }
}
