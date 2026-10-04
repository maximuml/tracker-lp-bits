<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

/**
 * User-row access on the announce/scrape hot path — narrow projections,
 * base-builder rows, and the user-row lock used to serialise traffic
 * increments inside the announce transaction.
 */
final class AnnounceUserRepository
{
    /**
     * Hot announce lookup by passkey — narrow column set.
     *
     * @param  array<int, string>  $columns
     */
    public function findByPasskey(string $passkey, array $columns = ['*']): ?User
    {
        /** @var User|null */
        return User::query()
            ->select($columns)
            ->where('passkey', $passkey)
            ->first();
    }

    /**
     * Row lock for update inside the caller's transaction (announce path
     * serialises uploaded/downloaded increments on the user row).
     */
    public function lockById(int $id): ?\stdClass
    {
        /** @var \stdClass|null */
        return User::query()
            ->where('id', $id)
            ->lockForUpdate()
            ->toBase()
            ->first();
    }

    /**
     * Announce-path user write (showclienterror flag, traffic increment
     * map with DB::raw() increments).
     *
     * @param  array<string, mixed>  $fields
     */
    public function updateById(int $id, array $fields): int
    {
        return User::query()->where('id', $id)->update($fields);
    }
}
