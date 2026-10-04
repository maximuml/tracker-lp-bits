<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Bridge for legacy category management pages until full Blade migration.
 */
final class CategoryRepository implements CategoryRepositoryInterface
{
    public function tableNameForType(string $type): string
    {
        return match ($type) {
            'category' => 'categories',
            'source' => 'sources',
            'medium' => 'media',
            'codec' => 'codecs',
            'standard' => 'standards',
            'processing' => 'processings',
            'audiocodec' => 'audiocodecs',
            'searchbox' => 'searchbox',
            'caticon' => 'caticons',
            'secondicon' => 'secondicons',
            default => $type,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRecord(string $table, int $id): ?array
    {
        $row = DB::table($table)->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getIconRows(): array
    {
        $rows = [];
        foreach (DB::table('caticons')->orderBy('id')->get() as $row) {
            $row = (array) $row;
            $rows[(int) $row['id']] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCategoryRows(): array
    {
        $rows = [];
        foreach (DB::table('categories')->leftJoin('searchbox', 'categories.mode', '=', 'searchbox.id')->select('categories.*', 'searchbox.name as catmodename')->get() as $row) {
            $row = (array) $row;
            $rows[(int) $row['id']] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public function findSecondIcon(array $row): ?array
    {
        $source = $row['source'] ?? '';
        $medium = $row['medium'] ?? '';
        $codec = $row['codec'] ?? '';
        $standard = $row['standard'] ?? '';
        $processing = $row['processing'] ?? '';
        $audiocodec = $row['audiocodec'] ?? '';
        $mode = $row['search_box_id'] ?? 0;

        $sirow = DB::table('secondicons')
            ->where(function ($query) use ($mode) {
                $query->where('mode', $mode)->orWhere('mode', 0);
            })
            ->where(function ($query) use ($source) {
                $query->where('source', $source)->orWhere('source', 0);
            })
            ->where(function ($query) use ($medium) {
                $query->where('medium', $medium)->orWhere('medium', 0);
            })
            ->where(function ($query) use ($codec) {
                $query->where('codec', $codec)->orWhere('codec', 0);
            })
            ->where(function ($query) use ($standard) {
                $query->where('standard', $standard)->orWhere('standard', 0);
            })
            ->where(function ($query) use ($processing) {
                $query->where('processing', $processing)->orWhere('processing', 0);
            })
            ->where(function ($query) use ($audiocodec) {
                $query->where('audiocodec', $audiocodec)->orWhere('audiocodec', 0);
            })
            ->first();

        return $sirow ? (array) $sirow : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCategoriesByMode(int $catmode): array
    {
        return DB::table('categories')
            ->where('mode', $catmode)
            ->orderBy('sort_index', 'desc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function findById(int $id): ?Category
    {
        return Category::query()->find($id);
    }

    /**
     * @param  array<int, int|string>  $modes
     * @return array<int, int|string>
     */
    public function pluckIdsByModes(array $modes): array
    {
        return Category::query()->whereIn('mode', $modes)->pluck('id')->toArray();
    }
}
