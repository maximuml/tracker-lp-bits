<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;

interface TorrentDownloadRepositoryInterface
{
    public function getDownloadUrl($id, User|array $user): string;

    public function encryptDownHash($id, $user): string;

    public function decryptDownHash($downHash, $user);

    public function getTrackerReportAuthKey($id, $uid, $initializeIfNotExists = false): string;

    public function checkTrackerReportAuthKey($authKey);

    public function resetTrackerReportAuthKeySecret($uid, $torrentId = 0): string;

    public function addPiecesHashCache(int $torrentId, string $piecesHash): \Redis|int|bool;

    public function delPiecesHashCache(string $piecesHash): \Redis|int|bool;

    public function getPiecesHashCache($piecesHash): array;

    public function loadPiecesHashCache($id = 0): array;

    public function touchCacheStamp(string|int $torrentId, string $field = 'cache_stamp'): void;

    public function resetCacheStamp(string|int $torrentId, string $field = 'cache_stamp'): void;
}
