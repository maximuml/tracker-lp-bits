<?php

namespace App\Repositories;

use App\Models\Peer;
use App\Models\Poll;
use App\Models\PollAnswer;
use App\Models\Torrent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class IndexRepository
{
    /**
     * Fetch the latest visible torrents with their category.
     * @param  int  $limit
     * @return  \Illuminate\Database\Eloquent\Collection<int, Torrent>
     */
    public static function getLatestTorrents(int $limit = 9): Collection
    {
        return Torrent::with('basic_category')
            ->where('visible', Torrent::VISIBLE_YES)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Fetch top uploaders ordered by uploaded torrent count.
     * @param  int  $limit
     * @param  ?int  $days
     * @return  \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public static function getTopUploaders(int $limit = 10, ?int $days = null): Collection
    {
        $query = User::query()
            ->whereHas('torrents', function ($query) use ($days) {
                if ($days !== null) {
                    $query->where('added', '>=', Carbon::today()->subDays($days));
                }
            })
            ->withCount(['torrents as count' => function ($query) use ($days) {
                if ($days !== null) {
                    $query->where('added', '>=', Carbon::today()->subDays($days));
                }
            }])
            ->orderByDesc('count')
            ->take($limit);

        return $query->get(['id', 'username']);
    }

    /** @return  array<int|string, mixed> */
    public static function getUserStats(): array
    {
        $cutoffDay = Carbon::now()->subDay()->format('Y-m-d H:i:s');
        $cutoffWeek = Carbon::now()->subWeek()->format('Y-m-d H:i:s');

        return [
            'registered' => User::count(),
            'unverified' => User::where('status', 'pending')->where('enabled', 'yes')->count(),
            'totalonlinetoday' => User::where('last_access', '>=', $cutoffDay)->count(),
            'totalonlineweek' => User::where('last_access', '>=', $cutoffWeek)->count(),
            'vip' => User::where('class', UC_VIP)->count(),
            'donated' => User::where('donor', 'yes')->count(),
            'warned' => User::where('warned', 'yes')->count(),
            'disabled' => User::where('enabled', 'no')->count(),
            'registered_male' => User::where('gender', 'Male')->count(),
            'registered_female' => User::where('gender', 'Female')->count(),
        ];
    }

    /** @return  array<int|string, mixed> */
    public static function getTorrentStats(): array
    {
        $seeders = Peer::where('seeder', Peer::SEEDER_YES)->count();
        $leechers = Peer::where('seeder', Peer::SEEDER_NO)->count();
        $cutoffQuarterHour = Carbon::now()->subSeconds(900)->format('Y-m-d H:i:s');

        return [
            'torrents' => Torrent::count(),
            'dead' => Torrent::where('visible', Torrent::VISIBLE_NO)->count(),
            'seeders' => $seeders,
            'leechers' => $leechers,
            'peers' => $seeders + $leechers,
            'ratio' => $leechers == 0 ? 0 : round($seeders / $leechers * 100),
            'activewebusernow' => User::where('last_access', '>=', $cutoffQuarterHour)->count(),
            'activetrackerusernow' => Peer::distinct()->count('userid'),
            'totaltorrentssize' => (int) Torrent::sum('size'),
            'totaluploaded' => (int) User::sum('uploaded'),
            'totaldownloaded' => (int) User::sum('downloaded'),
            'totaldata' => (int) User::sum('uploaded') + (int) User::sum('downloaded'),
        ];
    }

    /** @return  array<int|string, mixed> */
    public static function getClassStats(): array
    {
        return [
            UC_PEASANT => User::where('class', UC_PEASANT)->count(),
            UC_USER => User::where('class', UC_USER)->count(),
            UC_POWER_USER => User::where('class', UC_POWER_USER)->count(),
            UC_ELITE_USER => User::where('class', UC_ELITE_USER)->count(),
            UC_CRAZY_USER => User::where('class', UC_CRAZY_USER)->count(),
            UC_INSANE_USER => User::where('class', UC_INSANE_USER)->count(),
            UC_VETERAN_USER => User::where('class', UC_VETERAN_USER)->count(),
            UC_EXTREME_USER => User::where('class', UC_EXTREME_USER)->count(),
            UC_ULTIMATE_USER => User::where('class', UC_ULTIMATE_USER)->count(),
            UC_NEXUS_MASTER => User::where('class', UC_NEXUS_MASTER)->count(),
        ];
    }

    /** @return  ?array<int|string, mixed> */
    public static function getCurrentPoll(): ?array
    {
        $poll = Poll::query()->orderByDesc('id')->first();

        return $poll ? $poll->toArray() : null;
    }

    /**
     * @param  int  $pollId
     * @param  int  $userId
     */
    public static function hasVoted(int $pollId, int $userId): bool
    {
        return PollAnswer::where('pollid', $pollId)->where('userid', $userId)->exists();
    }

    /**
     * @param  int  $pollId
     * @param  int  $userId
     */
    public static function getUserVote(int $pollId, int $userId): ?int
    {
        $selection = PollAnswer::where('pollid', $pollId)->where('userid', $userId)->value('selection');

        return $selection === null ? null : (int) $selection;
    }

    /**
     * @param  int  $pollId
     * @param  int  $userId
     * @param  int  $choice
     */
    public static function recordPollVote(int $pollId, int $userId, int $choice): bool
    {
        PollAnswer::create([
            'pollid' => $pollId,
            'userid' => $userId,
            'selection' => $choice,
        ]);

        return true;
    }

    /**
     * @param  int  $pollId
     * @return  array<int|string, mixed>
     */
    public static function getPollResults(int $pollId): array
    {
        $selections = PollAnswer::where('pollid', $pollId)
            ->where('selection', '<=', Poll::MAX_OPTION_INDEX)
            ->pluck('selection')
            ->toArray();

        $counts = [];
        foreach ($selections as $selection) {
            $counts[$selection] = ($counts[$selection] ?? 0) + 1;
        }

        $poll = Poll::find($pollId);
        $items = [];
        for ($i = 0; $i <= Poll::MAX_OPTION_INDEX; ++$i) {
            $option = $poll ? $poll->getAttribute("option{$i}") : '';
            if ($option) {
                $items[] = [
                    'count' => $counts[$i] ?? 0,
                    'option' => (string) $option,
                    'index' => $i,
                ];
            }
        }

        usort($items, function ($a, $b) {
            if ($a['count'] > $b['count']) {
                return -1;
            }
            if ($a['count'] < $b['count']) {
                return 1;
            }

            return 0;
        });

        return $items;
    }
}
