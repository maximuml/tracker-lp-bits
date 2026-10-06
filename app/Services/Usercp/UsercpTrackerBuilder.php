<?php

declare(strict_types=1);

namespace App\Services\Usercp;

use App\Enums\UserAppendPromotion;
use App\Enums\UserFontsize;
use App\Enums\UserTheme;
use App\Enums\UserTimeType;
use App\Enums\UserTooltip;
use App\Repositories\StyleRepository;
use App\Support\Config\SiteConfig;
use App\Support\Html;
use App\Support\Html\SafeHtml;
use App\Support\Input;
use App\Support\YesNo;
use App\Support\Locale;
use App\Support\Strings;
use App\ViewModels\Search\SearchCategoryTableFactory;
use App\ViewModels\Usercp\UsercpTrackerSection;

/**
 * Builds the usercp "tracker" browse/settings section.
 */
final class UsercpTrackerBuilder
{
    public function __construct(
        private readonly SearchCategoryTableFactory $searchCategoryTableFactory
    ) {}

    /**
     * @param  array<string, mixed>  $curUser
     */
    public function build(array $curUser): UsercpTrackerSection
    {
        $showTooltipSetting = SiteConfig::current()->tweak->enableTooltip();
        $browsecatmode = SiteConfig::current()->main->browseCat(1);

        $notifs = (string) ($curUser['notifs'] ?? '');
        $specialState = 0;
        for ($i = 7; $i >= 0; $i--) {
            if (str_contains($notifs, "[spstate={$i}]")) {
                $specialState = $i;
                break;
            }
        }

        $categoriesTable = $this->searchCategoryTableFactory->create($browsecatmode, 'yes', '/web/torrents?allsec=1&', '', 3, $notifs, ['section_name' => true]);

        $currentTheme = UserTheme::fromStringSafe(is_string($curUser['theme'] ?? null) ? $curUser['theme'] : null)->value;
        $themeOptions = [];
        foreach (UserTheme::cases() as $theme) {
            $themeOptions[$theme->value] = (string) __('legacy/usercp.select_theme_'.$theme->value);
        }

        $stylesheetOptions = [];
        foreach (StyleRepository::all() as $id => $row) {
            $stylesheetOptions[$id] = (string) ($row['name'] ?? $id);
        }

        $currentFolder = Locale::folderFromCookie((string) Input::cookieValue('c_lang_folder', ''), false);
        $siteLanguages = [];
        $currentLangId = 0;
        foreach (Locale::languageList('site_lang', true) as $row) {
            $siteLanguages[(int) $row['id']] = (string) $row['lang_name'];
            if ($row['site_lang_folder'] === $currentFolder) {
                $currentLangId = (int) $row['id'];
            }
        }

        $incldead = 1;
        if (preg_match('/\[incldead=(\d)\]/', $notifs, $m)) {
            $incldead = (int) $m[1];
        }
        $inclbookmarked = 0;
        if (preg_match('/\[inclbookmarked=(\d)\]/', $notifs, $m)) {
            $inclbookmarked = (int) $m[1];
        }

        return new UsercpTrackerSection(
            formId: 'form'.Strings::randomCode(6),
            showEmailNotify: SiteConfig::current()->smtp->emailNotify()
                && SiteConfig::current()->smtp->type() !== 'none',
            pmnotif: str_contains($notifs, '[pm]'),
            emailnotif: str_contains($notifs, '[email]'),
            categoriesTable: $categoriesTable,
            incldead: $incldead,
            specialState: $specialState,
            inclbookmarked: $inclbookmarked,
            promotionOptionsHtml: SafeHtml::fromTrustedHtml(Html::promotionSelection($specialState)),
            stylesheetOptions: $stylesheetOptions,
            currentStylesheet: (int) ($curUser['stylesheet'] ?? 0),
            themeOptions: $themeOptions,
            currentTheme: $currentTheme,
            fontsize: UserFontsize::tryFrom((int) ($curUser['fontsize'] ?? 1))?->stringValue() ?? 'medium',
            langOptions: $siteLanguages,
            currentLangId: $currentLangId,
            pmnum: (int) ($curUser['pmnum'] ?? 0),
            showShoutbox: SiteConfig::current()->main->showShoutbox(),
            sbnum: (int) ($curUser['sbnum'] ?? 0),
            sbrefresh: (int) ($curUser['sbrefresh'] ?? 0),
            showdescription: YesNo::isYes($curUser['showdescription'] ?? null),
            showcomment: YesNo::isYes($curUser['showcomment'] ?? null),
            timetype: UserTimeType::tryFrom((int) ($curUser['timetype'] ?? 1))?->stringValue() ?? 'timealive',
            torrentsperpage: (int) ($curUser['torrentsperpage'] ?? 0),
            tooltip: UserTooltip::tryFrom((int) ($curUser['tooltip'] ?? 2))?->stringValue() ?? 'off',
            appendsticky: YesNo::isYes($curUser['appendsticky'] ?? null),
            appendnew: YesNo::isYes($curUser['appendnew'] ?? null),
            appendpromotion: UserAppendPromotion::tryFrom((int) ($curUser['appendpromotion'] ?? 2))?->stringValue() ?? 'icon',
            appendpicked: YesNo::isYes($curUser['appendpicked'] ?? null),
            dlicon: YesNo::isYes($curUser['dlicon'] ?? null),
            bmicon: YesNo::isYes($curUser['bmicon'] ?? null),
            showcomnum: YesNo::isYes($curUser['showcomnum'] ?? null),
            showlastcom: ! YesNo::isNo($curUser['showlastcom'] ?? null),
            showTooltipSetting: $showTooltipSetting,
        );
    }
}
