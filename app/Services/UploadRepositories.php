<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\TorrentDownloadRepositoryInterface;
use App\Repositories\CategoryRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\TorrentUploadRepository;

/**
 * Repository fan-out for UploadService — keeps the service ctor under
 * the dep cap (ADR: param-object convention, same as
 * HousekeepingRepositories / RegistrationRepositories).
 */
final readonly class UploadRepositories
{
    public function __construct(
        public TorrentDownloadRepositoryInterface $torrentDownload,
        public TorrentUploadRepository $torrentUpload,
        public CategoryRepository $category,
        public TorrentRepository $torrent,
    ) {}
}
