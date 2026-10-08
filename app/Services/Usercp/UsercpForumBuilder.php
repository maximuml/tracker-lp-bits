<?php

declare(strict_types=1);

namespace App\Services\Usercp;

use App\Enums\UserClickTopic;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Strings;
use App\ViewModels\Usercp\UsercpForumSection;

/**
 * Builds the usercp "forum" settings section.
 */
final class UsercpForumBuilder
{
    public function build(CurrentUser $curUser): UsercpForumSection
    {
        return new UsercpForumSection(
            formId: 'form'.Strings::randomCode(6),
            showTooltipSetting: SiteConfig::current()->tweak->enableTooltip(),
            topicsPerPage: (int) $curUser->value('topicsperpage', 0),
            postsPerPage: (int) $curUser->value('postsperpage', 0),
            avatars: $curUser->yes('avatars'),
            signatures: $curUser->yes('signatures'),
            showLastPost: $curUser->yes('showlastpost'),
            clicktopic: UserClickTopic::tryFrom((int) $curUser->value('clicktopic', 0))?->stringValue() ?? 'firstpage',
            signature: (string) $curUser->value('signature', ''),
        );
    }
}
