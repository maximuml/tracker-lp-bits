<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\CountryRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class CountryRepository implements CountryRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findById(int|string $id): ?array
    {
        $result = DB::table('countries')->where('id', $id)->first();

        return $result ? (array) $result : null;
    }
}
