<?php

declare(strict_types=1);

namespace App\ViewModels\Usercp;

/**
 * Forum settings section of the user control panel.
 *
 * Plain scalars — the section template renders them through
 * `x-settings-*` rows; escaping happens in Blade.
 */
final readonly class UsercpForumSection
{
    public function __construct(
        public string $formId,
        public bool $showTooltipSetting,
        public int $topicsPerPage,
        public int $postsPerPage,
        public bool $avatars,
        public bool $signatures,
        public bool $showLastPost,
        public string $clicktopic,
        public string $signature,
    ) {}
}
