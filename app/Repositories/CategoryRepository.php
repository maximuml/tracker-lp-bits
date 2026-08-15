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
    private const VALID_SUBCAT_TYPES = ['source', 'medium', 'codec', 'standard', 'processing', 'audiocodec'];

    public static function tableNameForType(string $type): string
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
     * @return list<string>
     */
    public static function validSubcatTypes(): array
    {
        return self::VALID_SUBCAT_TYPES;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function clearCacheAfterDelete(string $type, array $row): void
    {
        $cache = SupportContext::getCache();
        $dbtablename = self::tableNameForType($type);

        if (in_array($type, self::VALID_SUBCAT_TYPES, true)) {
            $cache?->delete_value($dbtablename . '_list');
        } elseif ($type === 'searchbox') {
            $cache?->delete_value('searchbox_content');
        } elseif ($type === 'caticon') {
            $cache?->delete_value('category_icon_content');
        } elseif ($type === 'secondicon') {
            $cache?->delete_value('secondicon_' . $row['source'] . '_' . $row['medium'] . '_' . $row['codec'] . '_' . $row['standard'] . '_' . $row['processing'] . '_' . $row['audiocodec'] . '_content');
        } elseif ($type === 'category') {
            $cache?->delete_value('category_content');
            $cache?->delete_value('category_list_mode_' . $row['mode']);
        }
    }
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
     * @return  array<string, mixed>|null
     */
    public static function getRecord(string $table, int $id): ?array
    {
        $row = NexusDB::table($table)->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public static function deleteRecord(string $table, int $id): bool
    {
        return (bool) NexusDB::table($table)->where('id', $id)->delete();
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getIconRows(): array
    {
        $rows = [];
        foreach (NexusDB::table('caticons')->orderBy('id')->get() as $row) {
            $row = (array) $row;
            $rows[(int) $row['id']] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getCategoryRows(): array
    {
        $rows = [];
        foreach (NexusDB::table('categories')->leftJoin('searchbox', 'categories.mode', '=', 'searchbox.id')->select('categories.*', 'searchbox.name as catmodename')->get() as $row) {
            $row = (array) $row;
            $rows[(int) $row['id']] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public static function findSecondIcon(array $row): ?array
    {
        $source = $row['source'] ?? '';
        $medium = $row['medium'] ?? '';
        $codec = $row['codec'] ?? '';
        $standard = $row['standard'] ?? '';
        $processing = $row['processing'] ?? '';
        $audiocodec = $row['audiocodec'] ?? '';
        $mode = $row['search_box_id'] ?? 0;

        $sirow = NexusDB::table('secondicons')
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
    public static function getCategoriesByMode(int $catmode): array
    {
        return NexusDB::table('categories')
            ->where('mode', $catmode)
            ->orderBy('sort_index', 'desc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
