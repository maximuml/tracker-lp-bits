<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Models\User;

/**
 * ViewModel for the user control panel page.
 *
 * Returned by UsercpPageService::build().
 */
final class UsercpPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>|null  $personal
     * @param  array<string, mixed>|null  $tracker
     * @param  array<string, mixed>|null  $forum
     * @param  array<string, mixed>|null  $security
     * @param  array<string, mixed>|null  $home
     */
    public function __construct(
        public readonly array $lang,
        public readonly array $curUser,
        public readonly User $userInfo,
        public readonly string $siteName,
        public readonly string $action,
        public readonly string $type,
        public readonly string $contentWidth,
        public readonly ?array $personal = null,
        public readonly ?array $tracker = null,
        public readonly ?array $forum = null,
        public readonly ?array $security = null,
        public readonly ?array $home = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lang' => $this->lang,
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
