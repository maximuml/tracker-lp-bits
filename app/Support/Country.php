<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\CountryRepositoryInterface;
use App\Support\Cache\NexusCache;

/**
 * Legacy country helper extracted from `include/functions.php`.
 *
 * Backs `get_country_row()`.
 */
final class Country
{
    /**
     * Fetch a country row, using the legacy cache layer.
     *
     * Mirrors `get_country_row()`.
     */
    /**
     * @return array<string, mixed>|null
     */
    public static function row(?NexusCache $cache, int|string $id): ?array
    {
        $cacheKey = 'country_'.$id.'_content';
        $row = $cache?->get($cacheKey) ?? false;

        if ($row === false) {
            $row = app(CountryRepositoryInterface::class)->findById($id);
            $cache?->put($cacheKey, $row, 86400);
        }

        return $row ?: null;
    }

    /**
     * Context-aware wrapper for {@see row()}.
     *
     * @return array<string, mixed>|null
     */
    public static function rowWithContext(int|string $id): ?array
    {
        return self::row(NexusCache::instance(), $id);
    }
}
