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
