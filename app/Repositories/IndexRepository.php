<?php

namespace App\Repositories;

use App\Models\News;
use App\Models\Peer;
use App\Models\Poll;
use App\Models\PollAnswer;
use App\Models\Post;
use App\Models\Torrent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;

class IndexRepository
{
    /**
     * Fetch the latest visible torrents with their category.
     * @param  int  $limit
     * @return  \Illuminate\Database\Eloquent\Collection<int, Torrent>
     */
    public static function getLatestTorrents(int $limit = 9): Collection
    {
        return Cache::remember(
            self::cacheKey('latest_torrents', [(string) $limit]),
            300,
            function () use ($limit) {
                return Torrent::with('basic_category')
                    ->where('visible', Torrent::VISIBLE_YES)
                    ->orderByDesc('id')
                    ->limit($limit)
                    ->get();
            }
        );
    }

    /**
     * @param  array<int, string>  $parts
     */
    private static function cacheKey(string $name, array $parts): string
    {
        return 'index_repo:' . $name . ':' . implode(':', $parts);
    }

    /**
     * Fetch top uploaders ordered by uploaded torrent count.
     * @param  int  $limit
     * @param  ?int  $days
     * @return  \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public static function getTopUploaders(int $limit = 10, ?int $days = null): Collection
    {
        $cacheKey = self::cacheKey('top_uploaders', [(string) $limit, $days === null ? 'all' : (string) $days]);

        return Cache::remember($cacheKey, 300, function () use ($limit, $days) {
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
        });
    }

    /** @return  array<int|string, mixed> */
    public static function getUserStats(): array
    {
        return Cache::remember(self::cacheKey('user_stats', []), 60, function () {
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
        });
    }

    public static function touchLastHome(int $userId): bool
    {
        return (bool) User::query()->where('id', $userId)->update(['last_home' => now()]);
    }

    /** @return  array<int|string, mixed> */
    public static function getTorrentStats(): array
    {
        return Cache::remember(self::cacheKey('torrent_stats', []), 60, function () {
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
        });
    }

    /** @return  array<int|string, mixed> */
    public static function getClassStats(): array
    {
        return Cache::remember(self::cacheKey('class_stats', []), 60, function () {
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
        });
    }

    /** @return  ?array<int|string, mixed> */
    public static function getCurrentPoll(): ?array
    {
        return Cache::remember(self::cacheKey('current_poll', []), 60, function () {
            $poll = Poll::query()->orderByDesc('id')->first();

            return $poll ? $poll->toArray() : null;
        });
    }

    /**
     * @param  int  $pollId
     * @param  int  $userId
     */
    public static function hasVoted(int $pollId, int $userId): bool
    {
        return (bool) Cache::remember(
            self::cacheKey('poll_voted', [(string) $pollId, (string) $userId]),
            60,
            fn () => PollAnswer::where('pollid', $pollId)->where('userid', $userId)->exists()
        );
    }

    /**
     * @param  int  $pollId
     * @param  int  $userId
     */
    public static function getUserVote(int $pollId, int $userId): ?int
    {
        return Cache::remember(
            self::cacheKey('poll_user_vote', [(string) $pollId, (string) $userId]),
            60,
            function () use ($pollId, $userId) {
                $selection = PollAnswer::where('pollid', $pollId)->where('userid', $userId)->value('selection');

                return $selection === null ? null : (int) $selection;
            }
        );
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

        Cache::forget(self::cacheKey('poll_voted', [(string) $pollId, (string) $userId]));
        Cache::forget(self::cacheKey('poll_user_vote', [(string) $pollId, (string) $userId]));
        Cache::forget(self::cacheKey('poll_results', [(string) $pollId]));

        return true;
    }

    /**
     * @param  int  $pollId
     * @return  array<int|string, mixed>
     */
    public static function getPollResults(int $pollId): array
    {
        return Cache::remember(
            self::cacheKey('poll_results', [(string) $pollId]),
            300,
            function () use ($pollId) {
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
        );
    }

    /**
     * @return  array<int, array<string, mixed>>
     */
    public static function getLatestNews(int $limit): array
    {
        return Cache::remember(
            self::cacheKey('latest_news', [(string) $limit]),
            300,
            function () use ($limit) {
                return News::query()
                    ->orderByDesc('added')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($news) => $news->toArray())
                    ->all();
            }
        );
    }

    /**
     * @return  array<int, array<string, mixed>>
     */
    public static function getLatestForumPosts(int $limit, int $minClassRead): array
    {
        return Cache::remember(
            self::cacheKey('latest_forum_posts', [(string) $limit, (string) $minClassRead]),
            60,
            function () use ($limit, $minClassRead) {
                return Post::query()
                    ->join('topics', 'posts.topicid', '=', 'topics.id')
                    ->join('forums', 'topics.forumid', '=', 'forums.id')
                    ->where('forums.minclassread', '<=', $minClassRead)
                    ->orderByDesc('posts.id')
                    ->limit($limit)
                    ->get([
                        'posts.id as pid',
                        'posts.userid as userpost',
                        'posts.added',
                        'topics.id as tid',
                        'topics.subject',
                        'topics.forumid',
                        'topics.views',
                        'forums.name',
                    ])
                    ->toArray();
            }
        );
    }
}
