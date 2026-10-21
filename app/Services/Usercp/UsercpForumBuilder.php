<?php

declare(strict_types=1);

namespace App\Services\Usercp;

use App\DTOs\Auth\ActorContext;
use App\Enums\UserClickTopic;
use App\Support\Config\SiteConfig;
use App\Support\Strings;
use App\ViewModels\Usercp\UsercpForumSection;

/**
 * Builds the usercp "forum" settings section.
 */
final class UsercpForumBuilder
{
    public function build(ActorContext $actor): UsercpForumSection
    {
        return new UsercpForumSection(
            formId: 'form'.Strings::randomCode(6),
            showTooltipSetting: SiteConfig::current()->tweak->enableTooltip(),
            topicsPerPage: (int) $actor->value('topicsperpage', 0),
            postsPerPage: (int) $actor->value('postsperpage', 0),
            avatars: $actor->yes('avatars'),
            signatures: $actor->yes('signatures'),
            showLastPost: $actor->yes('showlastpost'),
            clicktopic: UserClickTopic::tryFrom((int) $actor->value('clicktopic', 0))?->stringValue() ?? 'firstpage',
            signature: (string) $actor->value('signature', ''),
        );
    }
}
