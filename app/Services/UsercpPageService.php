<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Services\Usercp\UsercpForumBuilder;
use App\Services\Usercp\UsercpHomeBuilder;
use App\Services\Usercp\UsercpPersonalBuilder;
use App\Services\Usercp\UsercpSecurityBuilder;
use App\Services\Usercp\UsercpTrackerBuilder;
use App\Support\CurrentUser;
use App\ViewModels\UsercpPageViewModel;

/**
 * Prepares section data for the user control panel, replacing the legacy
 * usercp_content.php partial with typed Blade-rendered sections.
 *
 * Sections:
 *  - home: dashboard with stats, seed box, tokens, recently read topics
 *  - personal: personal settings form
 *  - tracker: tracker/browse settings form
 *  - forum: forum settings form
 *  - security: security settings form (with optional confirm step)
 *
 * The per-section builders live in App\Services\Usercp\*Builder — this class
 * is only the dispatcher assembling UsercpPageViewModel.
 */
final class UsercpPageService
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly UsercpHomeBuilder $homeBuilder,
        private readonly UsercpPersonalBuilder $personalBuilder,
        private readonly UsercpTrackerBuilder $trackerBuilder,
        private readonly UsercpForumBuilder $forumBuilder,
        private readonly UsercpSecurityBuilder $securityBuilder
    ) {}

    /**
     * Build the data for the requested section.
     */
    public function build(string $action, string $type): UsercpPageViewModel
    {
        $curUser = $this->currentUser;
        $userInfo = (new User)->newFromBuilder($curUser->get() ?? []);
        $siteName = Setting::getSiteName();

        $data = [
            'curUser' => $curUser->get() ?? [],
            'userInfo' => $userInfo,
            'siteName' => $siteName,
            'action' => $action,
            'type' => $type,
            'contentWidth' => '737',
        ];

        switch ($action) {
            case 'personal':
                $data['personal'] = $this->personalBuilder->build($curUser);
                break;
            case 'tracker':
                $data['tracker'] = $this->trackerBuilder->build($curUser);
                break;
            case 'forum':
                $data['forum'] = $this->forumBuilder->build($curUser);
                break;
            case 'security':
                $data['security'] = $this->securityBuilder->build($curUser, $type);
                break;
            default:
                $data['home'] = $this->homeBuilder->build($curUser, $userInfo);
                break;
        }

        return new UsercpPageViewModel(
            curUser: $data['curUser'],
            userInfo: $data['userInfo'],
            siteName: $data['siteName'],
            action: $data['action'],
            type: $data['type'],
            contentWidth: $data['contentWidth'],
            personal: $data['personal'] ?? null,
            tracker: $data['tracker'] ?? null,
            forum: $data['forum'] ?? null,
            security: $data['security'] ?? null,
            home: $data['home'] ?? null,
        );
    }
}
