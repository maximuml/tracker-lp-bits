<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * IP location records (`locations` table) — CRUD + client-side range filtering.
 */
final class LocationRepository
{
    /**
     * @param  array<string, string>  $data
     */
    public function insert(array $data): void
    {
        DB::table('locations')->insert($data);
    }

    /**
     * @param  array<string, string>  $data
     */
    public function updateById(int $id, array $data): void
    {
        DB::table('locations')->where('id', $id)->update($data);
    }

    public function deleteById(int $id): void
    {
        DB::table('locations')->where('id', $id)->delete();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = DB::table('locations')->where('id', $id)->first();

        return $row === null ? null : (array) $row;
    }

    /**
     * Locations overlapping an optional client-side IP range (ints from ip2long).
     */
    public function newRangeQuery(?int $rangeStartIp, ?int $rangeEndIp): Builder
    {
        return DB::table('locations')
            ->when($rangeStartIp !== null && $rangeEndIp !== null, function ($query) use ($rangeStartIp, $rangeEndIp) {
                return $query->whereRaw('INET_ATON(start_ip) <= ? AND INET_ATON(end_ip) >= ?', [$rangeStartIp, $rangeEndIp]);
            });
    }
}
