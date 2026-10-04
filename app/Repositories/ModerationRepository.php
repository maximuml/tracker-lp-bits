<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ReportType;
use Illuminate\Support\Facades\DB;

class ModerationRepository extends BaseRepository
{
    public function reportExists(int $addedBy, int $reportId, string $type): bool
    {
        return DB::table('reports')
            ->where('addedby', $addedBy)
            ->where('reportid', $reportId)
            ->where('type', ReportType::fromStringSafe($type)->value)
            ->exists();
    }

    /** @param  array<string, mixed>  $data */
    public function createReport(array $data): void
    {
        if (isset($data['type']) && is_string($data['type'])) {
            $data['type'] = ReportType::fromStringSafe($data['type'])->value;
        }
        DB::table('reports')->insert($data);
    }

    /**
     * @param  array<int>  $ids
     */
    public function markReportsDealt(array $ids, int $dealtBy): void
    {
        DB::table('reports')
            ->whereIn('id', $ids)
            ->where('dealtwith', 0)
            ->update(['dealtwith' => 1, 'dealtby' => $dealtBy]);
    }

    /**
     * @param  array<int>  $ids
     */
    public function deleteReports(array $ids): void
    {
        DB::table('reports')->whereIn('id', $ids)->delete();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getForumPost(int $postId): ?array
    {
        $row = (array) DB::table('topics')
            ->leftJoin('posts', 'posts.topicid', '=', 'topics.id')
            ->where('posts.id', $postId)
            ->first(['topics.id AS topicid', 'topics.subject AS subject', 'posts.userid AS postuserid']);

        return empty($row) ? null : $row;
    }

    public function countReports(): int
    {
        return (int) DB::table('reports')->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReports(int $offset, int $limit): array
    {
        return DB::table('reports')
            ->orderBy('dealtwith')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findMatchingBans(int $nip): array
    {
        return DB::table('bans')
            ->where('first', '<=', $nip)
            ->where('last', '>=', $nip)
            ->get(['first', 'last', 'comment'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function deleteDealtWithReportsBefore(string $until): int
    {
        return DB::table('reports')
            ->where('dealtwith', 1)
            ->where('added', '<', $until)
            ->delete();
    }
}
