<?php

namespace App\Repositories;

use Nexus\Database\NexusDB;

class FriendsRepository
{
    /**
     * @param  int  $userId
     * @return  array<int|string, mixed>
     */
    public static function getFriends(int $userId): array
    {
        return NexusDB::table('friends as f')
            ->leftJoin('users as u', 'f.friendid', '=', 'u.id')
            ->where('f.userid', $userId)
            ->select('f.friendid as id', 'u.last_access', 'u.class', 'u.avatar', 'u.title')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  int  $userId
     * @return  array<int|string, mixed>
     */
    public static function getBlocks(int $userId): array
    {
        return NexusDB::table('blocks')
            ->where('userid', $userId)
            ->select('blockid as id')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  int  $userId
     * @param  string  $type
     * @param  int  $targetId
     */
    public static function exists(int $userId, string $type, int $targetId): bool
    {
        [$table, $field] = self::resolveTable($type);

        return NexusDB::table($table)
            ->where('userid', $userId)
            ->where($field, $targetId)
            ->exists();
    }

    /**
     * @param  int  $userId
     * @param  string  $type
     * @param  int  $targetId
     */
    public static function add(int $userId, string $type, int $targetId): void
    {
        [$table, $field] = self::resolveTable($type);

        NexusDB::table($table)->insert([
            'userid' => $userId,
            $field => $targetId,
        ]);
    }

    /**
     * @param  int  $userId
     * @param  string  $type
     * @param  int  $targetId
     */
    public static function delete(int $userId, string $type, int $targetId): int
    {
        [$table, $field] = self::resolveTable($type);

        return (int) NexusDB::table($table)
            ->where('userid', $userId)
            ->where($field, $targetId)
            ->delete();
    }

    /**
     * @param  string  $type
     * @return  array<int|string, mixed>
     */
    private static function resolveTable(string $type): array
    {
        return match ($type) {
            'block' => ['blocks', 'blockid'],
            default => ['friends', 'friendid'],
        };
    }
}
