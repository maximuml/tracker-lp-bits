<?php

namespace App\Support;

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
     * @param  object|null  $cache  Legacy Redis cache wrapper.
     * @param  array<string, mixed>  $queryName  Legacy SQL debug query list.
     * @param  int  $defaultStylesheet  Default stylesheet id.
     * @param  array<string, mixed>  $userUpdateSet  Mutable user update set.
     */
    public function __construct(
        public ?array $user,
        public array $lang,
        public ?object $cache,
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
        public string $enableSpecial,
        public ?string $customMenu,
        public string $maxdlSystem,
        public string $whereTweak,
        public int $adminClass,
        public int $moderatorClass,
        public int $sysopClass,
        public int $vipClass,
        /** @var array<string, mixed> */
        array &$userUpdateSet,
    ) {
        $this->userUpdateSet = &$userUpdateSet;
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
