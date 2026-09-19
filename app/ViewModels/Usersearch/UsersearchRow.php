<?php

declare(strict_types=1);

namespace App\ViewModels\Usersearch;

use App\Support\Html\SafeHtml;

/**
 * One row of the administrative user-search results table.
 */
final class UsersearchRow
{
    public function __construct(
        public readonly int $id,
        public readonly SafeHtml $username,
        public readonly UserRatioCell $ratio,
        public readonly string $ip,
        public readonly bool $ipBanned,
        public readonly string $email,
        public readonly string $added,
        public readonly string $lastAccess,
        public readonly string $status,
        public readonly string $enabled,
        public readonly UserRatioCell $peerRatio,
        public readonly string $peerUploaded,
        public readonly string $peerDownloaded,
        public readonly int $postCount,
        public readonly int $commentCount,
    ) {}
}
