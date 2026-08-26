<?php

namespace App\Support;

use App\Support\Cache\LegacyRedisCache;
use Nexus\Nexus;

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
        $userUpdateSet = &SupportContext::getUserUpdateSet();

        $script = '';
        if (\function_exists('nexus')) {
            $script = Nexus::instance()->getScript();
        } else {
            $scriptFile = SupportContext::getServerValue('SCRIPT_FILENAME', '');
            $script = basename($scriptFile);
            if (str_contains($script, '.')) {
                $script = strstr($script, '.', true);
            }
        }

        return new self(
            user: app(CurrentUser::class)->get(),
            lang: app(Language::class)->functions(),
            cache: app(LegacyRedisCache::class),
            defaultStylesheet: (int) app(Globals::class)->get('defcss', 0),
            langDir: (string) app(Globals::class)->get('CURLANGDIR', ''),
            siteName: (string) app(Globals::class)->get('SITENAME', ''),
            slogan: (string) app(Globals::class)->get('SLOGAN', ''),
            logoMain: (string) app(Globals::class)->get('logo_main', ''),
            baseUrl: (string) app(Globals::class)->get('BASEURL', ''),
            siteOnline: (string) app(Globals::class)->get('SITE_ONLINE', 'yes'),
            enableDonation: (string) app(Globals::class)->get('enabledonation', 'no'),
            titleKeywordsTweak: (string) app(Globals::class)->get('titlekeywords_tweak', ''),
            metaKeywordsTweak: (string) app(Globals::class)->get('metakeywords_tweak', ''),
            metaDescriptionTweak: (string) app(Globals::class)->get('metadescription_tweak', ''),
            cssDateTweak: (string) app(Globals::class)->get('cssdate_tweak', ''),
            deleteNotTransferTwoAccount: (int) app(Globals::class)->get('deletenotransfertwo_account', 0),
            neverDeleteAccount: (int) app(Globals::class)->get('neverdelete_account', 0),
            iniUploadMain: (int) app(Globals::class)->get('iniupload_main', 0),
            dateFounded: (string) app(Globals::class)->get('datefounded', ''),
            icpLicenseMain: (string) app(Globals::class)->get('icplicense_main', ''),
            addKeyShortcut: (string) app(Globals::class)->get('add_key_shortcut', ''),
            queryName: (array) app(Globals::class)->get('query_name', []),
            enableSqlDebugTweak: (string) app(Globals::class)->get('enablesqldebug_tweak', 'no'),
            sqlDebugTweak: (int) app(Globals::class)->get('sqldebug_tweak', 0),
            analyticsCodeTweak: (string) app(Globals::class)->get('analyticscode_tweak', ''),
            requestSearch: is_scalar(SupportContext::getQuery('search', '')) ? (string) SupportContext::getQuery('search', '') : '',
            requestSearchArea: is_scalar(SupportContext::getQuery('search_area', '')) ? (string) SupportContext::getQuery('search_area', '') : '',
            scriptFileName: SupportContext::getServerValue('SCRIPT_FILENAME', ''),
            script: $script,
            enableOffer: (string) app(Globals::class)->get('enableoffer', ''),
            customMenu: (string) Hooks::applyFilter('nexus_menu') ?: null,
            maxdlSystem: (string) app(Globals::class)->get('maxdlsystem', ''),
            whereTweak: (string) app(Globals::class)->get('where_tweak', ''),
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
        return $this->user['fontsize'] ?? null;
    }
}
