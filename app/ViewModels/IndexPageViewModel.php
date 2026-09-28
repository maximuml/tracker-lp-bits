<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\ViewModels\Index\IndexBrowserNoteSection;
use App\ViewModels\Index\IndexDisclaimerSection;
use App\ViewModels\Index\IndexForumPostsSection;
use App\ViewModels\Index\IndexLatestTorrentsSection;
use App\ViewModels\Index\IndexNewsSection;
use App\ViewModels\Index\IndexPollsSection;
use App\ViewModels\Index\IndexShoutboxSection;
use App\ViewModels\Index\IndexStatsSection;
use App\ViewModels\Index\IndexTopUploadersSection;
use App\ViewModels\Index\IndexTrackerLoadSection;

/**
 * ViewModel for the index page.
 *
 * Returned by IndexPageService::build().
 */
final class IndexPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $curUser
     */
    public function __construct(
        public readonly array $curUser,
        public readonly bool $canNewsManage,
        public readonly bool $canPollManage,
        public readonly bool $canSbManage,
        public readonly bool $canLog,
        public readonly IndexNewsSection $news,
        public readonly IndexShoutboxSection $shoutbox,
        public readonly string $extraModules,
        public readonly IndexForumPostsSection $forumPosts,
        public readonly IndexLatestTorrentsSection $latestTorrents,
        public readonly IndexTopUploadersSection $topUploaders,
        public readonly IndexPollsSection $polls,
        public readonly IndexStatsSection $stats,
        public readonly IndexTrackerLoadSection $trackerLoad,
        public readonly IndexDisclaimerSection $disclaimer,
        public readonly IndexBrowserNoteSection $browserNote,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'curUser' => $this->curUser,
            'canNewsManage' => $this->canNewsManage,
            'canPollManage' => $this->canPollManage,
            'canSbManage' => $this->canSbManage,
            'canLog' => $this->canLog,
            'news' => $this->news,
            'shoutbox' => $this->shoutbox,
            'extraModules' => $this->extraModules,
            'forumPosts' => $this->forumPosts,
            'latestTorrents' => $this->latestTorrents,
            'topUploaders' => $this->topUploaders,
            'polls' => $this->polls,
            'stats' => $this->stats,
            'trackerLoad' => $this->trackerLoad,
            'disclaimer' => $this->disclaimer,
            'browserNote' => $this->browserNote,
        ];
    }
}
