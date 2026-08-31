<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class FriendsRepository
{
    /**
     * @return array<int|string, mixed>
     */
    public function getFriends(int $userId): array
    {
        return DB::table('friends as f')
            ->leftJoin('users as u', 'f.friendid', '=', 'u.id')
            ->where('f.userid', $userId)
            ->select('f.friendid as id', 'u.last_access', 'u.class', 'u.avatar', 'u.title')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getBlocks(int $userId): array
    {
        return DB::table('blocks')
            ->where('userid', $userId)
            ->select('blockid as id')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function exists(int $userId, string $type, int $targetId): bool
    {
        [$table, $field] = $this->resolveTable($type);

        return DB::table($table)
            ->where('userid', $userId)
            ->where($field, $targetId)
            ->exists();
    }

    public function add(int $userId, string $type, int $targetId): void
    {
        [$table, $field] = $this->resolveTable($type);

        DB::table($table)->insert([
            'userid' => $userId,
            $field => $targetId,
        ]);
    }

    public function delete(int $userId, string $type, int $targetId): int
    {
        [$table, $field] = $this->resolveTable($type);

        return (int) DB::table($table)
            ->where('userid', $userId)
            ->where($field, $targetId)
            ->delete();
    }

    /**
     * @return array<int|string, mixed>
     */
    private function resolveTable(string $type): array
    {
        return match ($type) {
            'block' => ['blocks', 'blockid'],
            default => ['friends', 'friendid'],
        };
    }
}
