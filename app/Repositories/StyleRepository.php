<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\StyleRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class StyleRepository implements StyleRepositoryInterface
{
    /** @var array<int, array<string, mixed>>|null */
    private static ?array $rows = null;

    /**
     * @return Collection<int, \stdClass>
     */
    public function listOrderedByName(): Collection
    {
        return DB::table('stylesheets')->orderBy('name')->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::$rows ??= self::queryAll();
    }

    /**
     * Fresh read bypassing the per-process memo (and updating it), for
     * callers revalidating a stale cache.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(): array
    {
        return self::$rows = self::queryAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function queryAll(): array
    {
        $rows = [];
        foreach (DB::table('stylesheets')->orderBy('id')->get() as $row) {
            $row = (array) $row;
            $rows[(int) $row['id']] = $row;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function row(int|string $id): ?array
    {
        return $this->all()[(int) $id] ?? null;
    }

    public function uri(int|string $id): ?string
    {
        $row = $this->row($id);

        return $row !== null ? (string) ($row['uri'] ?? '') : null;
    }

    public function highlightColor(int|string $id): ?string
    {
        $row = $this->row($id);

        return $row !== null ? ($row['hltr'] ?? null) : null;
    }

    public function firstId(): ?int
    {
        $rows = $this->all();

        return $rows === [] ? null : (int) array_key_first($rows);
    }
}
