<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\SupportContext;
use Nexus\Database\NexusDB;

/**
 * Bridge for legacy category management pages until full Blade migration.
 */
final class CategoryRepository
{
    /**
     * @return  array<int, array<string, mixed>>
     */
    public static function getSearchboxOptions(): array
    {
        return NexusDB::table('searchbox')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return  array<int, array<string, mixed>>
     */
    public static function getCaticonOptions(): array
    {
        return NexusDB::table('caticons')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public static function countByTable(string $table): int
    {
        return (int) NexusDB::table($table)->count();
    }

    /**
     * @param  'asc'|'desc'  $direction
     * @return  array<int, array<string, mixed>>
     */
    public static function listByTable(string $table, int $offset, int $perPage, string $sort = 'id', string $direction = 'desc'): array
    {
        return NexusDB::table($table)
            ->orderBy($sort, $direction)
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return  array<int, array<string, mixed>>
     */
    public static function getCategoryList(int $offset, int $perPage): array
    {
        return NexusDB::table('categories')
            ->select(['categories.*', 'searchbox.name as catmodename', 'caticons.name as icon_name'])
            ->leftJoin('searchbox', 'categories.mode', '=', 'searchbox.id')
            ->leftJoin('caticons', 'caticons.id', '=', 'categories.icon_id')
            ->orderBy('categories.mode')
            ->orderBy('categories.id')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return  array<string, array<int|string, string>>
     */
    public static function getSecondiconLookups(): array
    {
        return [
            'source' => NexusDB::table('sources')->pluck('name', 'id')->all(),
            'media' => NexusDB::table('media')->pluck('name', 'id')->all(),
            'codec' => NexusDB::table('codecs')->pluck('name', 'id')->all(),
            'standard' => NexusDB::table('standards')->pluck('name', 'id')->all(),
            'processing' => NexusDB::table('processings')->pluck('name', 'id')->all(),
            'audiocodec' => NexusDB::table('audiocodecs')->pluck('name', 'id')->all(),
        ];
    }

    /**
     * Render the legacy catmanage page.
     *
     * @param array<string, mixed> $data
     */
    public static function render(array $data = []): string
    {
        $partial = __DIR__ . '/../Services/Legacy/catmanage_content.php';
        if (! is_file($partial)) {
            return 'Legacy catmanage partial missing.';
        }

        extract(SupportContext::getGlobalsForView());
        extract($data);

        ob_start();
        /** @noinspection PhpIncludeInspection */
        include $partial;

        return (string) ob_get_clean();
    }
}
