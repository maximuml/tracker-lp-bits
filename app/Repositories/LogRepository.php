<?php

namespace App\Repositories;

use App\Auth\Permission;
use App\Models\News;
use App\Models\Poll;
use App\Models\PollAnswer;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Nexus\Database\NexusDB;

class LogRepository
{
    /** @param  array<int|string, mixed>  $filters */
    public static function countSiteLog(array $filters): int
    {
        $query = self::buildSiteLogQuery($filters);

        return (int) $query->count();
    }

    /**
     * @param  array<int|string, mixed>  $filters
     * @return array<int|string, mixed>
     */
    public static function getSiteLog(array $filters, int $offset, int $perPage): array
    {
        return self::buildSiteLogQuery($filters)
            ->orderBy('added', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  array<int|string, mixed>  $filters
     */
    private static function buildSiteLogQuery(array $filters): Builder
    {
        $query = NexusDB::table('sitelog');

        if (Permission::canViewConfidentialLog()) {
            if (in_array($filters['search'] ?? '', ['mod', 'normal'], true)) {
                $query->where('security_level', $filters['search']);
            }
        } else {
            $query->where('security_level', 'normal');
        }

        if (! empty($filters['query'])) {
            $query->where('txt', 'like', "%{$filters['query']}%");
        }

        return $query;
    }

    public static function countChronicle(string $queryString): int
    {
        $query = NexusDB::table('chronicle');
        if ($queryString !== '') {
            $query->where('txt', 'like', "%{$queryString}%");
        }

        return (int) $query->count();
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function getChronicle(string $queryString, int $offset, int $perPage): array
    {
        $query = NexusDB::table('chronicle');
        if ($queryString !== '') {
            $query->where('txt', 'like', "%{$queryString}%");
        }

        return $query
            ->select('id', 'added', 'txt')
            ->orderBy('added', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return ?array<int|string, mixed>
     */
    public static function getChronicleById(int $id): ?array
    {
        $row = NexusDB::table('chronicle')->where('id', $id)->first();

        return $row === null ? null : (array) $row;
    }

    public static function addChronicle(int $userId, string $txt): void
    {
        NexusDB::table('chronicle')->insert([
            'userid' => $userId,
            'added' => Carbon::now()->toDateTimeString(),
            'txt' => $txt,
        ]);
    }

    public static function updateChronicle(int $id, string $txt): int
    {
        return NexusDB::table('chronicle')->where('id', $id)->update(['txt' => $txt]);
    }

    public static function deleteChronicle(int $id): int
    {
        return NexusDB::table('chronicle')->where('id', $id)->delete();
    }

    /**
     * @return ?array<int|string, mixed>
     */
    public static function getGenericById(string $table, int $id): ?array
    {
        $row = NexusDB::table($table)->where('id', $id)->first();

        return $row === null ? null : (array) $row;
    }

    /** @param  array<int|string, mixed>  $filters */
    public static function countNews(array $filters): int
    {
        $query = News::query();

        if (! empty($filters['query'])) {
            switch ($filters['search'] ?? '') {
                case 'title':
                    $query->where('title', 'like', "%{$filters['query']}%");
                    break;
                case 'body':
                    $query->where('body', 'like', "%{$filters['query']}%");
                    break;
                case 'both':
                    $query->where(function ($q) use ($filters) {
                        $q->where('body', 'like', "%{$filters['query']}%")
                            ->orWhere('title', 'like', "%{$filters['query']}%");
                    });
                    break;
            }
        }

        return (int) $query->count();
    }

    /**
     * @param  array<int|string, mixed>  $filters
     * @return array<int|string, mixed>
     */
    public static function getNews(array $filters, int $offset, int $perPage): array
    {
        $query = News::query();

        if (! empty($filters['query'])) {
            switch ($filters['search'] ?? '') {
                case 'title':
                    $query->where('title', 'like', "%{$filters['query']}%");
                    break;
                case 'body':
                    $query->where('body', 'like', "%{$filters['query']}%");
                    break;
                case 'both':
                    $query->where(function ($q) use ($filters) {
                        $q->where('body', 'like', "%{$filters['query']}%")
                            ->orWhere('title', 'like', "%{$filters['query']}%");
                    });
                    break;
            }
        }

        return $query
            ->select('id', 'added', 'body', 'title')
            ->orderBy('added', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn ($news) => $news->toArray())
            ->all();
    }

    public static function getPollCount(): int
    {
        return (int) Poll::query()->count();
    }

    /** @return  array<int|string, mixed> */
    public static function getPollsExceptFirst(): array
    {
        $count = self::getPollCount();
        if ($count <= 1) {
            return [];
        }

        return Poll::query()
            ->orderBy('id', 'desc')
            ->offset(1)
            ->limit($count - 1)
            ->get()
            ->map(fn ($poll) => $poll->toArray())
            ->all();
    }

    public static function deletePoll(int $pollId): void
    {
        PollAnswer::query()->where('pollid', $pollId)->delete();
        Poll::query()->where('id', $pollId)->delete();
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function getPollVoteCounts(int $pollId): array
    {
        $selections = PollAnswer::query()
            ->where('pollid', $pollId)
            ->where('selection', '<', 20)
            ->pluck('selection')
            ->map(fn ($s) => (int) $s);

        $counts = [];
        foreach ($selections as $selection) {
            $counts[$selection] = ($counts[$selection] ?? 0) + 1;
        }

        return $counts;
    }
}
