<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Models\BonusLogs;
use App\Models\User;
use App\Models\UserMeta;
use App\Support\Logger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BonusCalculationRepository extends BaseRepository
{
    public function getCharityReceiverCount(float $ratioCharity): int
    {
        return (int) User::query()
            ->where('enabled', true)
            ->where('downloaded', '>', 10737418240)
            ->whereRaw('? > uploaded/downloaded', [$ratioCharity])
            ->count();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findGiftReceiver(string $username): ?array
    {
        $receiver = User::query()->where('username', $username)->first(['id', 'seedbonus']);

        return $receiver ? $receiver->toArray() : null;
    }

    public function hasChangeUsernameCard(int $userId): bool
    {
        return UserMeta::query()->where('uid', $userId)->where('meta_key', UserMeta::META_KEY_CHANGE_USERNAME)->exists();
    }

    public function hasRainbowIdForever(int $userId): bool
    {
        return UserMeta::query()->where('uid', $userId)->where('meta_key', UserMeta::META_KEY_PERSONALIZED_USERNAME)->whereNull('deadline')->exists();
    }

    public function getCount(string $category = '', int $userId = 0, int $businessType = 0): int
    {
        if ($category == BonusLogs::CATEGORY_COMMON) {
            $query = $this->buildQuery($userId, $businessType);

            return $query->count();
        }
        throw new \InvalidArgumentException("Invalid category: $category");
    }

    /**
     * @return mixed
     */
    public function getList(string $category = '', int $userId = 0, int $businessType = 0, int $page = 1, int $perPage = 50)
    {
        if ($category == BonusLogs::CATEGORY_COMMON) {
            $query = $this->buildQuery($userId, $businessType);

            return $query->orderBy('id', 'desc')->forPage($page, $perPage)->get();
        }
        throw new \InvalidArgumentException("Invalid category: $category");
    }

    /**
     * @return Builder<BonusLogs>
     */
    private function buildQuery(int $userId = 0, int $businessType = 0): Builder
    {
        $query = BonusLogs::query();
        if ($userId > 0) {
            $query->where('uid', $userId);
        }
        if ($businessType > 0) {
            $query->where('business_type', $businessType);
        }

        return $query;
    }

    /**
     * @param  array<int>|null  $torrentIdArr
     * @return array{torrentResult: array<int, array<string, mixed>>, sql: string}
     */
    public function getTorrentRowsForBonusCalculation(int $uid, ?array $torrentIdArr, int|float $minSize): array
    {
        if ($torrentIdArr !== null) {
            if (empty($torrentIdArr)) {
                $torrentIdArr = [-1];
            }
            $torrentQuery = DB::table('torrents')
                ->whereIn('id', $torrentIdArr)
                ->where('size', '>=', $minSize)
                ->select('id', 'added', 'size', 'seeders', DB::raw("'NO_PEER_ID' as peerID"), DB::raw("'' as last_action"), DB::raw("'' as ip"));
        } else {
            $torrentQuery = DB::table('torrents')
                ->leftJoin('peers', 'peers.torrent', '=', 'torrents.id')
                ->where('peers.userid', $uid)
                ->where('peers.seeder', 1)
                ->where('torrents.size', '>', $minSize)
                ->groupBy('torrents.id', 'peers.id')
                ->select('torrents.id', 'torrents.added', 'torrents.size', 'torrents.seeders', 'peers.id as peerID', 'peers.last_action', 'peers.ip');
        }

        return [
            'sql' => $torrentQuery->toSql(),
            'torrentResult' => $torrentQuery->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }

    /**
     * @param  array<int, int>  $torrentIds
     * @return array<int, array<int, int>>
     */
    public function getTagGrouped(array $torrentIds): array
    {
        if (empty($torrentIds)) {
            return [];
        }

        $tagGrouped = [];
        $tagResult = DB::table('torrent_tags')
            ->whereIn('torrent_id', $torrentIds)
            ->select('torrent_id', 'tag_id')
            ->get();
        foreach ($tagResult as $tagItem) {
            $tagGrouped[$tagItem->torrent_id][$tagItem->tag_id] = 1;
        }

        return $tagGrouped;
    }

    public function getMedalAdditionalFactor(int $uid, string $nowStr): float
    {
        $medalQuery = DB::table('medals')
            ->whereIn('id', function ($query) use ($uid, $nowStr) {
                $query->select('medal_id')
                    ->from('user_medals')
                    ->where('uid', $uid)
                    ->where(function ($q) use ($nowStr) {
                        $q->whereNull('expire_at')->orWhere('expire_at', '>', $nowStr);
                    })
                    ->where(function ($q) use ($nowStr) {
                        $q->whereNull('bonus_addition_expire_at')->orWhere('bonus_addition_expire_at', '>', $nowStr);
                    });
            });

        if (DB::connection()->getDriverName() === 'mysql') {
            $medalQuery->selectRaw('round(sum(bonus_addition_factor), 5) as factor');
        } elseif (DB::connection()->getDriverName() === 'pgsql') {
            $medalQuery->selectRaw('round(sum(bonus_addition_factor)::numeric, 5) as factor');
        } else {
            throw new \RuntimeException('Not supported database');
        }

        return floatval($medalQuery->value('factor') ?? 0);
    }

    public function getHaremAddition(int|string $uid): float|int|string
    {
        $addition = DB::table('users')
            ->where('invited_by', $uid)
            ->where('status', UserStatus::CONFIRMED->value)
            ->where('enabled', true)
            ->sum('seed_points_per_hour');

        Logger::writeWithContext("[HAREM_ADDITION], user: $uid, addition: $addition");

        return $addition;
    }
}
