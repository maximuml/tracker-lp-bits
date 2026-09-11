<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserFontsize;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;

/**
 * Context bundle for the legacy page header/footer (`PageLayout`).
 *
 * `PageLayout` no longer reads `$GLOBALS` or super-globals directly; the
 * procedural wrappers (`stdhead()` / `stdfoot()` in `include/functions.php`)
 * collect the required values and pass them in this object. This makes
 * the page layout helpers testable and decouples them from global state.
 *
 * `userUpdateSet` is stored as a reference so page code that mutates the
 * legacy `$USERUPDATESET` global between `stdhead()` and `stdfoot()` is
 * still visible to the footer DB write.
 */
final class PageLayoutContext
{
    /** @var array<string, mixed> */
    public array $userUpdateSet;

    public float $startTime = 0.0;

    public bool $offlineMsg = false;

    /**
     * @param  array<string, mixed>|null  $user  Current user row.
     * @param  array<string, string>  $lang  Loaded language strings.
     * @param  LegacyRedisCache|null  $cache  Legacy Redis cache wrapper.
     * @param  array<string, mixed>  $queryName  Legacy SQL debug query list.
     * @param  int  $defaultStylesheet  Default stylesheet id.
     * @param  array<string, mixed>  $userUpdateSet  Mutable user update set.
     */
    public function __construct(
        public ?array $user,
        public array $lang,
        public ?LegacyRedisCache $cache,
        public int $defaultStylesheet,
        public string $langDir,
        public string $siteName,
        public string $slogan,
        public string $logoMain,
        public string $baseUrl,
        public string $siteOnline,
        public string $enableDonation,
        public string $titleKeywordsTweak,
        public string $metaKeywordsTweak,
        public string $metaDescriptionTweak,
        public string $cssDateTweak,
        public int $deleteNotTransferTwoAccount,
        public int $neverDeleteAccount,
        public int $iniUploadMain,
        public string $dateFounded,
        public string $icpLicenseMain,
        public string $addKeyShortcut,
        public array $queryName,
        public string $enableSqlDebugTweak,
        public int $sqlDebugTweak,
        public string $analyticsCodeTweak,
        public string $requestSearch,
        public string $requestSearchArea,
        public string $scriptFileName,
        public string $script,
        public string $enableOffer,
        public ?string $customMenu,
        public string $maxdlSystem,
        public string $whereTweak,
        public string $menuHtml,
        public string $menuSelected,
        public int $adminClass,
        public int $moderatorClass,
        public int $sysopClass,
        public int $vipClass,
        /** @var array<string, mixed> */
        array &$userUpdateSet,
    ) {
        $this->userUpdateSet = &$userUpdateSet;
    }

    public static function fromSupportContext(): self
    {
        $userUpdateSet = &app(UserUpdateBatch::class)->all();

        $script = '';
        if (\function_exists('nexus')) {
            $script = RequestContext::instance()->getScript();
        } else {
            $scriptFile = Input::serverValue('SCRIPT_FILENAME', '');
            $script = basename($scriptFile);
            if (str_contains($script, '.')) {
                $script = strstr($script, '.', true) ?: '';
            }
        }

        $siteConfig = SiteConfig::current();
        $main = $siteConfig->main;
        $tweak = $siteConfig->tweak;
        $account = $siteConfig->account;
        $basic = $siteConfig->basic;

        return new self(
            user: app(CurrentUser::class)->get(),
            lang: app(Language::class)->functions(),
            cache: app(LegacyRedisCache::class),
            defaultStylesheet: $main->defStylesheet(0),
            langDir: Locale::currentLangDir(),
            siteName: $basic->siteName(),
            slogan: $main->slogan(),
            logoMain: $main->logo(),
            baseUrl: $basic->baseUrl(),
            siteOnline: $main->siteOnline(true) ? 'yes' : 'no',
            enableDonation: $main->donation(false) ? 'yes' : 'no',
            titleKeywordsTweak: $tweak->titleKeywords(),
            metaKeywordsTweak: $tweak->metaKeywords(),
            metaDescriptionTweak: $tweak->metaDescription(),
            cssDateTweak: $tweak->cssDate(),
            deleteNotTransferTwoAccount: $account->deleteNoTransferTwo(0),
            neverDeleteAccount: $account->neverdelete(),
            iniUploadMain: $main->iniUpload(0),
            dateFounded: $tweak->dateFounded(),
            icpLicenseMain: $main->icpLicense(),
            addKeyShortcut: (string) app(Globals::class)->get('add_key_shortcut', ''),
            queryName: (array) app(Globals::class)->get('query_name', []),
            enableSqlDebugTweak: $tweak->enableSqlDebug(false) ? 'yes' : 'no',
            sqlDebugTweak: $tweak->sqlDebug(0),
            analyticsCodeTweak: $tweak->analyticsCode(),
            requestSearch: is_scalar(request()->query('search', '')) ? (string) request()->query('search', '') : '',
            requestSearchArea: is_scalar(request()->query('search_area', '')) ? (string) request()->query('search_area', '') : '',
            scriptFileName: Input::serverValue('SCRIPT_FILENAME', ''),
            script: $script,
            enableOffer: $main->showOffer(false) ? 'yes' : '',
            customMenu: null,
            maxdlSystem: $main->maxDlSystem(false) ? 'yes' : '',
            whereTweak: $tweak->where(),
            menuHtml: (string) app(Globals::class)->get('nexus_menu_html', ''),
            menuSelected: (string) app(Globals::class)->get('nexus_menu_selected', ''),
            adminClass: defined('UC_ADMINISTRATOR') ? (int) \constant('UC_ADMINISTRATOR') : 0,
            moderatorClass: defined('UC_MODERATOR') ? (int) \constant('UC_MODERATOR') : 0,
            sysopClass: defined('UC_SYSOP') ? (int) \constant('UC_SYSOP') : 0,
            vipClass: defined('UC_VIP') ? (int) \constant('UC_VIP') : 0,
            userUpdateSet: $userUpdateSet,
        );
    }

    public function isLoggedIn(): bool
    {
        return $this->user !== null && ! empty($this->user['id']);
    }

    public function userClass(): int
    {
        return (int) ($this->user['class'] ?? 0);
    }

    public function userStylesheet(): int
    {
        return (int) ($this->user['stylesheet'] ?? $this->defaultStylesheet);
    }

    public function userFontSize(): ?string
    {
        $value = $this->user['fontsize'] ?? null;
        if ($value === null) {
            return null;
        }

        if ($value instanceof UserFontsize) {
            return $value->stringValue();
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return UserFontsize::from((int) $value)->stringValue();
        }

        return (string) $value;
    }
}
