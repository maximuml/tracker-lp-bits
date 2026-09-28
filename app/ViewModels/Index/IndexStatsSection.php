<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

/**
 * Tracker statistics section on the index page.
 *
 * `userStats`/`torrentStats`/`labels` are keyed scalar groups consumed
 * directly by the section blade; `classStats` is the ordered list of
 * per-class rows.
 *
 * @phpstan-type UserStatsShape array{activeToday:string,activeThisWeek:string,registered:string,unconfirmed:string,vip:string,vipLabel:\App\Support\Html\SafeHtml,donors:string,donorsLabel:string,warned:string,warnedLabel:string,banned:string,bannedLabel:string,male:string,maleLabel:string,female:string,femaleLabel:string}
 * @phpstan-type TorrentStatsShape array{torrents:string,dead:string,seeders:string,leechers:string,peers:string,ratio:string,activeBrowsing:string,trackerActive:string,totalSize:string,totalUploaded:string,totalDownloaded:string,totalData:string}
 */
final readonly class IndexStatsSection
{
    /**
     * @param  UserStatsShape|null  $userStats
     * @param  TorrentStatsShape|null  $torrentStats
     * @param  list<IndexClassStatRow>  $classStats
     * @param  array<string, string>  $labels
     */
    public function __construct(
        public bool $show = false,
        public string $title = '',
        public ?array $userStats = null,
        public ?array $torrentStats = null,
        public array $classStats = [],
        public ?IndexTodayUsers $todayUsers = null,
        public array $labels = [],
    ) {}
}
