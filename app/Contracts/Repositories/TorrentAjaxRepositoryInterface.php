<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;

interface TorrentAjaxRepositoryInterface
{
    /**
     * @return Collection<int, \stdClass>
     */
    public function fileList(int $torrentId): Collection;

    /**
     * @return array<string, mixed>
     */
    public function snatchList(int $torrentId): array;

    /**
     * @return list{string, list<string>, list<int>}
     */
    public function searchSuggest(string $searchstr): array;

    /**
     * @return array<string, mixed>
     */
    public function autocompleteTorrents(string $query, ?User $user): array;

    /**
     * @return array<string, mixed>
     */
    public function peerList(int $torrentId, ?User $currentUser = null): array;

    /**
     * @return array<string, mixed>
     */
    public function userTorrentList(int $targetUserId, string $type, int $page, ?User $currentUser = null): array;
}
