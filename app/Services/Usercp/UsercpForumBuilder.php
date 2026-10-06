<?php

declare(strict_types=1);

namespace App\Services\Usercp;

use App\Enums\UserClickTopic;
use App\Support\Config\SiteConfig;
use App\Support\YesNo;
use App\Support\Strings;
use App\ViewModels\Usercp\UsercpForumSection;

/**
 * Builds the usercp "forum" settings section.
 */
final class UsercpForumBuilder
{
    /**
     * @param  array<string, mixed>  $curUser
     */
    public function build(array $curUser): UsercpForumSection
    {
        return new UsercpForumSection(
            formId: 'form'.Strings::randomCode(6),
            showTooltipSetting: SiteConfig::current()->tweak->enableTooltip(),
            topicsPerPage: (int) ($curUser['topicsperpage'] ?? 0),
            postsPerPage: (int) ($curUser['postsperpage'] ?? 0),
            avatars: YesNo::isYes($curUser['avatars'] ?? null),
            signatures: YesNo::isYes($curUser['signatures'] ?? null),
            showLastPost: YesNo::isYes($curUser['showlastpost'] ?? null),
            clicktopic: UserClickTopic::tryFrom((int) ($curUser['clicktopic'] ?? 0))?->stringValue() ?? 'firstpage',
            signature: (string) ($curUser['signature'] ?? ''),
        );
    }
}
