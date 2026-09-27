<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;

interface TorrentDownloadRepositoryInterface
{
    /**
     * @param  mixed  $id
     * @param  array<int|string, mixed>|User  $user
     */
    public function getDownloadUrl($id, User|array $user): string;

    /**
     * @param  mixed  $id
     * @param  mixed  $user
     */
    public function encryptDownHash($id, $user): string;

    /**
     * @param  mixed  $downHash
     * @param  mixed  $user
     * @return array<int|string, mixed>
     */
    public function decryptDownHash($downHash, $user);

    /**
     * @param  mixed  $authKey
     * @return array<int|string, mixed>
     */
    public function checkTrackerReportAuthKey($authKey);

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     */
    public function resetTrackerReportAuthKeySecret($uid, $torrentId = 0): string;

    public function addPiecesHashCache(int $torrentId, string $piecesHash): \Redis|int|bool;

    public function delPiecesHashCache(string $piecesHash): \Redis|int|bool;

    /**
     * @param  mixed  $piecesHash
     * @return array<int|string, mixed>
     */
    public function getPiecesHashCache($piecesHash): array;

    /**
     * @param  mixed  $id
     * @return array<int|string, mixed>
     */
    public function loadPiecesHashCache($id = 0): array;

    public function touchCacheStamp(string|int $torrentId, string $field = 'cache_stamp'): void;

    public function resetCacheStamp(string|int $torrentId, string $field = 'cache_stamp'): void;
}
