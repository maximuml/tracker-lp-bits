<?php

declare(strict_types=1);

namespace App\ViewModels\Usercp;

use App\Support\Html\SafeHtml;

/**
 * Tracker/browse settings section of the user control panel.
 *
 * `categoriesHtml` and `promotionOptionsHtml` stay trusted HTML:
 * the category checkbox grid comes from the shared `SearchBox`
 * category-table builder and the promotion `<option>`s from
 * `Tag::promotionSelection` — both are trusted internal helpers.
 */
final readonly class UsercpTrackerSection
{
    /**
     * @param  array<string, string>  $themeOptions  value => label
     * @param  array<int, string>  $langOptions  language id => name
     */
    public function __construct(
        public string $formId,
        public bool $showEmailNotify,
        public bool $pmnotif,
        public bool $emailnotif,
        public SafeHtml $categoriesHtml,
        public int $incldead,
        public int $specialState,
        public int $inclbookmarked,
        public SafeHtml $promotionOptionsHtml,
        public array $themeOptions,
        public string $currentTheme,
        public string $fontsize,
        public array $langOptions,
        public int $currentLangId,
        public int $pmnum,
        public bool $showShoutbox,
        public int $sbnum,
        public int $sbrefresh,
        public bool $showdescription,
        public bool $showcomment,
        public string $timetype,
        public int $torrentsperpage,
        public string $tooltip,
        public bool $appendsticky,
        public bool $appendnew,
        public string $appendpromotion,
        public bool $appendpicked,
        public bool $dlicon,
        public bool $bmicon,
        public bool $showcomnum,
        public bool $showlastcom,
        public bool $showTooltipSetting,
    ) {}
}
