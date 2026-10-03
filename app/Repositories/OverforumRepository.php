<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\OverForum;
use App\Support\Cache;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * @return Collection<int, OverForum>
     */
    public function listOrdered(): Collection
    {
        return OverForum::query()->orderBy('sort')->get();
    }
}
