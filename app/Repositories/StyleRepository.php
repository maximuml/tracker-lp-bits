<?php

declare(strict_types=1);

namespace App\Repositories;

use Nexus\Database\NexusDB;

final class StyleRepository
{
    /** @var array<int, array<string, mixed>>|null */
    private static ?array $rows = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$rows === null) {
            $rows = [];
            foreach (NexusDB::table('stylesheets')->orderBy('id')->get() as $row) {
                $row = (array) $row;
                $rows[(int) $row['id']] = $row;
            }
            self::$rows = $rows;
        }

        return self::$rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function row(int|string $id): ?array
    {
        return self::all()[(int) $id] ?? null;
    }

    public static function uri(int|string $id): ?string
    {
        $row = self::row($id);

        return $row !== null ? (string) ($row['uri'] ?? '') : null;
    }

    public static function highlightColor(int|string $id): ?string
    {
        $row = self::row($id);

        return $row !== null ? ($row['hltr'] ?? null) : null;
    }
}
