<?php

namespace App\Repositories;

use App\Models\ExamUser;
use App\Models\Message;
use App\Models\Peer;
use App\Models\Poll;
use App\Models\PollAnswer;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Ratio;
use App\Support\UserClass;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Nexus\Database\NexusDB;

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

    /**
     * Build data for the personalized dashboard on index2.php.
     *
     * @return array<string, mixed>
     */
    public static function getDashboardData(int $userId): array
    {
        /** @var User|null $user */
        $user = User::query()->find($userId, ['id', 'username', 'uploaded', 'downloaded', 'seedbonus', 'seed_points', 'seeding_torrent_count', 'seeding_torrent_size']);
        if (!$user) {
            return [];
        }

        $uped = (float) $user->uploaded;
        $downed = (float) $user->downloaded;
        $ratioValue = Ratio::share($uped, $downed);
        $ratioColor = is_numeric($ratioValue) ? Ratio::color((float) $ratioValue) : '';
        $ratioHtml = Ratio::userRatioHtml($uped, $downed, '', 'Inf.');

        $activeTorrents = Peer::query()
            ->with('relative_torrent')
            ->where('userid', $userId)
            ->whereIn('seeder', [Peer::SEEDER_YES, Peer::SEEDER_NO])
            ->orderByDesc('last_action')
            ->limit(5)
            ->get();

        $exams = ExamUser::query()
            ->with('exam')
            ->where('uid', $userId)
            ->where('status', ExamUser::STATUS_NORMAL)
            ->orderByDesc('id')
            ->limit(2)
            ->get();

        return [
            'user' => $user,
            'ratio_value' => $ratioValue,
            'ratio_color' => $ratioColor,
            'ratio_html' => $ratioHtml,
            'uploaded' => (int) $user->uploaded,
            'downloaded' => (int) $user->downloaded,
            'bonus' => number_format((float) $user->seedbonus, 1),
            'seed_points' => number_format((float) $user->seed_points, 1),
            'seeding_count' => (int) $user->seeding_torrent_count,
            'seeding_size' => (int) $user->seeding_torrent_size,
            'leeching_count' => Peer::query()->where('userid', $userId)->where('seeder', Peer::SEEDER_NO)->count(),
            'unread_pm_count' => Message::query()->where('receiver', $userId)->where('unread', 'yes')->count(),
            'active_torrents' => $activeTorrents,
            'exams' => $exams,
        ];
    }

    /**
     * Trending torrents by current swarm size.
     *
     * @return Collection<int, Torrent>
     */
    public static function getTrendingTorrents(int $limit = 6): Collection
    {
        return Torrent::query()
            ->with('basic_category')
            ->where('visible', Torrent::VISIBLE_YES)
            ->where('banned', 'no')
            ->orderByDesc('seeders')
            ->orderByDesc('leechers')
            ->limit($limit)
            ->get();
    }

    /**
     * Most snatched torrents by completion count.
     *
     * @return Collection<int, Torrent>
     */
    public static function getMostSnatchedTorrents(int $limit = 6): Collection
    {
        return Torrent::query()
            ->with('basic_category')
            ->where('visible', Torrent::VISIBLE_YES)
            ->where('banned', 'no')
            ->orderByDesc('times_completed')
            ->orderByDesc('seeders')
            ->limit($limit)
            ->get();
    }

    /**
     * Aggregates for the index2.php charts. Cached for one hour.
     *
     * @return array<string, mixed>
     */
    public static function getChartData(): array
    {
        /** @var mixed $cached */
        $cached = NexusDB::cache_get('index2_chart_v2');
        if (is_array($cached)) {
            $classLabels = [];
            $classValues = [];
            foreach ($cached['class_counts'] ?? [] as $class => $count) {
                $classLabels[] = UserClass::name((int) $class, false, false, true);
                $classValues[] = (int) $count;
            }
            $cached['class_labels'] = $classLabels;
            $cached['class_values'] = $classValues;

            return $cached;
        }

        $cutoff = Carbon::now()->subYear()->format('Y-m-d H:i:s');

        /** @var array<int|string, int> $classRows */
        $classRows = User::query()
            ->selectRaw('class, count(*) as count')
            ->groupBy('class')
            ->orderBy('class')
            ->pluck('count', 'class')
            ->toArray();

        $seeders = Peer::query()->where('seeder', Peer::SEEDER_YES)->count();
        $leechers = Peer::query()->where('seeder', Peer::SEEDER_NO)->count();

        /** @var array<string, int> $userMonths */
        $userMonths = User::query()
            ->selectRaw("DATE_FORMAT(added, '%Y-%m') as month, count(*) as count")
            ->where('added', '>=', $cutoff)
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month')
            ->toArray();

        /** @var array<string, int> $torrentMonths */
        $torrentMonths = Torrent::query()
            ->selectRaw("DATE_FORMAT(added, '%Y-%m') as month, count(*) as count")
            ->where('added', '>=', $cutoff)
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month')
            ->toArray();

        $totalUploaded = (int) User::query()->sum('uploaded');
        $totalDownloaded = (int) User::query()->sum('downloaded');

        $result = [
            'class_counts' => $classRows,
            'seeders' => $seeders,
            'leechers' => $leechers,
            'monthly_users' => $userMonths,
            'monthly_torrents' => $torrentMonths,
            'total_uploaded' => $totalUploaded,
            'total_downloaded' => $totalDownloaded,
            'total_users' => User::count(),
            'total_torrents' => Torrent::count(),
            'total_peers' => $seeders + $leechers,
        ];

        NexusDB::cache_put('index2_chart_v2', $result, 3600);

        $classLabels = [];
        $classValues = [];
        foreach ($classRows as $class => $count) {
            $classLabels[] = UserClass::name((int) $class, false, false, true);
            $classValues[] = (int) $count;
        }
        $result['class_labels'] = $classLabels;
        $result['class_values'] = $classValues;

        return $result;
    }
}
