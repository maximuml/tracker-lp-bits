<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Permission;
use App\Models\News;
use App\Models\Poll;
use App\Models\PollAnswer;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class LogRepository
{
    /** @param  array<int|string, mixed>  $filters */
    public function countSiteLog(array $filters): int
    {
        $query = $this->buildSiteLogQuery($filters);

        return (int) $query->count();
    }

    /**
     * @param  array<int|string, mixed>  $filters
     * @return array<int|string, mixed>
     */
    public function getSiteLog(array $filters, int $offset, int $perPage): array
    {
        return $this->buildSiteLogQuery($filters)
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
    private function buildSiteLogQuery(array $filters): Builder
    {
        $query = DB::table('sitelog');

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

    public function countChronicle(string $queryString): int
    {
        $query = DB::table('chronicle');
        if ($queryString !== '') {
            $query->where('txt', 'like', "%{$queryString}%");
        }

        return (int) $query->count();
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getChronicle(string $queryString, int $offset, int $perPage): array
    {
        $query = DB::table('chronicle');
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
    public function getChronicleById(int $id): ?array
    {
        $row = DB::table('chronicle')->where('id', $id)->first();

        return $row === null ? null : (array) $row;
    }

    public function addChronicle(int $userId, string $txt): void
    {
        DB::table('chronicle')->insert([
            'userid' => $userId,
            'added' => Carbon::now()->toDateTimeString(),
            'txt' => $txt,
        ]);
    }

    public function updateChronicle(int $id, string $txt): int
    {
        return DB::table('chronicle')->where('id', $id)->update(['txt' => $txt]);
    }

    public function deleteChronicle(int $id): int
    {
        return DB::table('chronicle')->where('id', $id)->delete();
    }

    /**
     * @return ?array<int|string, mixed>
     */
    public function getGenericById(string $table, int $id): ?array
    {
        $row = DB::table($table)->where('id', $id)->first();

        return $row === null ? null : (array) $row;
    }

    /** @param  array<int|string, mixed>  $filters */
    public function countNews(array $filters): int
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
    public function getNews(array $filters, int $offset, int $perPage): array
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

    public function getPollCount(): int
    {
        return (int) Poll::query()->count();
    }

    /** @return  array<int|string, mixed> */
    public function getPollsExceptFirst(): array
    {
        $count = $this->getPollCount();
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

    public function deletePoll(int $pollId): void
    {
        PollAnswer::query()->where('pollid', $pollId)->delete();
        Poll::query()->where('id', $pollId)->delete();
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getPollVoteCounts(int $pollId): array
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
