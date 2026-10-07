<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\IpLogRepository;
use App\Repositories\RequireSeedTorrentRepository;

/**
 * Parameter object bundling the auxiliary query dependencies of
 * AnnounceService — keeps its constructor within the RepositorySizeTest
 * dependency cap.
 */
final readonly class AnnounceRepositories
{
    public function __construct(
        public IpLogRepository $ipLog,
        public RequireSeedTorrentRepository $requireSeedTorrent,
        public TorrentStatsService $torrentStats,
    ) {}
}
