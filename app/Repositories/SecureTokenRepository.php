<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Bearer-token rows in caller-selected tables (`sessions`, invite tokens, etc.)
 * — the table name is a runtime parameter, so queries stay here rather than on
 * a fixed-table model.
 */
final class SecureTokenRepository
{
    public function findByDigest(string $table, string $digest): ?\stdClass
    {
        $row = DB::table($table)->where('token_digest', $digest)->first();

        return $row === null ? null : (object) $row;
    }

    public function lockByDigest(string $table, string $digest): ?\stdClass
    {
        $row = DB::table($table)
            ->where('token_digest', $digest)
            ->lockForUpdate()
            ->first();

        return $row === null ? null : (object) $row;
    }

    public function findById(string $table, int $id): ?\stdClass
    {
        $row = DB::table($table)->where('id', $id)->first();

        return $row === null ? null : (object) $row;
    }

    public function findByColumn(string $table, string $column, string $value): ?\stdClass
    {
        $row = DB::table($table)->where($column, $value)->first();

        return $row === null ? null : (object) $row;
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public function updateByIdWhereUnconsumed(string $table, int $id, array $update): void
    {
        DB::table($table)
            ->where('id', $id)
            ->whereNull('consumed_at')
            ->update($update);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function insertGetId(string $table, array $data): int
    {
        return (int) DB::table($table)->insertGetId($data);
    }

    public function revokeUnconsumedForUser(string $table, int $userId): int
    {
        return DB::table($table)
            ->where('user_id', $userId)
            ->whereNull('consumed_at')
            ->where('revoked', 0)
            ->update(['revoked' => 1]);
    }
}
