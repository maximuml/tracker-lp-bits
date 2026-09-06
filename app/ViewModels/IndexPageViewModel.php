<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the index page.
 *
 * Returned by IndexPageService::build().
 */
final class IndexPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $news
     * @param  array<string, mixed>  $shoutbox
     * @param  array<string, mixed>  $forumPosts
     * @param  array<string, mixed>  $latestTorrents
     * @param  array<string, mixed>  $topUploaders
     * @param  array<string, mixed>  $polls
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $trackerLoad
     * @param  array<string, mixed>  $disclaimer
     * @param  array<string, mixed>  $browserNote
     */
    public function __construct(
        public readonly array $lang,
        public readonly array $curUser,
        public readonly bool $canNewsManage,
        public readonly bool $canPollManage,
        public readonly bool $canSbManage,
        public readonly bool $canLog,
        public readonly array $news,
        public readonly array $shoutbox,
        public readonly string $extraModules,
        public readonly array $forumPosts,
        public readonly array $latestTorrents,
        public readonly array $topUploaders,
        public readonly array $polls,
        public readonly array $stats,
        public readonly array $trackerLoad,
        public readonly array $disclaimer,
        public readonly array $browserNote,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lang' => $this->lang,
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
