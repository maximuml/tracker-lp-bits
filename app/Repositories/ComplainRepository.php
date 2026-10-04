<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ComplainRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Abuse-report repository: `complains` / `complain_replies` tables.
 */
final class ComplainRepository implements ComplainRepositoryInterface
{
    public function existsByIdAndUuid(int $id, string $uuid): bool
    {
        return DB::table('complains')->where('id', $id)->where('uuid', $uuid)->exists();
    }

    /** @return list<array<string, mixed>> */
    public function listPending(): array
    {
        return array_values(DB::table('complains')
            ->where('answered', 0)
            ->orderByDesc('id')
            ->get(['added', 'uuid', 'email'])
            ->map(fn ($r): array => (array) $r)
            ->all());
    }

    public function countAnswered(): int
    {
        return (int) DB::table('complains')->where('answered', 1)->count();
    }

    /** @return list<array<string, mixed>> */
    public function listAnswered(int $offset, int $limit): array
    {
        return array_values(DB::table('complains')
            ->where('answered', 1)
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get(['added', 'uuid', 'email'])
            ->map(fn ($r): array => (array) $r)
            ->all());
    }

    /** @return array<string, mixed>|null */
    public function findByUuid(string $uuid): ?array
    {
        $row = DB::table('complains')->where('uuid', $uuid)->first();

        return $row === null ? null : (array) $row;
    }

    /** @return list<array<string, mixed>> */
    public function listReplies(int $complainId): array
    {
        return array_values(DB::table('complain_replies')
            ->where('complain', $complainId)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($r): array => (array) $r)
            ->all());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function insertComplain(array $data): int
    {
        return (int) DB::table('complains')->insertGetId($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function insertReply(array $data): void
    {
        DB::table('complain_replies')->insert($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateById(int $id, array $data): void
    {
        DB::table('complains')->where('id', $id)->update($data);
    }

    public function getUuidById(int $id): ?string
    {
        /** @var string|null */
        return DB::table('complains')->where('id', $id)->value('uuid');
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        /** @var array<string, mixed>|null */
        $row = DB::table('complains')->where('id', $id)->first();

        return $row === null ? null : (array) $row;
    }
}
