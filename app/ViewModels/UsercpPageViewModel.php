<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Models\User;
use App\ViewModels\Usercp\UsercpForumSection;
use App\ViewModels\Usercp\UsercpPersonalSection;
use App\ViewModels\Usercp\UsercpSecuritySection;
use App\ViewModels\Usercp\UsercpTrackerSection;

/**
 * ViewModel for the user control panel page.
 *
 * Returned by UsercpPageService::build().
 */
final class UsercpPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>|null  $home
     */
    public function __construct(
        public readonly array $curUser,
        public readonly User $userInfo,
        public readonly string $siteName,
        public readonly string $action,
        public readonly string $type,
        public readonly string $contentWidth,
        public readonly ?UsercpPersonalSection $personal = null,
        public readonly ?UsercpTrackerSection $tracker = null,
        public readonly ?UsercpForumSection $forum = null,
        public readonly ?UsercpSecuritySection $security = null,
        public readonly ?array $home = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'curUser' => $this->curUser,
            'userInfo' => $this->userInfo,
            'siteName' => $this->siteName,
            'action' => $this->action,
            'type' => $this->type,
            'contentWidth' => $this->contentWidth,
            'personal' => $this->personal,
            'tracker' => $this->tracker,
            'forum' => $this->forum,
            'security' => $this->security,
            'home' => $this->home,
        ];
    }
}
