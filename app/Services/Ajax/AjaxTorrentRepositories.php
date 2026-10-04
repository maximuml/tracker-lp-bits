<?php

declare(strict_types=1);

namespace App\Services\Ajax;

use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Repositories\BonusRepository;
use App\Repositories\TorrentModerationRepository;

/**
 * Parameter object bundling the torrent/offer-domain repository
 * dependencies of AjaxService — keeps its constructor within the
 * RepositorySizeTest dependency cap.
 */
final readonly class AjaxTorrentRepositories
{
    public function __construct(
        public TorrentModerationRepository $torrentModeration,
        public BonusRepository $bonus,
        public OfferRepositoryInterface $offers,
    ) {}
}
