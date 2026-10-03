<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

final class BitbucketRepository extends BaseRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function insert(array $data): void
    {
        DB::table('bitbucket')->insert($data);
    }

    /**
     * @param  array<int, string>  $columns
     */
    public function findColumnsById(int $id, array $columns): ?\stdClass
    {
        /** @var \stdClass|null $row */
        $row = DB::table('bitbucket')->where('id', $id)->first($columns);

        return $row;
    }

    public function deleteById(int $id): void
    {
        DB::table('bitbucket')->where('id', $id)->delete();
    }
}
