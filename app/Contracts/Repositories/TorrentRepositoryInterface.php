<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface TorrentRepositoryInterface
{
    /**
     * @return mixed
     */
    public function getList(Request $request, User $user, ?string $sectionName = null);

    /**
     * @return mixed
     */
    public function getDetail(int $id, User $user);

    /**
     * @return mixed
     */
    public function getSearchBox(?int $id = null);

    /**
     * @param  list<string>  $columns
     */
    public function findById(int $id, array $columns = ['*']): ?Torrent;

    /**
     * @param  list<string>  $columns
     */
    public function findOrFailById(int $id, array $columns = ['*']): Torrent;

    /**
     * @param  array<string, mixed>  $fields
     */
    public function updateFields(int $id, array $fields): void;

    /**
     * @param  list<int|string>  $posStates
     * @return Collection<int, int>
     */
    public function pluckIdsByPosStates(array $posStates): Collection;

    /**
     * Prepared torrents+categories+extras join for the RSS feed — the
     * caller layers dynamic filters (bookmarks, approval, paid, taxonomy).
     */
    public function newRssBaseQuery(): Builder;

    public function getNameById(int $id): ?string;

    public function getOwnerId(int $id): ?int;

    /**
     * Per-uploader torrent aggregation for the bonus-history report.
     *
     * @return Collection<int, \stdClass>
     */
    public function listUploaderStats(string $startTime, string $endTime, int $minClass, string|Expression $sortColumn, string $sortDirection): Collection;

    /**
     * Latest torrent per owner, keyed by owner id.
     *
     * @param  array<int>  $ownerIds
     * @return Collection<int|string, \stdClass>
     */
    public function listLastTorrentsForOwners(array $ownerIds): Collection;

    public function existsById(int $id): bool;

    /**
     * Per-peer seeding/leeching rows for bonus calculation.
     *
     * @param  array<int|string, mixed>  $userIds
     * @return Collection<int, \stdClass>
     */
    public function listSeedingLeechingForUsers(array $userIds, int|float $minSize): Collection;
}
