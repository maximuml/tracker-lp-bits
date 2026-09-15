<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Cache;
use Illuminate\Support\Facades\DB;

class OverforumRepository extends BaseRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOverforums(): array
    {
        return DB::table('overforums')
            ->orderBy('sort')
            ->get(['id', 'name'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function deleteOverforum(int $id): void
    {
        DB::table('overforums')->where('id', $id)->delete();
        $this->clearOverforumCache();
    }

    /** @param  array<string, mixed>  $data */
    public function updateOverforum(int $id, array $data): void
    {
        DB::table('overforums')->where('id', $id)->update($data);
        $this->clearOverforumCache();
    }

    /** @param  array<string, mixed>  $data */
    public function createOverforum(array $data): void
    {
        DB::table('overforums')->insert($data);
        $this->clearOverforumCache();
    }

    public function getMaxOverforumSort(): int
    {
        return (int) DB::table('overforums')->count();
    }

    /** @return  array<string, mixed>|null */
    public function getOverforumRow(int $id): ?array
    {
        $row = (array) DB::table('overforums')->where('id', $id)->first();

        return empty($row) ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllOverforums(): array
    {
        return $this->getOverforumsList();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOverforumsList(): array
    {
        return DB::table('overforums')
            ->orderBy('sort')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    private function clearOverforumCache(): void
    {
        Cache::forgetWithLocales('overforums_list');
    }
}
