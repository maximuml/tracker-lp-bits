<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property string $lang_name
 * @property string $flagpic
 * @property int $sub_lang
 * @property int $rule_lang
 * @property int $site_lang
 * @property string $site_lang_folder
 * @property string $trans_state
 */

namespace App\Models;

use App\Support\Cache;
use App\Support\Config\SiteConfig;

/**
 * @property int $id
 * @property string $lang_name
 * @property string $site_lang_folder
 */
class Language extends NexusModel
{
    const DEFAULT_ENABLED = ['en'];

    const TRANS_STATE_UP_TO_DATE = 'up-to-date';

    const TRANS_STATE_OUT_DATE = 'outdate';

    const TRANS_STATE_INCOMPLETE = 'incomplete';

    const TRANS_STATE_NEED_NEW = 'need-new';

    const TRANS_STATE_UNAVAILABLE = 'unavailable';

    const CONFIG = [
        'en' => [
            'lang_name' => 'English',
            'lang_name_cn' => 'English',
            'trans_state' => self::TRANS_STATE_UP_TO_DATE,
        ],
    ];

    /** @var string */
    protected $table = 'language';

    /** @var list<string> */
    protected $fillable = [
        'lang_name', 'site_lang_folder',
    ];

    /** @return  array<int|string, mixed> */
    public static function listAvailable(): array
    {
        return array_keys(self::CONFIG);
    }

    /**
     * @return array<int, string>
     */
    public static function listEnabled(bool $withoutCache = false): array
    {
        $siteConfig = $withoutCache
            ? SiteConfig::fromDb()
            : SiteConfig::current();

        return $siteConfig->main->enabledSiteLanguages(self::DEFAULT_ENABLED);
    }

    public static function updateTransStatus(): void
    {
        foreach (self::CONFIG as $locale => $info) {
            self::query()->where('lang_name', $info['lang_name'])->update([
                'site_lang_folder' => $locale,
                'site_lang' => 1,
                'trans_state' => $info['trans_state'],
            ]);
        }
        Cache::forgetWithLocales('site_lang_lang_list');
    }
}
