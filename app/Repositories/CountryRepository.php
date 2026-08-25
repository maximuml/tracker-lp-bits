<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

final class CountryRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int|string $id): ?array
    {
        $result = DB::table('countries')->where('id', $id)->first();

        return $result ? (array) $result : null;
    }
}
